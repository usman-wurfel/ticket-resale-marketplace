<?php

namespace App\Services\Resale;

use App\Models\Customer;
use App\Models\Event;
use App\Models\Event\ResaleTicket;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Orchestrates the buyer-side of the transaction process described in the
 * PRD ("Overview of the Transaction Process"):
 *
 *   1. Preliminary checks (risk tier, purchase window, strict-tier buyer
 *      single-transaction rule).
 *   2. FIFO allocation of a matching listed ticket.
 *   3. Reservation (10 minute payment window).
 *   4. Payment: discount from platform_balance, then gross sale amount held
 *      in escrow_balance (never taken from escrow for discounts).
 *
 * This service does NOT talk to any external payment gateway (explicit PRD
 * requirement at this stage). Payment is simulated by moving the money
 * between internal columns of the customers table.
 */
class ResalePurchaseService
{
    public function __construct(
        private FifoMatcherService $fifo,
        private TransferDeadlineService $deadlines,
        private ConfidenceLevelService $confidence,
        private BalanceService $balance,
        private ResaleNotificationService $notifications,
        private TransferService $transfers
    ) {
    }

    /**
     * Preliminary checks + FIFO allocation + 10-min reservation.
     *
     * @throws \DomainException when a rule is violated.
     */
    public function reserveForBuyer(
        Customer $buyer,
        Event $event,
        ?string $zone,
        float $price,
        array $excludeTicketIds = [],
        int $requestedQty = 1,
        ?string $entranceDate = null
    ): ResaleTicket {
        if ($buyer->isBanned()) {
            throw new \DomainException('Your account has been suspended and cannot purchase tickets.');
        }

        if (!$this->deadlines->canPurchase($event)) {
            throw new \DomainException('The purchase window for this event is closed.');
        }

        if (!$this->deadlines->canAllocate($event)) {
            throw new \DomainException('No new ticket can be allocated at this time.');
        }

        if (!$this->confidence->canOpenNewBuyerTransaction($buyer)) {
            throw new \DomainException($this->confidence->buyerOpenTransactionBlockMessage($buyer));
        }

        // One listing = one ticket (quantity always 1).
        $requestedQty = 1;

        return DB::transaction(function () use ($buyer, $event, $zone, $price, $excludeTicketIds, $requestedQty, $entranceDate) {
            $ticket = $this->fifo->findNext(
                (int) $event->id,
                $zone,
                $price,
                (int) $buyer->id,
                $excludeTicketIds,
                $entranceDate
            );

            if (!$ticket) {
                throw new \DomainException('No ticket matches your criteria right now.');
            }

            // Lock the row for update to avoid two concurrent buyers reserving
            // the same ticket.
            $ticket = ResaleTicket::whereKey($ticket->id)->lockForUpdate()->first();
            if (!$ticket || $ticket->status !== ResaleTicket::STATUS_LISTED) {
                throw new \DomainException('No ticket matches your criteria right now.');
            }

            $availableQty = (int) $ticket->quantity;
            if ($availableQty < 1) {
                throw new \DomainException('This listing is out of stock.');
            }

            // We match FIFO one listing at a time. If the buyer wants more
            // units than the oldest seller has in one listing, we cap to the
            // available qty of that listing. (To buy more, they can repeat
            // the purchase for the next FIFO seller.)
            $takeQty = min($requestedQty, $availableQty);

            $paymentWindow = (int) config('resale.payment_window_minutes', 10);
            $now = Carbon::now();
            $paymentDeadline = $now->copy()->addMinutes($paymentWindow);
            $bookingId = uniqid('res_');

            if ($takeQty >= $availableQty) {
                // Whole listing is taken by this buyer.
                $ticket->fill([
                    'buyer_customer_id' => $buyer->id,
                    'status' => ResaleTicket::STATUS_RESERVED,
                    'reserved_at' => $now,
                    'reserved_until' => $paymentDeadline,
                    'payment_deadline' => $paymentDeadline,
                    'booking_id' => $ticket->booking_id ?: $bookingId,
                ]);
                $ticket->save();

                return $ticket;
            }

            // Partial take → split: create a child for the buyer, leave the
            // remainder listed on the parent row.
            $child = ResaleTicket::create([
                'booking_id' => $bookingId,
                'event_id' => $ticket->event_id,
                'zone' => $ticket->zone,
                'entrance_date' => $ticket->entrance_date,
                'deprioritized' => $ticket->deprioritized,
                'seller_customer_id' => $ticket->seller_customer_id,
                'buyer_customer_id' => $buyer->id,
                'price' => $ticket->price,
                'quantity' => $takeQty,
                'original_quantity' => $takeQty,
                'parent_id' => $ticket->id,
                'status' => ResaleTicket::STATUS_RESERVED,
                'reserved_at' => $now,
                'reserved_until' => $paymentDeadline,
                'payment_deadline' => $paymentDeadline,
                'notes' => $ticket->notes,
                'ticket_pdfs' => $ticket->ticket_pdfs,
            ]);

            $ticket->update([
                'quantity' => $availableQty - $takeQty,
                'status' => ResaleTicket::STATUS_LISTED,
            ]);

            return $child->fresh();
        });
    }

    /**
     * Compute what the buyer effectively has to pay after applying any
     * positive platform_balance (spendable wallet, not escrow).
     */
    public function quote(Customer $buyer, ResaleTicket $ticket): array
    {
        $gross = round((float) $ticket->price * max((int) $ticket->quantity, 1), 2);
        $balanceAvailable = (float) $buyer->platform_balance;
        $balanceUsed = min($balanceAvailable, $gross);
        $payable = round($gross - $balanceUsed, 2);

        return [
            'gross' => $gross,
            'balance_available' => $balanceAvailable,
            'balance_used' => $balanceUsed,
            'payable' => $payable,
        ];
    }

    /**
     * Step 4: the buyer pays within the 10 minutes. We:
     *   - optionally consume part of the buyer's platform_balance (discount)
     *   - register the full gross amount as escrow_balance (held funds)
     *   - mark the ticket as PAID and compute the transfer deadline.
     *
     * Returns the updated ticket.
     */
    public function pay(Customer $buyer, ResaleTicket $ticket): ResaleTicket
    {
        if ($ticket->status !== ResaleTicket::STATUS_RESERVED) {
            throw new \DomainException('Ticket is not in a reservable state.');
        }
        if ((int) $ticket->buyer_customer_id !== (int) $buyer->id) {
            throw new \DomainException('You are not the buyer for this reservation.');
        }
        if ($ticket->payment_deadline && Carbon::parse($ticket->payment_deadline)->lessThan(now())) {
            throw new \DomainException('The 10 minute payment window has expired.');
        }

        return DB::transaction(function () use ($buyer, $ticket) {
            $ticket = ResaleTicket::whereKey($ticket->id)->lockForUpdate()->first();

            $quote = $this->quote($buyer, $ticket);

            // Apply available balance first (reduces the payable amount).
            if ($quote['balance_used'] > 0) {
                $this->balance->applyBalanceDiscount($buyer, $quote['balance_used'], $ticket->id);
            }

            if ($quote['gross'] > 0) {
                $this->balance->recordBuyerPayment($buyer, $quote['gross'], $ticket->id, [
                    'gross' => $quote['gross'],
                    'balance_used' => $quote['balance_used'],
                    'payable' => $quote['payable'],
                ]);
            }

            $allocationTime = Carbon::now();
            $event = $ticket->event()->first();

            $ticket->update([
                'status' => ResaleTicket::STATUS_PAID,
                'paid_at' => $allocationTime,
            ]);
            $ticket->refresh();

            if ($event && $this->transfers->shouldAutoCompleteInternalTransferAtPayment($ticket, $event)) {
                $this->transfers->completeInternalTransferUsingExistingPdfs($ticket);

                return $ticket->fresh();
            }

            if (!$event) {
                throw new \DomainException('Event not found for this ticket.');
            }

            $deadlineResult = $this->deadlines->computeDeadline($event, $allocationTime);
            if (!$deadlineResult['can_transfer']) {
                throw new \DomainException('A resale transfer is no longer possible for this event window.');
            }

            $ticket->update([
                'transfer_status' => ResaleTicket::TRANSFER_PENDING,
                'transfer_deadline' => $deadlineResult['deadline'],
            ]);

            $fresh = $ticket->fresh();
            $this->notifyPaid($fresh);
            return $fresh;
        });
    }

    private function notifyPaid(ResaleTicket $ticket): void
    {
        $buyer = $ticket->buyer_customer_id ? Customer::find($ticket->buyer_customer_id) : null;
        $seller = $ticket->seller_customer_id ? Customer::find($ticket->seller_customer_id) : null;

        if ($buyer) {
            $this->notifications->notify(
                $buyer,
                \App\Models\ResaleNotification::TYPE_PAYMENT_CONFIRMED,
                'Payment received',
                'Your payment has been received. The seller will now transfer the ticket to you.',
                ['resale_ticket_id' => $ticket->id]
            );
        }
        if ($seller) {
            $this->notifications->notify(
                $seller,
                \App\Models\ResaleNotification::TYPE_TRANSFER_REQUESTED,
                'Action required: transfer your ticket',
                'A buyer has paid for your ticket. Please transfer it before the deadline.',
                ['resale_ticket_id' => $ticket->id]
            );
        }
    }
}

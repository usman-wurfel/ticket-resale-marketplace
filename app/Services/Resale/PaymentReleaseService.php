<?php

namespace App\Services\Resale;

use App\Models\Customer;
use App\Models\Event\ResaleTicket;
use App\Models\SupportTicket;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Releases payments to sellers and drives the T+48h timer.
 *
 * Rules (PRD):
 *   - No litigation                              → release at T + 48h
 *   - Litigations all resolved within 48h        → release at T + 48h
 *   - At least one litigation unresolved at 48h  → release when the last
 *                                                  litigation is resolved
 *
 * This service is idempotent: once a ticket has `payment_released_at` set,
 * we don't release again.
 */
class PaymentReleaseService
{
    public function __construct(
        private BalanceService $balance,
        private ConfidenceLevelService $confidence,
        private ResaleNotificationService $notifications
    ) {
    }

    /**
     * Try to release payment for all eligible transferred tickets. Usually
     * invoked by cron.
     *
     * @return int Number of tickets processed in this run
     */
    public function releaseDue(?Carbon $now = null): int
    {
        $now = $now ?: Carbon::now();

        $candidates = ResaleTicket::query()
            ->where('transfer_status', ResaleTicket::TRANSFER_DONE)
            ->whereNull('payment_released_at')
            ->whereNotNull('payment_release_due_at')
            ->where('payment_release_due_at', '<=', $now)
            ->get();

        $processed = 0;
        foreach ($candidates as $ticket) {
            if ($this->releaseForTicket($ticket, $now)) {
                $processed++;
            }
        }
        return $processed;
    }

    /**
     * Release a single ticket if ready. Returns true if released.
     */
    public function releaseForTicket(ResaleTicket $ticket, ?Carbon $now = null): bool
    {
        $now = $now ?: Carbon::now();

        if (!empty($ticket->payment_released_at)) {
            return false;
        }

        // If the ticket hasn't been transferred yet there is nothing to release.
        if ($ticket->transfer_status !== ResaleTicket::TRANSFER_DONE) {
            return false;
        }

        // The due date must be in the past OR all litigations must be resolved.
        $hasOpen = SupportTicket::where('resale_ticket_id', $ticket->id)
            ->where('is_litigation', true)
            ->where('litigation_status', SupportTicket::LITIGATION_OPEN)
            ->exists();

        if ($hasOpen) {
            return false;
        }

        $dueAt = $ticket->payment_release_due_at
            ? Carbon::parse($ticket->payment_release_due_at)
            : null;

        if ($dueAt && $dueAt->greaterThan($now)) {
            return false;
        }

        return (bool) DB::transaction(function () use ($ticket) {
            $ticket->refresh();
            if (!empty($ticket->payment_released_at)) {
                return false;
            }

            $amount = round((float) $ticket->price * max((int) $ticket->quantity, 1), 2);

            $commissionPercent = (float) (DB::table('basic_settings')->value('commission') ?? 0);
            if ($commissionPercent < 0) {
                $commissionPercent = 0;
            }
            if ($commissionPercent > 100) {
                $commissionPercent = 100;
            }
            // Seller receives Price × (1 − commission). Commission already includes taxes/costs.
            $sellerAmount = round($amount * (1 - ($commissionPercent / 100)), 2);

            $seller = Customer::find($ticket->seller_customer_id);
            $buyer = Customer::find($ticket->buyer_customer_id);

            // Check for an unfavorable outcome across all resolved litigations
            // for this ticket. If the seller was blamed in any of them, we
            // refund the buyer rather than release.
            $sellerBlamed = SupportTicket::where('resale_ticket_id', $ticket->id)
                ->where('is_litigation', true)
                ->where('litigation_status', SupportTicket::LITIGATION_RESOLVED)
                ->where('outcome', SupportTicket::OUTCOME_SELLER)
                ->exists();

            if ($sellerBlamed && $buyer) {
                $this->balance->refundBuyer($buyer, $amount, $ticket->id, 'seller_blamed_litigation');
                $this->notifications->notify(
                    $buyer,
                    \App\Models\ResaleNotification::TYPE_PAYMENT_RELEASED,
                    'Refund issued after litigation',
                    'Your payment has been refunded following the litigation resolution.',
                    ['resale_ticket_id' => $ticket->id]
                );
            } elseif ($seller) {
                if ($buyer) {
                    $this->balance->settleBuyerEscrowForCompletedPurchase($buyer, $amount, $ticket->id);
                }
                $this->balance->releaseToSeller($seller, $sellerAmount, $ticket->id);
                $this->notifications->notify(
                    $seller,
                    \App\Models\ResaleNotification::TYPE_PAYMENT_RELEASED,
                    'Your payment has been released',
                    'Your funds from the ticket sale are now available in your withdrawable balance.',
                    [
                        'resale_ticket_id' => $ticket->id,
                        'gross' => $amount,
                        'commission_percent' => $commissionPercent,
                        'net' => $sellerAmount,
                    ]
                );
            } else {
                Log::warning("PaymentRelease: missing seller for resale ticket {$ticket->id}");
                return false;
            }

            $ticket->payment_released_at = now();
            $ticket->status = ResaleTicket::STATUS_SOLD;
            $ticket->save();

            // Recalculate confidence for both parties now the transaction is
            // finalized (PRD: "At the end of the 48 hours after the end of
            // the event, or after the last litigation if it exceeds the 48
            // hours, we recalculate the User Confidence Level").
            if ($seller) {
                $this->confidence->recalculate($seller);
            }
            if ($buyer) {
                $this->confidence->recalculate($buyer);
            }

            return true;
        });
    }
}

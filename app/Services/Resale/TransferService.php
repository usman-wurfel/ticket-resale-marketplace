<?php

namespace App\Services\Resale;

use App\Models\Customer;
use App\Models\Event;
use App\Models\Event\ResaleTicket;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Handles the seller-side transfer of a paid resale ticket to the buyer, and
 * the reassignment flow when the transfer deadline is missed.
 *
 * PRD:
 *   - Internal transfer: seller uploads the ticket in PDF; once stored, the
 *     ticket is considered transferred.
 *   - External transfer: seller confirms they transferred the ticket on an
 *     external platform (e.g. Ticketmaster).
 *
 *   Missed deadline consequences (PRD):
 *     - transaction cancelled
 *     - ticket is unassigned and becomes available again (deprioritized)
 *     - seller keeps their positive balance on their platform account
 *       (nothing to refund – the platform has not received money FROM the
 *       seller; we just do not release the escrow)
 *     - buyer is notified; we offer to assign an equivalent ticket
 */
class TransferService
{
    public function __construct(
        private BalanceService $balance,
        private FifoMatcherService $fifo,
        private TransferDeadlineService $deadlines,
        private ConfidenceLevelService $confidence,
        private ResaleNotificationService $notifications
    ) {
    }

    /**
     * Decide whether the seller must upload the ticket PDF *at listing time*.
     *
     * Client spec ("Transfer Timeline and Transfer Deadlines"):
     *   - Internal Transfer and p > h  → the ticket is already available to the
     *     seller when they list (h is NULL or already passed), so they must
     *     upload the PDF straight away. No transfer deadline is then sent to
     *     them when a buyer purchases.
     *   - Internal Transfer and p < h  → the ticket is NOT yet available to the
     *     seller, so we must NOT ask for the PDF at listing. The transfer then
     *     follows the same deadline rules as an External Transfer.
     *   - External Transfer            → never upload at listing.
     *
     * @param  Carbon|null  $listingTime  Instant p (defaults to now).
     */
    public function sellerMustUploadPdfAtListing(Event $event, ?Carbon $listingTime = null): bool
    {
        if (($event->event_ticket_transfer_type ?? 'external') !== 'internal') {
            return false;
        }

        $p = $listingTime ?: Carbon::now();
        $hRaw = $event->getAttributes()['ticket_availability_datetime'] ?? null;

        // h is NULL → ticket already available → must upload (p >= h).
        if ($hRaw === null || $hRaw === '') {
            return true;
        }

        // p >= h → already available at listing → must upload.
        return $p->greaterThanOrEqualTo(Carbon::parse((string) $hRaw));
    }

    /**
     * Internal transfer + the seller has already uploaded enough ticket PDFs
     * on the platform (either at listing time when p > h, or at any moment
     * after the ticket became available): we already hold the file, so we do
     * not ask the seller to "transfer" anything – we just hand the existing
     * PDF to the buyer and mark the transfer done. This is the very first
     * step of the post-payment flow.
     */
    public function shouldAutoCompleteInternalTransferAtPayment(ResaleTicket $ticket, Event $event): bool
    {
        if (($event->event_ticket_transfer_type ?? 'external') !== 'internal') {
            return false;
        }
        $qty = max(1, (int) $ticket->quantity);

        return count($this->effectiveTicketPdfsForPolicy($ticket)) >= $qty;
    }

    /**
     * Mark internal transfer complete using PDFs already stored (listing or parent row).
     */
    public function completeInternalTransferUsingExistingPdfs(ResaleTicket $ticket): ResaleTicket
    {
        if ($ticket->status !== ResaleTicket::STATUS_PAID) {
            throw new \DomainException('Ticket must be paid before completing transfer.');
        }

        return $this->markTransferred($ticket, []);
    }

    public function resaleListingRoot(ResaleTicket $ticket): ResaleTicket
    {
        if ($ticket->parent_id) {
            $parent = ResaleTicket::find($ticket->parent_id);

            return $parent ?: $ticket;
        }

        return $ticket;
    }

    private function effectiveTicketPdfsForPolicy(ResaleTicket $ticket): array
    {
        $candidates = [];
        if ($ticket->parent_id) {
            $p = ResaleTicket::find($ticket->parent_id);
            if ($p) {
                $candidates[] = $p;
            }
        }
        $candidates[] = $ticket;
        foreach ($candidates as $row) {
            $list = $this->existingPdfs($row);
            if (count($list) > 0) {
                return $list;
            }
        }

        return [];
    }

    /**
     * Internal transfer: seller uploads one or more PDFs (one per seat).
     * Marks the ticket as transferred and starts the T+48h release timer.
     *
     * If the seller had already attached PDFs at listing time (they are
     * stored in `ticket_pdfs`) they can skip uploading by passing an empty
     * array — we will simply mark the existing files as delivered.
     *
     * @param  UploadedFile[]  $pdfs
     */
    public function internalUpload(ResaleTicket $ticket, array $pdfs, Customer $seller): ResaleTicket
    {
        $this->assertSeller($ticket, $seller);
        $this->assertReadyForTransfer($ticket);

        $existing = $this->existingPdfs($ticket);
        $qty = max(1, (int) $ticket->quantity);
        $availableAfterUpload = count($existing) + count($pdfs);

        if ($availableAfterUpload < $qty) {
            throw new \DomainException(sprintf(
                'You must attach %d ticket PDF(s) in total (%d already on file).',
                $qty,
                count($existing)
            ));
        }

        $directory = public_path('assets/admin/file/resale-tickets/');
        @mkdir($directory, 0775, true);

        $added = [];
        foreach ($pdfs as $pdf) {
            if (!$pdf instanceof UploadedFile) {
                continue;
            }
            $filename = 'resale-transfer-' . uniqid() . '.pdf';
            $pdf->move($directory, $filename);
            $added[] = $filename;
        }

        return $this->markTransferred($ticket, $added);
    }

    private function existingPdfs(ResaleTicket $ticket): array
    {
        if (empty($ticket->ticket_pdfs)) {
            return [];
        }
        $decoded = json_decode((string) $ticket->ticket_pdfs, true);
        return is_array($decoded) ? array_values(array_filter($decoded)) : [];
    }

    /**
     * External transfer: the seller claims to have transferred the ticket on
     * an external platform. We trust their declaration and start the timer.
     */
    public function externalConfirm(ResaleTicket $ticket, Customer $seller): ResaleTicket
    {
        $this->assertSeller($ticket, $seller);
        $this->assertReadyForTransfer($ticket);

        return $this->markTransferred($ticket, []);
    }

    /**
     * Cron: look for paid tickets whose transfer deadline has been missed
     * and apply the consequences described in the PRD.
     *
     * @return int Number of tickets processed
     */
    public function enforceMissedDeadlines(?Carbon $now = null): int
    {
        $now = $now ?: Carbon::now();

        $ids = ResaleTicket::query()
            ->where('status', ResaleTicket::STATUS_PAID)
            ->where('transfer_status', ResaleTicket::TRANSFER_PENDING)
            ->whereNotNull('transfer_deadline')
            ->where('transfer_deadline', '<', $now)
            ->pluck('id');

        $count = 0;
        foreach ($ids as $id) {
            $done = DB::transaction(function () use ($id) {
                /** @var ResaleTicket|null $ticket */
                $ticket = ResaleTicket::whereKey($id)->lockForUpdate()->first();
                if (!$ticket || $ticket->status !== ResaleTicket::STATUS_PAID) {
                    return false;
                }
                if ($ticket->transfer_status !== ResaleTicket::TRANSFER_PENDING) {
                    return false;
                }

                // Count this miss. On the 2nd missed deadline for the SAME
                // ticket we cancel it outright (client request); on the 1st we
                // relist it deprioritized + locked.
                $originalBuyerId = $ticket->buyer_customer_id;
                $missCount = (int) $ticket->missed_deadline_count + 1;
                $cancelNow = $missCount >= 2;

                $ticket->update([
                    'transfer_status' => ResaleTicket::TRANSFER_MISSED,
                    'status' => $cancelNow ? ResaleTicket::STATUS_CANCELLED : ResaleTicket::STATUS_LISTED,
                    'missed_deadline_count' => $missCount,
                    'deprioritized' => true,
                    'deprioritized_locked' => true,
                    'buyer_customer_id' => null,
                    'last_buyer_customer_id' => $originalBuyerId,
                    'reserved_at' => null,
                    'reserved_until' => null,
                    'payment_deadline' => null,
                    'paid_at' => null,
                    'transfer_deadline' => null,
                ]);

                // Refund the buyer back to platform balance and offer them an
                // equivalent (same event / zone / price ≤ original). The buyer
                // can Accept (FIFO allocates a new ticket) or Decline (must find
                // another one themselves) from the notification.
                if ($originalBuyerId) {
                    $buyer = Customer::find($originalBuyerId);
                    if ($buyer) {
                        $amount = round((float) $ticket->price * max((int) $ticket->quantity, 1), 2);
                        $this->balance->refundBuyer($buyer, $amount, $ticket->id, 'transfer_deadline_missed');

                        $equivalent = $this->fifo->findEquivalent($ticket, (int) $buyer->id);
                        if ($equivalent) {
                            $this->notifications->notify(
                                $buyer,
                                \App\Models\ResaleNotification::TYPE_REASSIGNMENT_OFFERED,
                                'An equivalent ticket is available',
                                'The seller missed the transfer deadline so the sale was cancelled and you have been refunded. We found another ticket for the same event, same zone and at an equal or lower price. Would you like us to assign it to you automatically?',
                                ['resale_ticket_id' => $ticket->id]
                            );
                        } else {
                            $this->notifications->notify(
                                $buyer,
                                \App\Models\ResaleNotification::TYPE_REASSIGNMENT_UNAVAILABLE,
                                'Transfer deadline missed',
                                'The seller missed the transfer deadline so the sale was cancelled and you have been refunded. We could not find an equivalent ticket right now (same event, same zone, equal or lower price). You can browse the event again to pick another one.',
                                ['resale_ticket_id' => $ticket->id]
                            );
                        }
                    }
                }

                if ($ticket->seller_customer_id) {
                    $seller = Customer::find($ticket->seller_customer_id);
                    if ($seller) {
                        $this->notifications->notify(
                            $seller,
                            \App\Models\ResaleNotification::TYPE_TRANSFER_FAILED,
                            $cancelNow ? 'Your listing was cancelled (deadline missed twice)' : 'You missed the transfer deadline',
                            $cancelNow
                                ? 'The transfer deadline was missed for the second time on this ticket, so the listing has been cancelled. Your confidence level has been impacted.'
                                : 'The transfer deadline was reached without a transfer. Your confidence level has been impacted.',
                            ['resale_ticket_id' => $ticket->id]
                        );
                    }
                }

                return true;
            });

            if ($done) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Reassign an equivalent ticket to a buyer whose previous ticket failed.
     * Always obeys FIFO + "never the same ticket" (PRD).
     */
    public function reassignEquivalent(ResaleTicket $previous, Customer $buyer): ?ResaleTicket
    {
        $previousBuyerId = (int) ($previous->buyer_customer_id ?: $previous->last_buyer_customer_id);
        if ($previousBuyerId && $previousBuyerId !== (int) $buyer->id) {
            throw new \DomainException('Cannot reassign for a different buyer.');
        }

        $replacement = $this->fifo->findEquivalent($previous, $buyer->id);
        if (!$replacement) {
            return null;
        }

        return DB::transaction(function () use ($replacement, $buyer, $previous) {
            $ticket = ResaleTicket::whereKey($replacement->id)->lockForUpdate()->first();
            if (!$ticket || $ticket->status !== ResaleTicket::STATUS_LISTED) {
                return null;
            }

            $now = Carbon::now();
            $ticket->update([
                'buyer_customer_id' => $buyer->id,
                'status' => ResaleTicket::STATUS_RESERVED,
                'reserved_at' => $now,
                'reserved_until' => $now->copy()->addMinutes((int) config('resale.payment_window_minutes', 10)),
                'payment_deadline' => $now->copy()->addMinutes((int) config('resale.payment_window_minutes', 10)),
                'previous_ticket_id' => $previous->id,
                'booking_id' => $ticket->booking_id ?: uniqid('res_'),
            ]);

            return $ticket->fresh();
        });
    }

    private function assertSeller(ResaleTicket $ticket, Customer $seller): void
    {
        if ((int) $ticket->seller_customer_id !== (int) $seller->id) {
            throw new \DomainException('You are not the seller for this ticket.');
        }
    }

    private function assertReadyForTransfer(ResaleTicket $ticket): void
    {
        if ($ticket->status !== ResaleTicket::STATUS_PAID) {
            throw new \DomainException('Ticket is not ready for transfer (must be paid).');
        }
        if ($ticket->transfer_status !== ResaleTicket::TRANSFER_PENDING) {
            throw new \DomainException('Transfer is not pending for this ticket.');
        }
        if ($ticket->transfer_deadline && Carbon::parse($ticket->transfer_deadline)->lessThan(now())) {
            throw new \DomainException('The transfer deadline has already passed.');
        }

        // Make sure tickets are available on the organizer side.
        $event = $ticket->event()->first();
        if ($event && $event->ticket_availability_datetime) {
            $availability = Carbon::parse($event->ticket_availability_datetime);
            if ($availability->greaterThan(now())) {
                throw new \DomainException('Tickets are not yet available for transfer.');
            }
        }
    }

    private function markTransferred(ResaleTicket $ticket, array $addedPdfs): ResaleTicket
    {
        $existing = [];
        if (!empty($ticket->ticket_pdfs)) {
            $decoded = json_decode((string) $ticket->ticket_pdfs, true);
            if (is_array($decoded)) {
                $existing = $decoded;
            }
        }
        $merged = array_values(array_filter(array_merge($existing, $addedPdfs)));

        $event = $ticket->event()->first();
        $endRef = $event && $event->end_date_time
            ? Carbon::parse($event->end_date_time)
            : ($event ? $event->startDateTime() : null);

        $releaseDueAt = $endRef
            ? $endRef->copy()->addHours((int) config('resale.release_hold_hours', 48))
            : now()->addHours((int) config('resale.release_hold_hours', 48));

        $ticket->update([
            'status' => ResaleTicket::STATUS_TRANSFERRED,
            'transfer_status' => ResaleTicket::TRANSFER_DONE,
            'transferred_at' => now(),
            'transfer_deadline' => null,
            'ticket_pdfs' => $merged ? json_encode($merged) : null,
            'payment_release_due_at' => $releaseDueAt,
        ]);

        $fresh = $ticket->fresh();
        $this->notifyTransferred($fresh);

        return $fresh;
    }

    private function notifyTransferred(ResaleTicket $ticket): void
    {
        if ($ticket->buyer_customer_id) {
            $buyer = Customer::find($ticket->buyer_customer_id);
            if ($buyer) {
                $this->notifications->notify(
                    $buyer,
                    \App\Models\ResaleNotification::TYPE_TRANSFER_RECEIVED,
                    'Your ticket has been transferred',
                    'The seller has transferred your ticket. Please check your bought-tickets page and confirm reception.',
                    ['resale_ticket_id' => $ticket->id]
                );
            }
        }
        if ($ticket->seller_customer_id) {
            $seller = Customer::find($ticket->seller_customer_id);
            if ($seller) {
                $this->notifications->notify(
                    $seller,
                    \App\Models\ResaleNotification::TYPE_TRANSFER_REQUESTED,
                    'Transfer recorded',
                    'Your transfer has been recorded. Payment will be released after the T+48h hold if no litigation is opened.',
                    ['resale_ticket_id' => $ticket->id]
                );
            }
        }
    }
}

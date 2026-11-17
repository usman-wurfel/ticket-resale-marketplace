<?php

namespace App\Services\Resale;

use App\Models\Customer;
use App\Models\Event\ResaleTicket;
use App\Models\SupportTicket;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * PRD: "Payment Release, Refunds, and Litigations".
 *
 *   - A litigation may be created from the moment the ticket is transferred
 *     and no later than 48 hours after the end of the event.
 *   - If no litigation is created, payment is released 48h after the end of
 *     the event.
 *   - If litigations are resolved within the 48h window, payment is still
 *     released at T + 48h.
 *   - If any litigation is not resolved within the window, payment is
 *     released when the LAST litigation is resolved.
 *
 *   When resolving a litigation, the operator assigns blame:
 *     - seller, buyer, or unclear.
 */
class LitigationService
{
    public function __construct(
        private BalanceService $balance,
        private ConfidenceLevelService $confidence,
        private ResaleNotificationService $notifications
    ) {
    }

    /**
     * Open a new litigation on a resale ticket. Returns the created support
     * ticket (which doubles as the litigation record).
     *
     * @throws \DomainException if the litigation window is closed.
     */
    public function open(
        ResaleTicket $resaleTicket,
        Customer $openedBy,
        string $subject,
        string $description,
        ?string $attachment = null
    ): SupportTicket {
        if (!$this->withinLitigationWindow($resaleTicket)) {
            throw new \DomainException('Litigation window is closed for this ticket.');
        }

        if (!in_array($resaleTicket->transfer_status, [
            ResaleTicket::TRANSFER_DONE,
        ], true)) {
            throw new \DomainException('Litigations can only be opened after the ticket is transferred.');
        }

        $counterpartyId = ((int) $openedBy->id === (int) $resaleTicket->seller_customer_id)
            ? $resaleTicket->buyer_customer_id
            : $resaleTicket->seller_customer_id;

        $ticket = SupportTicket::create([
            'user_id' => $openedBy->id,
            'user_type' => 'customer',
            'email' => $openedBy->email,
            'subject' => $subject,
            'description' => $description,
            'attachment' => $attachment,
            'status' => 0,
            'source_type' => 'resale_ticket',
            'source_id' => $resaleTicket->id,
            'event_id' => $resaleTicket->event_id,
            'resale_ticket_id' => $resaleTicket->id,
            'booking_ref' => $resaleTicket->booking_id,
            'counterparty_user_type' => 'customer',
            'counterparty_user_id' => $counterpartyId,
            'is_litigation' => true,
            'litigation_status' => SupportTicket::LITIGATION_OPEN,
        ]);

        if ($counterpartyId) {
            $counterparty = Customer::find($counterpartyId);
            if ($counterparty) {
                $this->notifications->notify(
                    $counterparty,
                    \App\Models\ResaleNotification::TYPE_LITIGATION_OPENED,
                    'A litigation was opened',
                    'A litigation has been opened about your recent resale transaction. Please check the incident center for details.',
                    ['resale_ticket_id' => $resaleTicket->id, 'support_ticket_id' => $ticket->id]
                );
            }
        }

        return $ticket;
    }

    /**
     * Resolve a litigation.
     *
     * @param  string  $outcome  seller | buyer | unclear (blame for confidence)
     * @param  array   $settlement  Optional explicit financial decision:
     *     [
     *       'mode'                => 'timeline' | 'manual',
     *       'reimbursement_type'  => 'none' | 'partial' | 'full',
     *       'reimbursement_amount'=> float,   // used when partial
     *       'release_to_seller'   => bool,
     *     ]
     *   When mode = 'manual' the admin's decision is applied immediately
     *   (refund the buyer fully/partially, and/or release the payment to the
     *   seller) and the sale is finalized. When mode = 'timeline' (default) we
     *   only record the outcome and let the normal T+48h release flow run.
     */
    public function resolve(
        SupportTicket $litigation,
        string $outcome,
        ?int $adminId = null,
        array $settlement = []
    ): SupportTicket {
        $outcome = strtolower($outcome);
        if (!in_array($outcome, [
            SupportTicket::OUTCOME_SELLER,
            SupportTicket::OUTCOME_BUYER,
            SupportTicket::OUTCOME_UNCLEAR,
            SupportTicket::OUTCOME_NONE,
        ], true)) {
            throw new \InvalidArgumentException('Invalid outcome.');
        }

        // Accidental / not-a-real-incident: no financial movement by default.
        if ($outcome === SupportTicket::OUTCOME_NONE) {
            $settlement = array_merge([
                'mode' => SupportTicket::SETTLEMENT_TIMELINE,
                'reimbursement_type' => SupportTicket::REIMBURSE_NONE,
                'reimbursement_amount' => 0,
                'release_to_seller' => false,
            ], $settlement);
            // Prefer timeline unless admin explicitly chose manual.
            if (($settlement['mode'] ?? '') !== SupportTicket::SETTLEMENT_MANUAL) {
                $settlement['mode'] = SupportTicket::SETTLEMENT_TIMELINE;
            }
        }

        $mode = ($settlement['mode'] ?? SupportTicket::SETTLEMENT_TIMELINE) === SupportTicket::SETTLEMENT_MANUAL
            ? SupportTicket::SETTLEMENT_MANUAL
            : SupportTicket::SETTLEMENT_TIMELINE;
        $reimbType = strtolower((string) ($settlement['reimbursement_type'] ?? SupportTicket::REIMBURSE_NONE));
        if (!in_array($reimbType, [SupportTicket::REIMBURSE_NONE, SupportTicket::REIMBURSE_PARTIAL, SupportTicket::REIMBURSE_FULL], true)) {
            $reimbType = SupportTicket::REIMBURSE_NONE;
        }
        $reimbAmountReq = round((float) ($settlement['reimbursement_amount'] ?? 0), 2);
        $releaseToSeller = (bool) ($settlement['release_to_seller'] ?? false);

        DB::transaction(function () use ($litigation, $outcome, $adminId, $mode, $reimbType, $reimbAmountReq, $releaseToSeller) {
            $resaleTicket = $litigation->resale_ticket_id
                ? ResaleTicket::find($litigation->resale_ticket_id)
                : null;

            $blameType = null;
            $blameId = null;
            if ($resaleTicket) {
                if ($outcome === SupportTicket::OUTCOME_SELLER) {
                    $blameType = 'customer';
                    $blameId = $resaleTicket->seller_customer_id;
                } elseif ($outcome === SupportTicket::OUTCOME_BUYER) {
                    $blameType = 'customer';
                    $blameId = $resaleTicket->buyer_customer_id;
                }
            }

            $litigation->outcome = $outcome;
            $litigation->litigation_status = SupportTicket::LITIGATION_RESOLVED;
            $litigation->resolved_at = now();
            $litigation->resolver_admin_id = $adminId;
            $litigation->blame_user_type = $blameType;
            $litigation->blame_user_id = $blameId;
            $litigation->settlement_mode = $mode;
            $litigation->reimbursement_type = $reimbType;
            $litigation->reimbursement_amount = $reimbType === SupportTicket::REIMBURSE_NONE ? 0 : $reimbAmountReq;
            $litigation->release_to_seller = $releaseToSeller;
            // Point 2 fix: a resolved litigation must REMAIN "Open" until the
            // admin manually closes the incident — except outcome "none"
            // (not a real incident), which closes immediately without blame.
            if ($outcome === SupportTicket::OUTCOME_NONE) {
                $litigation->status = SupportTicket::STATUS_CLOSED;
            } elseif ((int) $litigation->status !== SupportTicket::STATUS_CLOSED) {
                $litigation->status = SupportTicket::STATUS_OPEN;
            }
            $litigation->save();

            // Apply the admin's explicit financial decision now (if manual).
            // Skip for outcome "none" unless admin forced manual settlement.
            if ($mode === SupportTicket::SETTLEMENT_MANUAL && $resaleTicket && $outcome !== SupportTicket::OUTCOME_NONE) {
                $this->applyManualSettlement($resaleTicket, $reimbType, $reimbAmountReq, $releaseToSeller);
            }

            // Recalculate confidence for the blamed user so the counter is
            // reflected immediately. Outcome "none" never blames anyone.
            if ($blameId) {
                $customer = Customer::find($blameId);
                if ($customer) {
                    $this->confidence->recalculate($customer);
                }
            }

            // Notify both parties.
            if ($resaleTicket) {
                foreach ([$resaleTicket->seller_customer_id, $resaleTicket->buyer_customer_id] as $cid) {
                    if (!$cid) continue;
                    $c = Customer::find($cid);
                    if ($c) {
                        $this->notifications->notify(
                            $c,
                            \App\Models\ResaleNotification::TYPE_LITIGATION_RESOLVED,
                            'Litigation resolved',
                            'A litigation on your resale transaction has been resolved. Outcome: ' . $outcome . '.',
                            ['resale_ticket_id' => $resaleTicket->id, 'support_ticket_id' => $litigation->id]
                        );
                    }
                }
            }
        });

        return $litigation->fresh();
    }

    /**
     * Execute the admin's manual financial decision and finalize the sale so
     * the automatic T+48h release flow does not also run for this ticket.
     */
    private function applyManualSettlement(
        ResaleTicket $resaleTicket,
        string $reimbType,
        float $reimbAmountReq,
        bool $releaseToSeller
    ): void {
        if (!empty($resaleTicket->payment_released_at)) {
            return; // already finalized – do nothing
        }

        $gross = round((float) $resaleTicket->price * max((int) $resaleTicket->quantity, 1), 2);
        $buyer = $resaleTicket->buyer_customer_id ? Customer::find($resaleTicket->buyer_customer_id) : null;
        $seller = $resaleTicket->seller_customer_id ? Customer::find($resaleTicket->seller_customer_id) : null;

        // 1) Reimbursement to the buyer (none / partial / full).
        $refundAmount = 0.0;
        if ($reimbType === SupportTicket::REIMBURSE_FULL) {
            $refundAmount = $gross;
        } elseif ($reimbType === SupportTicket::REIMBURSE_PARTIAL) {
            $refundAmount = max(0.0, min($reimbAmountReq, $gross));
        }
        if ($refundAmount > 0 && $buyer) {
            $this->balance->refundBuyer($buyer, $refundAmount, $resaleTicket->id, 'litigation_admin_reimbursement');
            $this->notifications->notify(
                $buyer,
                \App\Models\ResaleNotification::TYPE_PAYMENT_RELEASED,
                'Reimbursement issued',
                'Following the litigation decision, ' . number_format($refundAmount, 2) . ' has been credited to your platform balance.',
                ['resale_ticket_id' => $resaleTicket->id]
            );
        }

        // 2) Release the payment to the seller (can happen together with a
        //    reimbursement – "both at the same time" per the client).
        if ($releaseToSeller && $seller) {
            // Settle any remaining escrow the buyer still holds for this sale.
            if ($buyer) {
                $this->balance->settleBuyerEscrowForCompletedPurchase($buyer, $gross, $resaleTicket->id);
            }
            $commissionPercent = (float) (DB::table('basic_settings')->value('commission') ?? 0);
            if ($commissionPercent < 0) {
                $commissionPercent = 0;
            }
            if ($commissionPercent > 100) {
                $commissionPercent = 100;
            }
            $sellerNet = round($gross * (1 - ($commissionPercent / 100)), 2);
            $this->balance->releaseToSeller($seller, $sellerNet, $resaleTicket->id);
            $this->notifications->notify(
                $seller,
                \App\Models\ResaleNotification::TYPE_PAYMENT_RELEASED,
                'Your payment has been released',
                'Following the litigation decision, your funds from the ticket sale are now available in your withdrawable balance.',
                [
                    'resale_ticket_id' => $resaleTicket->id,
                    'gross' => $gross,
                    'commission_percent' => $commissionPercent,
                    'net' => $sellerNet,
                ]
            );
        }

        // Finalize so the cron release flow will skip this ticket.
        $resaleTicket->payment_released_at = now();
        $resaleTicket->status = ResaleTicket::STATUS_SOLD;
        $resaleTicket->save();

        if ($seller) {
            $this->confidence->recalculate($seller);
        }
        if ($buyer) {
            $this->confidence->recalculate($buyer);
        }
    }

    /**
     * Window: [transferred_at, eventEnd + 48h].
     */
    public function withinLitigationWindow(ResaleTicket $resaleTicket, ?Carbon $now = null): bool
    {
        $now = $now ?: Carbon::now();
        if (empty($resaleTicket->transferred_at)) {
            return false;
        }

        $event = $resaleTicket->event;
        $endRef = $event && $event->end_date_time
            ? Carbon::parse($event->end_date_time)
            : ($event ? $event->startDateTime() : null);

        if (!$endRef) {
            return false;
        }

        $windowClose = $endRef->copy()->addHours((int) config('resale.release_hold_hours', 48));
        return $now->lessThanOrEqualTo($windowClose);
    }

    public function hasOpenLitigations(ResaleTicket $resaleTicket): bool
    {
        return SupportTicket::where('resale_ticket_id', $resaleTicket->id)
            ->where('is_litigation', true)
            ->where('litigation_status', SupportTicket::LITIGATION_OPEN)
            ->exists();
    }
}

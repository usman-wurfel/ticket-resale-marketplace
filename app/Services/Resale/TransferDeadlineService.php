<?php

namespace App\Services\Resale;

use App\Models\Event;
use Carbon\Carbon;

/**
 * Transfer timeline rules (PRD / client spec).
 *
 *   T = event start
 *   t = allocation time (buyer payment / reservation completion — use the same instant here)
 *   h = when tickets become available to the seller (ticket_availability_datetime, or "now" if unset)
 *
 * Purchase window: no purchase after T − 12h30 (see {@see canPurchase}).
 *
 * For **thresholds** (whether there is enough time before T):
 *   - If tickets are already available at t: use T − t.
 *   - If not yet available at t: use T − h.
 *
 * **Deadlines** (when a transfer must be completed):
 *   - If T − (ref) ≥ 48h  → deadline = min(t + 48h, T − 12h)  (t is always allocation time)
 *   - If 48h > T − (ref) ≥ 12h30 → deadline = t + 30m if already available at t, else h + 30m
 *   - If 12h30 > T − (ref) ≥ 12h → no new allocations; existing rows keep deadlines set elsewhere
 *   - If 12h > T − (ref) → no transfer possible
 *
 * `ref` is t or h for the band test only, as above.
 */
class TransferDeadlineService
{
    public const PURCHASE_CUTOFF_MINUTES = 750;   // 12h30
    public const TRANSFER_CUTOFF_12H_MINUTES = 720;
    public const TRANSFER_CUTOFF_48H_MINUTES = 2880;

    public function canPurchase(Event $event, ?Carbon $now = null): bool
    {
        $T = $event->startDateTime();
        if (!$T) {
            return false;
        }
        $now = $now ?: Carbon::now();
        $cutoff = $T->copy()->subMinutes(self::PURCHASE_CUTOFF_MINUTES);

        return $now->lessThan($cutoff);
    }

    /**
     * Can the platform allocate (reserve) a brand new ticket at time $now?
     */
    public function canAllocate(Event $event, ?Carbon $now = null): bool
    {
        $result = $this->computeDeadline($event, $now);

        return $result['can_allocate'];
    }

    /**
     * @param  Carbon|null  $allocationTime  Instant t (defaults to now), e.g. buyer payment time.
     * @return array{
     *   deadline: Carbon|null,
     *   can_allocate: bool,
     *   can_transfer: bool,
     *   reference: Carbon,
     *   is_available: bool,
     *   gate_minutes: int
     * }
     */
    public function computeDeadline(Event $event, ?Carbon $allocationTime = null): array
    {
        $t = $allocationTime ?: Carbon::now();
        $T = $event->startDateTime();
        if (!$T) {
            return [
                'deadline' => null,
                'can_allocate' => false,
                'can_transfer' => false,
                'reference' => $t,
                'is_available' => false,
                'gate_minutes' => 0,
            ];
        }

        $h = $event->ticketAvailabilityDateTime();
        $alreadyAvailable = $h->lessThanOrEqualTo($t);

        // Minutes from the comparison reference (t or h) to event start T (signed).
        $ref = $alreadyAvailable ? $t : $h;
        $gateMinutes = $ref->diffInMinutes($T, false);

        $deadline = null;
        $canAllocate = false;
        $canTransfer = false;

        if ($gateMinutes >= self::TRANSFER_CUTOFF_48H_MINUTES) {
            $deadline = $t->copy()
                ->addMinutes(self::TRANSFER_CUTOFF_48H_MINUTES)
                ->min($T->copy()->subMinutes(self::TRANSFER_CUTOFF_12H_MINUTES));
            $canAllocate = true;
            $canTransfer = true;
        } elseif ($gateMinutes >= self::PURCHASE_CUTOFF_MINUTES) {
            $deadline = $alreadyAvailable
                ? $t->copy()->addMinutes(30)
                : $h->copy()->addMinutes(30);
            $canAllocate = true;
            $canTransfer = true;
        } elseif ($gateMinutes >= self::TRANSFER_CUTOFF_12H_MINUTES) {
            $deadline = null;
            $canAllocate = false;
            $canTransfer = true;
        } else {
            $deadline = null;
            $canAllocate = false;
            $canTransfer = false;
        }

        return [
            'deadline' => $deadline,
            'can_allocate' => $canAllocate,
            'can_transfer' => $canTransfer,
            'reference' => $ref,
            'is_available' => $alreadyAvailable,
            'gate_minutes' => $gateMinutes,
        ];
    }
}

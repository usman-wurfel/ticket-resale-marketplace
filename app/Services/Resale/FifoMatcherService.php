<?php

namespace App\Services\Resale;

use App\Models\Customer;
use App\Models\Event\ResaleTicket;

/**
 * FIFO matcher described in the PRD ("FIFO Matching Algorithm").
 *
 *   A ticket may be selected only if:
 *     - its status is "listed" (not sold / not reserved)
 *     - the seller is not the buyer
 *     - it is not in the buyer's exclude-list (e.g. a ticket whose transfer
 *       previously failed for this same buyer)
 *
 *   Deprioritized tickets are kept at the end of the queue. Ordering uses the
 *   seller's current confidence_level (not only the denormalized flag) so FIFO
 *   stays correct when a seller moves back to LOW RISK (internal HIGH) before
 *   cron syncs resale_tickets.deprioritized.
 *
 *   The waiting list key is (event_id, zone, price, entrance_date).
 */
class FifoMatcherService
{
    /**
     * Find the next eligible ticket for the given criteria.
     *
     * @param int         $eventId
     * @param string|null $zone
     * @param float       $price
     * @param int         $buyerCustomerId
     * @param int[]       $excludeTicketIds
     * @param string|null $entranceDate Y-m-d
     */
    public function findNext(
        int $eventId,
        ?string $zone,
        float $price,
        int $buyerCustomerId,
        array $excludeTicketIds = [],
        ?string $entranceDate = null,
        bool $exactPrice = true
    ): ?ResaleTicket {
        $baseQuery = ResaleTicket::query()
            ->select('resale_tickets.*')
            ->join('customers', 'customers.id', '=', 'resale_tickets.seller_customer_id')
            ->where('resale_tickets.event_id', $eventId)
            ->where('resale_tickets.status', ResaleTicket::STATUS_LISTED)
            // Buy UI groups by exact price; reassignment (findEquivalent) may use <=.
            ->when($exactPrice, function ($q) use ($price) {
                $q->where('resale_tickets.price', $price);
            }, function ($q) use ($price) {
                $q->where('resale_tickets.price', '<=', $price);
            })
            ->where('resale_tickets.seller_customer_id', '!=', $buyerCustomerId)
            ->when($zone !== null, function ($q) use ($zone) {
                $q->where('resale_tickets.zone', $zone);
            })
            ->when($entranceDate !== null && $entranceDate !== '', function ($q) use ($entranceDate) {
                $q->whereDate('resale_tickets.entrance_date', $entranceDate);
            })
            ->when(!empty($excludeTicketIds), function ($q) use ($excludeTicketIds) {
                $q->whereNotIn('resale_tickets.id', $excludeTicketIds);
            })
            ->where('resale_tickets.quantity', '>', 0)
            ->orderByRaw(
                'CASE WHEN customers.confidence_level IN (?, ?) THEN 1 ELSE 0 END ASC',
                [Customer::CONFIDENCE_MEDIUM, Customer::CONFIDENCE_LOW]
            )
            ->orderBy('resale_tickets.deprioritized', 'asc')
            ->orderBy('resale_tickets.created_at', 'asc')
            ->orderBy('resale_tickets.id', 'asc');

        return (clone $baseQuery)->first();
    }

    /**
     * Equivalent-ticket search used when we need to re-assign a buyer after
     * a seller missed their transfer deadline. Same event, same zone, price
     * less than or equal to the original, and never the same ticket as the
     * one that already failed for this buyer.
     */
    public function findEquivalent(ResaleTicket $previous, int $buyerCustomerId): ?ResaleTicket
    {
        $exclude = array_filter([$previous->id, $previous->parent_id]);
        $entranceDate = $previous->entrance_date
            ? \Carbon\Carbon::parse($previous->entrance_date)->format('Y-m-d')
            : null;

        return $this->findNext(
            (int) $previous->event_id,
            $previous->zone,
            (float) $previous->price,
            $buyerCustomerId,
            $exclude,
            $entranceDate,
            false // equivalent reassignment: price <= original
        );
    }
}

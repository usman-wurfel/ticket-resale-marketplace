<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResaleNotification extends Model
{
    protected $table = 'resale_notifications';

    protected $fillable = [
        'customer_id',
        'type',
        'title',
        'body',
        'resale_ticket_id',
        'support_ticket_id',
        'is_read',
    ];

    protected $casts = [
        'is_read' => 'boolean',
    ];

    // Canonical notification types used across the resale flow.
    public const TYPE_RESERVATION_PLACED = 'reservation_placed';
    public const TYPE_PAYMENT_CONFIRMED = 'payment_confirmed';
    public const TYPE_TRANSFER_REQUESTED = 'transfer_requested';
    public const TYPE_TRANSFER_RECEIVED = 'transfer_received';
    public const TYPE_TRANSFER_FAILED = 'transfer_failed';
    public const TYPE_PAYMENT_RELEASED = 'payment_released';
    public const TYPE_LITIGATION_OPENED = 'litigation_opened';
    public const TYPE_LITIGATION_RESOLVED = 'litigation_resolved';
    public const TYPE_RESERVATION_EXPIRED = 'reservation_expired';
    public const TYPE_CONFIDENCE_CHANGED = 'confidence_changed';
    /** Equivalent ticket exists after a missed transfer: buyer can accept or decline. */
    public const TYPE_REASSIGNMENT_OFFERED = 'reassignment_offered';
    /** Equivalent ticket NOT available: buyer must find one themselves. */
    public const TYPE_REASSIGNMENT_UNAVAILABLE = 'reassignment_unavailable';

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }
}

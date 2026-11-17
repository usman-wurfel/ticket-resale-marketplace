<?php

namespace App\Models\Event;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ResaleTicket extends Model
{
    use HasFactory;

    public const STATUS_LISTED = 'listed';
    public const STATUS_RESERVED = 'reserved';
    public const STATUS_PAID = 'paid';
    public const STATUS_TRANSFERRED = 'transferred';
    public const STATUS_SOLD = 'sold';
    public const STATUS_CANCELLED = 'cancelled';

    public const TRANSFER_PENDING = 'pending';
    public const TRANSFER_DONE = 'transferred';
    public const TRANSFER_MISSED = 'missed_deadline';
    public const TRANSFER_CANCELLED = 'cancelled';

    protected $fillable = [
        'booking_id',
        'event_id',
        'zone',
        'entrance_date',
        'deprioritized',
        'deprioritized_locked',
        'seller_customer_id',
        'buyer_customer_id',
        'buyer_external_account',
        'last_buyer_customer_id',
        'price',
        'quantity',
        'original_quantity',
        'parent_id',
        'previous_ticket_id',
        'status',
        'reserved_at',
        'reserved_until',
        'payment_deadline',
        'paid_at',
        'transfer_status',
        'transfer_deadline',
        'missed_deadline_count',
        'transferred_at',
        'payment_release_due_at',
        'payment_released_at',
        'notes',
        'ticket_pdf',
        'ticket_pdfs',
    ];

    protected $casts = [
        'deprioritized' => 'boolean',
        'deprioritized_locked' => 'boolean',
        'price' => 'decimal:2',
        'entrance_date' => 'date',
        'reserved_at' => 'datetime',
        'reserved_until' => 'datetime',
        'payment_deadline' => 'datetime',
        'paid_at' => 'datetime',
        'transfer_deadline' => 'datetime',
        'transferred_at' => 'datetime',
        'payment_release_due_at' => 'datetime',
        'payment_released_at' => 'datetime',
    ];

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function seller()
    {
        return $this->belongsTo(Customer::class, 'seller_customer_id');
    }

    public function buyer()
    {
        return $this->belongsTo(Customer::class, 'buyer_customer_id');
    }

    public function event()
    {
        return $this->belongsTo(\App\Models\Event::class, 'event_id', 'id');
    }

    public function eventContent()
    {
        return $this->belongsTo(EventContent::class, 'event_id', 'event_id');
    }

    public function transaction()
    {
        return $this->hasOne(\App\Models\Transaction::class, 'resale_ticket_id');
    }

    public function litigations()
    {
        return $this->hasMany(\App\Models\SupportTicket::class, 'resale_ticket_id')
            ->where('is_litigation', true);
    }
}

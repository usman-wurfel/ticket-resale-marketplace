<?php

namespace App\Models;

use App\Models\Event\Booking;
use App\Models\ShopManagement\ProductOrder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'transcation_id',
        'booking_id',
        'resale_ticket_id',
        'transcation_type',
        'customer_id',
        'organizer_id',
        'payment_status',
        'status',
        'payment_deadline',
        'paid_at',
        'released_at',
        'refunded_at',
        'context',
        'payment_method',
        'grand_total',
        'pre_balance',
        'after_balance',
        'commission',
        'tax',
        'gateway_type',
        'currency_symbol',
        'currency_symbol_position'
    ];

    protected $casts = [
        'payment_deadline' => 'datetime',
        'paid_at' => 'datetime',
        'released_at' => 'datetime',
        'refunded_at' => 'datetime',
    ];

    // Transaction type codes (legacy: 1=event, 2=product, 3=withdraw,
    // 4=balance add, 5=balance subtract, 6=resale offline).
    public const TYPE_RESALE_ESCROW = 10;   // buyer funds held in escrow_balance
    public const TYPE_RESALE_RELEASE = 11;  // platform → seller withdrawable
    public const TYPE_RESALE_REFUND = 12;   // escrow → buyer platform wallet
    public const TYPE_BALANCE_DISCOUNT = 13;// platform_balance used as discount
    public const TYPE_WITHDRAWAL = 14;      // withdrawal payout
    public const TYPE_RESALE_ESCROW_SETTLE = 15; // buyer escrow released (sale completed / settled)

    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_RELEASED = 'released';
    public const STATUS_REFUNDED = 'refunded';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_FAILED = 'failed';
    //method
    public function method()
    {
        return $this->belongsTo(WithdrawPaymentMethod::class, 'payment_method', 'id');
    }

    //room_booking 
    public function product_order()
    {
        return $this->belongsTo(ProductOrder::class, 'booking_id', 'id');
    }
    //event_booking 
    public function event_booking()
    {
        return $this->belongsTo(Booking::class, 'booking_id', 'id');
    }

    public function organizer()
    {
        return $this->belongsTo(Organizer::class);
    }

    public function resaleTicket()
    {
        return $this->belongsTo(\App\Models\Event\ResaleTicket::class, 'resale_ticket_id');
    }

    public function customer()
    {
        return $this->belongsTo(\App\Models\Customer::class, 'customer_id');
    }
}

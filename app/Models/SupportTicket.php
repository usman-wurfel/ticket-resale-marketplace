<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupportTicket extends Model
{
  use HasFactory;
  protected $fillable = [
    'user_id',
    'user_type',
    'admin_id',
    'email',
    'subject',
    'description',
    'attachment',
    'status',
    'last_message',
    'source_type',
    'source_id',
    'event_id',
    'booking_ref',
    'counterparty_user_type',
    'counterparty_user_id',
    'counterparty_name',
    'resale_ticket_id',
    'is_litigation',
    'litigation_status',
    'outcome',
    'blame_user_type',
    'blame_user_id',
    'resolved_at',
    'resolver_admin_id',
    'settlement_mode',
    'reimbursement_type',
    'reimbursement_amount',
    'release_to_seller',
  ];

  protected $casts = [
    'is_litigation' => 'boolean',
    'resolved_at' => 'datetime',
    'reimbursement_amount' => 'decimal:2',
    'release_to_seller' => 'boolean',
  ];

  public const LITIGATION_OPEN = 'open';
  public const LITIGATION_RESOLVED = 'resolved';

  public const OUTCOME_SELLER = 'seller';
  public const OUTCOME_BUYER = 'buyer';
  public const OUTCOME_UNCLEAR = 'unclear';
  /** Closed as not a real incident / opened by mistake — no blame, no confidence impact. */
  public const OUTCOME_NONE = 'none';

  public const STATUS_PENDING = 1;
  public const STATUS_OPEN = 2;
  public const STATUS_CLOSED = 3;
  public const SETTLEMENT_TIMELINE = 'timeline';
  public const SETTLEMENT_MANUAL = 'manual';

  // Reimbursement decision when settling manually.
  public const REIMBURSE_NONE = 'none';
  public const REIMBURSE_PARTIAL = 'partial';
  public const REIMBURSE_FULL = 'full';
  public function customer()
  {
    return $this->belongsTo(Customer::class, 'user_id', 'id');
  }
  public function organizer()
  {
    return $this->belongsTo(Organizer::class, 'user_id', 'id');
  }
  public function counterpartyCustomer()
  {
    return $this->belongsTo(Customer::class, 'counterparty_user_id', 'id');
  }
  public function messages()
  {
    return $this->hasMany(Conversation::class);
  }
  public function resaleTicket()
  {
    return $this->belongsTo(\App\Models\Event\ResaleTicket::class, 'resale_ticket_id');
  }
  public function blameUser()
  {
    return $this->belongsTo(Customer::class, 'blame_user_id', 'id');
  }
}

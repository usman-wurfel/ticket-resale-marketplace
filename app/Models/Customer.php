<?php

namespace App\Models;

use App\Models\Event\Booking;
use App\Models\Event\Wishlist;
use App\Models\ShopManagement\OrderItem;
use App\Models\ShopManagement\ProductOrder;
use App\Models\ShopManagement\ProductReview;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Laravel\Sanctum\HasApiTokens;


class Customer extends Model implements AuthenticatableContract
{
  use HasApiTokens, HasFactory, Authenticatable;
  protected $fillable = [
    'provider',
    'provider_id',
    'fname',
    'lname',
    'username',
    'email',
    'photo',
    'phone',
    'address',
    'country',
    'state',
    'city',
    'zip_code',
    'password',
    'gender',
    'national_id_number',
    'status',
    'email_verified_at',
    'verification_token',
    'platform_balance',
    'escrow_balance',
    'withdrawable_balance',
    'confidence_level',
    'confidence_score',
    'confidence_updated_at',
    'successful_transactions_count',
    'litigations_responsible_count',
    'manual_reinstatement_count',
    'litigations_at_reinstatement',
  ];

  protected $hidden = [
    'password',
    'remember_token',
    'two_factor_recovery_codes',
    'two_factor_secret',
  ];

  protected $casts = [
    'email_verified_at' => 'datetime',
    'confidence_updated_at' => 'datetime',
    'platform_balance' => 'decimal:2',
    'escrow_balance' => 'decimal:2',
    'withdrawable_balance' => 'decimal:2',
    'confidence_score' => 'decimal:4',
    'successful_transactions_count' => 'integer',
    'litigations_responsible_count' => 'integer',
    'manual_reinstatement_count' => 'integer',
    'litigations_at_reinstatement' => 'integer',
  ];

  /** Internal tier: best track record (maps to "LOW RISK" in UI). */
  public const CONFIDENCE_HIGH = 'HIGH';
  public const CONFIDENCE_MEDIUM = 'MEDIUM';
  /** Internal tier: worst non-banned track record (maps to "HIGH RISK" in UI). */
  public const CONFIDENCE_LOW = 'LOW';
  public const CONFIDENCE_BANNED = 'BANNED';

  public function isBanned(): bool
  {
    return $this->confidence_level === self::CONFIDENCE_BANNED;
  }

  /**
   * Human-readable resale risk label (inverse wording vs internal HIGH/LOW codes).
   */
  public function resaleRiskLabelForDisplay(): string
  {
    return match ($this->confidence_level) {
      self::CONFIDENCE_HIGH => 'LOW RISK',
      self::CONFIDENCE_MEDIUM => 'MEDIUM RISK',
      self::CONFIDENCE_LOW => 'HIGH RISK',
      self::CONFIDENCE_BANNED => 'BANNED',
      default => (string) $this->confidence_level,
    };
  }

  public function hasStrictResaleSellerRules(): bool
  {
    return $this->confidence_level === self::CONFIDENCE_LOW;
  }

  public function isDeprioritized(): bool
  {
    return in_array($this->confidence_level, [self::CONFIDENCE_MEDIUM, self::CONFIDENCE_LOW], true);
  }

  //bookings
  public function bookings()
  {
    return $this->hasMany(Booking::class);
  }
  //order_items
  public function order_items()
  {
    return $this->hasMany(OrderItem::class, 'user_id', 'id');
  }
  //product_orders
  public function product_orders()
  {
    return $this->hasMany(ProductOrder::class, 'user_id', 'id');
  }
  //product_reviews
  public function product_reviews()
  {
    return $this->hasMany(ProductReview::class, 'user_id', 'id');
  }
  //support_tickets
  public function support_tickets()
  {
    return $this->hasMany(SupportTicket::class, 'user_id', 'id');
  }
  //wishlists
  public function wishlists()
  {
    return $this->hasMany(Wishlist::class, 'customer_id', 'id');
  }
}

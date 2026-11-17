<?php

namespace App\Models;

use App\Models\Event\Booking;
use App\Models\Event\EventContent;
use App\Models\Event\EventDates;
use App\Models\Event\EventImage;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Event\Ticket;
use App\Models\Event\Wishlist;

class Event extends Model
{
  use HasFactory;

  protected $casts = [
    'start_date_time' => 'datetime',
    'end_date_time' => 'datetime',
    'ticket_availability_datetime' => 'datetime',
    'allow_custom_zone' => 'boolean',
  ];

  /**
   * Canonical event start datetime (T). If the dedicated column is not set
   * yet, fall back to start_date + start_time.
   */
  public function startDateTime(): ?\Carbon\Carbon
  {
    if (!empty($this->attributes['start_date_time'])) {
      try {
        return \Carbon\Carbon::parse($this->attributes['start_date_time']);
      } catch (\Throwable $e) {
        // fall through
      }
    }
    if (!empty($this->start_date)) {
      try {
        $time = !empty($this->start_time) ? $this->start_time : '00:00';
        return \Carbon\Carbon::parse($this->start_date . ' ' . $time);
      } catch (\Throwable $e) {
      }
    }
    return null;
  }

  /**
   * When the tickets/QR codes become transferable. Defaults to "already
   * available" (now) if no value is provided.
   */
  public function ticketAvailabilityDateTime(): \Carbon\Carbon
  {
    if (!empty($this->attributes['ticket_availability_datetime'])) {
      try {
        return \Carbon\Carbon::parse($this->attributes['ticket_availability_datetime']);
      } catch (\Throwable $e) {
      }
    }
    // PRD: if already available, transfer can happen immediately.
    return \Carbon\Carbon::now()->subSecond();
  }

  /**
   * List of calendar days a ticket may grant entrance for.
   * Multi-day span (start→end) or each row in event_dates when date_type=multiple.
   *
   * @return array<int,string> Y-m-d dates
   */
  public function entranceDates(): array
  {
    $dates = [];

    if (($this->date_type ?? 'single') === 'multiple') {
      $rows = $this->relationLoaded('dates')
        ? $this->dates
        : $this->dates()->orderBy('start_date')->get();
      foreach ($rows as $row) {
        if (!empty($row->start_date)) {
          try {
            $dates[] = \Carbon\Carbon::parse($row->start_date)->format('Y-m-d');
          } catch (\Throwable $e) {
          }
        }
      }
    }

    if (empty($dates) && !empty($this->start_date)) {
      try {
        $start = \Carbon\Carbon::parse($this->start_date)->startOfDay();
        $end = !empty($this->end_date)
          ? \Carbon\Carbon::parse($this->end_date)->startOfDay()
          : $start->copy();
        if ($end->lt($start)) {
          $end = $start->copy();
        }
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
          $dates[] = $d->format('Y-m-d');
        }
      } catch (\Throwable $e) {
      }
    }

    return array_values(array_unique($dates));
  }

  public function isMultiDay(): bool
  {
    return count($this->entranceDates()) > 1;
  }

  protected $fillable = [
    'organizer_id',
    'thumbnail',
    'status',
    'countdown_status',
    'date_type',
    'start_date',
    'start_time',
    'duration',
    'end_date',
    'end_time',
    'end_date_time',
    'start_date_time',
    'ticket_availability_datetime',
    'is_featured',
    'event_type',
    'event_ticket_transfer_type',
    'external_platform_name',
    'latitude',
    'longitude',
    'location_id',
    'allow_custom_zone',
    'ticket_image',
    'instructions',
    'meeting_url',
    'ticket_logo',
    'ticket_slot_image'
  ];
  public function ticket()
  {
    return $this->hasOne(Ticket::class);
  }
  public function tickets()
  {
    return $this->hasMany(Ticket::class);
  }
  //information
  public function information()
  {
    return $this->hasOne(EventContent::class);
  }
  //bookings
  public function booking()
  {
    return $this->hasMany(Booking::class);
  }

  //wishtlist
  public function wishlists()
  {
    return $this->hasMany(Wishlist::class, 'event_id', 'id');
  }

  public function organizer()
  {
    return $this->belongsTo(Organizer::class);
  }

  public function galleries()
  {
    return $this->hasMany(EventImage::class);
  }

  public function dates()
  {
    return $this->hasMany(EventDates::class);
  }

  public function location()
  {
    return $this->belongsTo(\App\Models\Event\EventLocation::class, 'location_id');
  }
}

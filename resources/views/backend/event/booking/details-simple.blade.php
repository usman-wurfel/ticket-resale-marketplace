@extends('backend.layout')

@section('content')
  @php
    $eventInfos = \App\Models\Event\EventContent::where('language_id', $defaultLang->id)
        ->where('event_id', $booking->event_id)
        ->select('slug', 'event_id', 'title')
        ->first();
    if (empty($eventInfos)) {
        $eventInfos = \App\Models\Event\EventContent::where('event_id', $booking->event_id)
            ->select('slug', 'event_id', 'title')
            ->first();
    }
    $customer = $booking->customerInfo()->first();
    $paymentStatus = strtolower(trim((string) $booking->paymentStatus));
    $isOnHold = in_array($paymentStatus, ['reserved', 'pending', 'on_hold'], true);
    $isReserved = in_array($paymentStatus, ['completed', '1'], true) || $booking->paymentStatus === 1;
  @endphp

  <div class="page-header">
    <h4 class="page-title">{{ __('Booking Details') }}</h4>
    <ul class="breadcrumbs">
      <li class="nav-home"><a href="{{ route('admin.dashboard') }}"><i class="flaticon-home"></i></a></li>
      <li class="separator"><i class="flaticon-right-arrow"></i></li>
      <li class="nav-item"><a href="#">{{ __('Ticket Booking') }}</a></li>
      <li class="separator"><i class="flaticon-right-arrow"></i></li>
      <li class="nav-item"><a href="#">{{ __('Booking Details') }}</a></li>
    </ul>
  </div>

  <div class="row">
    <div class="col-md-8">
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <div class="card-title mb-0">{{ __('Booking') }} #{{ $booking->booking_id }}</div>
          <a class="btn btn-info btn-sm" href="{{ url()->previous() }}">
            <span class="btn-label"><i class="fas fa-backward"></i></span>{{ __('Back') }}
          </a>
        </div>
        <div class="card-body">
          <div class="row mb-2">
            <div class="col-lg-4"><strong>{{ __('Event') }}:</strong></div>
            <div class="col-lg-8">
              @if (!empty($eventInfos))
                <a href="{{ route('event.details', ['slug' => $eventInfos->slug, 'id' => $eventInfos->event_id]) }}" target="_blank">
                  {{ $eventInfos->title }}
                </a>
              @else
                -
              @endif
            </div>
          </div>
          <div class="row mb-2">
            <div class="col-lg-4"><strong>{{ __('Booking Date') }}:</strong></div>
            <div class="col-lg-8">{{ FullDateTime($booking->created_at) }}</div>
          </div>
          <div class="row mb-2">
            <div class="col-lg-4"><strong>{{ __('Event Date') }}:</strong></div>
            <div class="col-lg-8">{{ $booking->event_date ?: '-' }}</div>
          </div>
          <div class="row mb-2">
            <div class="col-lg-4"><strong>{{ __('Quantity') }}:</strong></div>
            <div class="col-lg-8">{{ $booking->quantity ?? '-' }}</div>
          </div>
          <div class="row mb-2">
            <div class="col-lg-4"><strong>{{ __('Customer Paid') }}:</strong></div>
            <div class="col-lg-8">
              {{ ($booking->currencyTextPosition == 'left' ? $booking->currencySymbol . ' ' : '') . ($booking->price + $booking->tax) . ($booking->currencyTextPosition == 'right' ? ' ' . $booking->currencySymbol : '') }}
            </div>
          </div>
          <div class="row mb-2">
            <div class="col-lg-4"><strong>{{ __('Paid via') }}:</strong></div>
            <div class="col-lg-8">
              @if (!is_null($booking->paymentMethod))
                {{ $booking->paymentMethod === 'pay_at_event' ? 'Online booking' : $booking->paymentMethod }}
              @else
                -
              @endif
            </div>
          </div>
          <div class="row mb-2">
            <div class="col-lg-4"><strong>{{ __('Payment Status') }}:</strong></div>
            <div class="col-lg-8">
              @if ($isReserved)
                <span class="badge badge-success">{{ __('Reserved') }}</span>
              @elseif ($isOnHold)
                <span class="badge badge-warning">{{ __('On hold') }}</span>
              @elseif ($paymentStatus === 'rejected')
                <span class="badge badge-danger">{{ __('Rejected') }}</span>
              @else
                <span class="badge badge-secondary">{{ ucfirst((string) $booking->paymentStatus) }}</span>
              @endif
            </div>
          </div>
          <div class="row">
            <div class="col-lg-4"><strong>{{ __('Reserved until') }}:</strong></div>
            <div class="col-lg-8">
              {{ reservedUntilRemainingMinutes($booking->reserved_until) }}
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-md-4">
      <div class="card">
        <div class="card-header">
          <div class="card-title mb-0">{{ __('Customer') }}</div>
        </div>
        <div class="card-body">
          <div class="row mb-2">
            <div class="col-4"><strong>{{ __('Name') }}:</strong></div>
            <div class="col-8">{{ $booking->fname . ' ' . $booking->lname }}</div>
          </div>
          <div class="row mb-2">
            <div class="col-4"><strong>{{ __('Email') }}:</strong></div>
            <div class="col-8">{{ $booking->email ?: '-' }}</div>
          </div>
          <div class="row">
            <div class="col-4"><strong>{{ __('Phone') }}:</strong></div>
            <div class="col-8">{{ $booking->phone ?: '-' }}</div>
          </div>
        </div>
      </div>
    </div>

  </div>
@endsection

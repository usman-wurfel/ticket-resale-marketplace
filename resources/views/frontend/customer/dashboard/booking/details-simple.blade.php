@extends('frontend.layout')

@section('pageHeading')
  {{ __('Booking details') }}
@endsection

@section('hero-section')
  <section class="page-banner overlay pt-120 pb-125 rpt-90 rpb-95 lazy"
    data-bg="{{ asset('assets/admin/img/' . $basicInfo->breadcrumb) }}">
    <div class="container">
      <div class="banner-inner">
        <h2 class="page-title">{{ __('Booking details') }}</h2>
        <nav aria-label="breadcrumb">
          <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('index') }}">{{ __('Home') }}</a></li>
            <li class="breadcrumb-item active">{{ __('Booking details') }}</li>
          </ol>
        </nav>
      </div>
    </div>
  </section>
@endsection

@section('content')
  <section class="user-dashbord">
    <div class="container">
      <div class="row">
        @includeIf('frontend.customer.partials.sidebar')
        <div class="col-lg-9">
          <div class="account-info">
            <div class="title d-flex justify-content-between align-items-center flex-wrap">
              <h4>{{ __('Booking details') }}</h4>
              <a href="{{ route('customer.tickets.bought') }}" class="btn btn-sm btn-secondary">{{ __('Back') }}</a>
            </div>
            <div class="main-info">
              @php
                $qty = max(1, (int) $booking->quantity);
                $unitPrice = $qty > 0 ? round((float) $booking->price / $qty, 2) : (float) $booking->price;
                $totalAmount = (float) $booking->price + (float) ($booking->tax ?? 0);
                $event = $booking
                    ->event()
                    ->where('language_id', $currentLanguageInfo->id)
                    ->select('title', 'slug', 'event_id')
                    ->first();
                if (empty($event)) {
                    $language = App\Models\Language::where('is_default', 1)->first();
                    $event = $booking
                        ->event()
                        ->where('language_id', $language->id)
                        ->select('title', 'slug', 'event_id')
                        ->first();
                }
              @endphp

              <ul class="list-unstyled mb-0" style="font-size: 15px; line-height: 1.9;">
                <li><strong>{{ __('Booking ID') }}:</strong> #{{ $booking->booking_id }}</li>
                <li><strong>{{ __('Status') }}:</strong> {{ ucfirst((string) $booking->paymentStatus) }}</li>
                <li><strong>{{ __('Payment method') }}:</strong> {{ __($booking->paymentMethod) }}</li>
                @if (!empty($event))
                  <li>
                    <strong>{{ __('Event') }}:</strong>
                    <a href="{{ route('event.details', [$event->slug, $event->event_id]) }}">{{ $event->title }}</a>
                  </li>
                @endif
                @if ($booking->event_date)
                  <li><strong>{{ __('Event date') }}:</strong>
                    {{ \Carbon\Carbon::parse($booking->event_date)->translatedFormat('D, M d, Y h:i a') }}</li>
                @endif
                <li><strong>{{ __('Booked on') }}:</strong>
                  {{ \Carbon\Carbon::parse($booking->created_at)->translatedFormat('D, M d, Y h:i a') }}</li>
                <li><strong>{{ __('Quantity') }}:</strong> {{ $booking->quantity }}</li>
                <li><strong>{{ __('Price') }} ({{ __('each') }}):</strong> {{ number_format($unitPrice, 2) }}</li>
                <li><strong>{{ __('Total') }}:</strong> {{ number_format($totalAmount, 2) }}</li>
                @if ($booking->reserved_until)
                  <li><strong>{{ __('Reserved until') }}:</strong>
                    {{ reservedUntilRemainingMinutes($booking->reserved_until) }}</li>
                @endif
              </ul>
              <div class="mt-3">
                @include('frontend.customer.partials.btn-action-incident-modern', [
                  'href' => route('customer.support_tickert.create', ['source_type' => 'platform_booking', 'source_id' => $booking->id]),
                  'title' => __('Open incident'),
                ])
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
@endsection

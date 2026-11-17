@extends('frontend.layout')

@php
  $isReservationPending =
      isset($booking) &&
      $booking &&
      (string) $booking->paymentStatus === 'reserved';
@endphp

@section('pageHeading')
  @if ($isReservationPending)
    {{ __('Reservation Success') }}
  @else
    {{ __('Payment Success') }}
  @endif
@endsection

@section('style')
  @unless ($isReservationPending)
    <link rel="stylesheet" href="{{ asset('assets/admin/css/summernote-content.css') }}">
  @endunless
@endsection

@section('hero-section')
  @if ($isReservationPending)
    @include('frontend.customer.partials.reservation-success-hero')
  @else
    <section class="page-banner overlay pt-120 pb-125 rpt-90 rpb-95 lazy"
      data-bg="{{ asset('assets/admin/img/' . $basicInfo->breadcrumb) }}">
      <div class="container">
        <div class="banner-inner">
          <h2 class="page-title">{{ __('Payment Success') }}</h2>
          <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
              <li class="breadcrumb-item"><a href="{{ route('index') }}">{{ __('Home') }}</a></li>
              <li class="breadcrumb-item active">{{ __('Payment Success') }}</li>
            </ol>
          </nav>
        </div>
      </div>
    </section>
  @endif
@endsection

@section('content')
  @if ($isReservationPending)
    <div class="booking-message">
      <div class="container">
        <div class="row">
          <div class="col-lg-12">
            <div class="booking-success">
              @include('frontend.customer.partials.reservation-success-message')
            </div>
          </div>
        </div>
      </div>
    </div>
  @else
    <div class="booking-message">
      <div class="container">
        <div class="row">
          <div class="col-lg-12">
            <div class="booking-success">
              <div class="icon text-success"><i class="far fa-check-circle"></i></div>
              <h2 class="mb-3">{{ __('Success') }}!</h2>
              @if (request()->input('via') == 'offline')
                <p class="mb-1">{{ __('Your Booking Request was Successfully Placed') }}.</p>
                <p>{{ __('Please wait for confirmation') }}.</p>
              @else
                <p class="mb-1">{{ __('Your transaction was successful') }}.</p>
                <p>{{ __('Please wait we will send you a mail with an invoice') . '.' }}</p>
              @endif
              <p class="mb-0">{{ __('Thank you') }}.</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  @endif
@endsection

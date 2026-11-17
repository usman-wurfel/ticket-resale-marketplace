@extends('frontend.layout')

@section('pageHeading')
  {{ __('Reservation Success') }}
@endsection

@section('hero-section')
  @include('frontend.customer.partials.reservation-success-hero')
@endsection

@section('content')
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
@endsection

@extends('frontend.layout')
@section('pageHeading')
{{ $content->title }}
@endsection

@php
$og_title = $content->title;
$og_description = strip_tags($content->description);
$og_image = asset('assets/admin/img/event/thumbnail/' . $content->thumbnail);
@endphp

@section('meta-keywords', "{{ $content->meta_keywords }}")
@section('meta-description', "$content->meta_description")
@section('og-title', "$og_title")
@section('og-description', "$og_description")
@section('og-image', "$og_image")

@section('custom-style')
<link rel="stylesheet" href="{{ asset('assets/admin/css/summernote-content.css') }}">
<link rel="stylesheet" href="{{ asset('assets/front/css/slot.css') }}">
@endsection

@section('hero-section')
<!-- Page Banner Start -->
<section class="page-banner overlay pt-120 pb-125 rpt-90 rpb-95 lazy"
    data-bg="{{ asset('assets/admin/img/' . $basicInfo->breadcrumb) }}">
    <div class="container">
        <div class="banner-inner">
            <h2 class="page-title">
                {{ strlen($content->title) > 30 ? mb_substr($content->title, 0, 30, 'UTF-8') . '...' : $content->title
                }}
            </h2>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('index') }}">{{ __('Home') }}</a></li>
                    <li class="breadcrumb-item active">
                        @if (!empty($pageHeading))
                        {{ $pageHeading->event_details_page_title ?? __('Event Details') }}
                        @else
                        {{ __('Event Details') }}
                        @endif
                    </li>
                </ol>
            </nav>
        </div>
    </div>
</section>
<!-- Page Banner End -->
@endsection
@section('content')
<!-- Event Page Start -->
@php
$map_address = preg_replace('/\s+/u', ' ', trim($content->address));
$map_address = str_replace('/', ' ', $map_address);
$map_address = str_replace('?', ' ', $map_address);
$map_address = str_replace(',', ' ', $map_address);
@endphp
<section class="event-details-section pt-110 rpt-90 pb-90 rpb-70">
    <div class="container">
        <div class="event-details-content">
            <div class="event-top d-flex flex-wrap-wrap has-gap">
                @php
                if ($content->date_type == 'multiple') {
                $event_date = eventLatestDates($content->id);
                $date = strtotime(@$event_date->start_date);
                } else {
                $date = strtotime($content->start_date);
                }
                @endphp
                @if ($content->date_type != 'multiple')
                <div class="event-top-date">
                    <div class="event-month">
                        {{ \Carbon\Carbon::parse($date)->timezone($websiteInfo->timezone)->translatedFormat('M') }}
                    </div>
                    <div class="event-date">
                        {{ \Carbon\Carbon::parse($date)->timezone($websiteInfo->timezone)->translatedFormat('d') }}
                    </div>
                </div>
                @endif
                <div class="event-bottom-content">
                    @php
                    if ($content->date_type == 'multiple') {
                    $event_date = eventLatestDates($content->id);
                    $startDateTime = @$event_date->start_date_time;
                    $endDateTime = @$event_date->end_date_time;
                    //for multiple get last end date
                    $last_end_date = eventLastEndDates($content->id);
                    $last_end_date = $last_end_date->end_date_time;

                    $now_time = \Carbon\Carbon::now()
                    ->timezone($websiteInfo->timezone)
                    ->translatedFormat('Y-m-d H:i:s');
                    } else {
                    $now_time = \Carbon\Carbon::now()
                    ->timezone($websiteInfo->timezone)
                    ->translatedFormat('Y-m-d H:i:s');
                    $startDateTime = $content->start_date . ' ' . $content->start_time;
                    $endDateTime = $content->end_date . ' ' . $content->end_time;
                    }
                    $over = false;

                    @endphp
                    @if ($content->date_type == 'single' && $content->countdown_status == 1)
                    <div class="event-details-top">
                        @if ($startDateTime >= $now_time)
                        <h2 class="title">{{ $content->title }} <span class="badge badge-info">{{ __('Upcoming')
                                }}</span>
                        </h2>
                        @elseif ($startDateTime <= $endDateTime && $endDateTime>= $now_time)
                            <h2 class="title">
                                {{ $content->title }}
                                <span class="badge badge-success">{{ __('Running') }}</span>
                            </h2>
                            @else
                            @php
                            $over = true;
                            @endphp
                            <h2 class="title">
                                {{ $content->title }}
                                <span class="badge badge-danger">{{ __('Over') }}</span>
                            </h2>
                            @endif
                    </div>
                    @elseif ($content->date_type == 'multiple')
                    <div class="event-details-top">
                        <h2 class="title">{{ $content->title }}
                            @if ($startDateTime >= $now_time)
                            <span class="badge badge-info">{{ __('Upcoming') }}</span>
                            @elseif ($startDateTime <= $last_end_date && $last_end_date>= $now_time)
                                <span class="badge badge-success">{{ __('Running') }}</span>
                                @else
                                @php
                                $over = true;
                                @endphp
                                <span class="badge badge-danger">{{ __('Over') }}</span>
                                @endif
                        </h2>
                    </div>
                    @else
                    <div class="event-details-top">
                        <h2 class="title">{{ $content->title }}</h2>
                    </div>

                    @endif

                    <div class="event-details-header mb-25">
                        <ul>
                            <li><i class="far fa-calendar-alt"></i>
                                {{ \Carbon\Carbon::parse($date)->timezone($websiteInfo->timezone)->translatedFormat('D,
                                dS M Y') }}
                            </li>

                            <li><i class="far fa-clock"></i>
                                {{ $content->date_type == 'multiple' ? @$event_date->duration : $content->duration }}
                            </li>
                            @if ($content->event_type == 'venue')
                            <li><i class="fas fa-map-marker-alt"></i>
                                {{ $content->address }}
                            </li>
                            @else
                            <li><i class="fas fa-map-marker-alt"></i> {{ __('Online') }}</li>
                            @endif
                        </ul>
                    </div>
                </div>
            </div>
            <div class="event-details-image mb-50">
                {{-- <div class="event-details-images">
                    @foreach ($images as $item)
                    <div class="slide-item">
                        <a href="{{ asset('assets/admin/img/event-gallery/' . $item->image) }}">
                            <img src="{{ asset('assets/admin/img/event-gallery/' . $item->image) }}"
                                alt="Event Details">
                        </a>
                    </div>
                    @endforeach
                </div> --}}
                <div class="swiper event-details-images">
                    <div class="swiper-wrapper">
                        @foreach ($images as $item)
                        <div class="swiper-slide">
                            <a href="{{ asset('assets/admin/img/event-gallery/' . $item->image) }}">
                                <img src="{{ asset('assets/admin/img/event-gallery/' . $item->image) }}"
                                    alt="Event Details">
                            </a>
                        </div>
                        @endforeach
                    </div>
                    <div class="event-details-slide-prev"><i class="fas fa-chevron-left"></i></div>
                    <div class="event-details-slide-next"><i class="fas fa-chevron-right"></i></div>
                </div>

                <div class="buttons">
                    @if (Auth::guard('customer')->check())
                    @php
                    $customer_id = Auth::guard('customer')->user()->id;
                    $event_id = $content->id;
                    $checkWishList = checkWishList($event_id, $customer_id);
                    @endphp
                    @else
                    @php
                    $checkWishList = false;
                    @endphp
                    @endif
                    @if ($content->event_type != 'online')
                    <a href="javascript:void(0)" data-toggle="modal" data-target=".bd-example-modal-lg">
                        <i class="fas fa-map-marker-alt m-0"></i>
                    </a>
                    @endif
                    <a href="{{ $checkWishList == false ? route('addto.wishlist', $content->id) : route('remove.wishlist', $content->id) }}"
                        class="{{ $checkWishList == true ? 'text-success' : '' }}"><i class="fas fa-bookmark"></i></a>
                    <a href="javascript:void(0)" data-toggle="modal" data-target=".share-event">
                        <i class="fas fa-share-alt"></i></a>
                </div>
            </div>
            <div class="row">
                <div class="col-lg-7">
                    <div class="event-details-content-inner">
                        <div class="event-info d-flex align-items-center mb-1">
                            <span>
                                <a href="{{ route('events', ['category' => $content->slug]) }}">{{ $content->name }}</a>
                            </span>
                        </div>
                        @if (Session::has('paypal_error'))
                        <div class="alert alert-danger">{{ Session::get('paypal_error') }}</div>
                        @endif
                        @php
                        Session::put('paypal_error', null);
                        @endphp
                        <h3 class="inner-title mb-25">{{ __('Description') }}</h3>

                        <div class="summernote-content">
                            {!! $content->description !!}
                        </div>

                        @if ($content->event_type != 'online')
                        <h3 class="inner-title mb-30">{{ __('Map') }}</h3>
                        <div class="our-location mb-50">
                            <iframe
                                src="//maps.google.com/maps?width=100%25&amp;height=385&amp;hl=en&amp;q={{ $map_address }}&amp;t=&amp;z=14&amp;ie=UTF8&amp;iwloc=B&amp;output=embed"
                                height="385" class="map-h" allowfullscreen="" loading="lazy"></iframe>
                        </div>
                        @endif

                        @if (!empty($content->refund_policy))
                        <h3>{{ __('Return Policy') }}</h3>
                        <p>{{ @$content->refund_policy }}</p>
                        @endif

                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="sidebar-sticky">
                        <form action="{{ route('check-out2') }}" method="post" @if ($over==true) onsubmit="return false"
                            @endif>
                            @csrf
                            <input type="hidden" name="event_id" value="{{ $content->id }}" id="">
                            <input type="hidden" name="pricing_type" value="{{ $content->pricing_type }}" id="">
                            <div class="event-details-information">
                                <input type="hidden" name="date_type" value="{{ $content->date_type }}">
                                @if ($content->date_type == 'multiple')
                                @php
                                $dates = eventDates($content->id);
                                $exp_dates = eventExpDates($content->id);
                                @endphp

                                <div class="form-group">
                                    <label for="">{{ __('Select Date') }}</label>
                                    <select name="event_date" id="" class="form-control">
                                        @if (count($dates) > 0)
                                        @foreach ($dates as $date)
                                        <option value="{{ FullDateTime($date->start_date_time) }}">
                                            {{ FullDateTime($date->start_date_time) }}
                                            ({{ timeZoneOffset($websiteInfo->timezone) }}
                                            {{ __('GMT') }})
                                        </option>
                                        @endforeach
                                        @endif
                                        @if (count($exp_dates) > 0)
                                        @foreach ($exp_dates as $exp_date)
                                        <option disabled value="">
                                            {{ FullDateTime($exp_date->start_date_time) }}
                                            ({{ timeZoneOffset($websiteInfo->timezone) }}
                                            {{ __('GMT') }})
                                        </option>
                                        @endforeach
                                        @endif
                                    </select>
                                    @error('event_date')
                                    <p class="text-danger">{{ $message }}</p>
                                    @enderror
                                </div>
                                @else
                                <input type="hidden" name="event_date"
                                    value="{{ FullDateTime($content->start_date . $content->start_time) }}">
                                @endif

                                {{-- Count down start --}}
                                @if ($content->date_type == 'single' && $content->countdown_status == 1)
                                <div class="event-details-top">
                                    @if ($startDateTime >= $now_time)
                                    <b>{{ __('Event Starts In') }}</b>
                                    <hr>
                                    @php
                                    $dt = Carbon\Carbon::parse($startDateTime);
                                    $year = $dt->year;
                                    $month = $dt->month;
                                    $day = $dt->day;
                                    $end_time = Carbon\Carbon::parse($startDateTime);
                                    $hour = $end_time->hour;
                                    $minute = $end_time->minute;
                                    $now = str_replace(
                                    '+00:00',
                                    '.000' . timeZoneOffset($websiteInfo->timezone) . '00:00',
                                    gmdate('c'),
                                    );

                                    @endphp
                                    <div class="count-down mb-3" dir="ltr">
                                        <div class="event-countdown" data-now="{{ $now }}" data-year="{{ $year }}"
                                            data-month="{{ $month }}" data-day="{{ $day }}" data-hour="{{ $hour }}"
                                            data-minute="{{ $minute }}"
                                            data-timezone="{{ timeZoneOffset($websiteInfo->timezone) }}">
                                        </div>
                                    </div>
                                    @elseif ($startDateTime <= $endDateTime && $endDateTime>= $now_time)
                                        <p>{{ __('The Event is Running') }}</p>
                                        @else
                                        <p>{{ __('The Event is Over') }}</p>
                                        @endif
                                </div>
                                @endif

                                {{-- Countdown end --}}
                                <b>Listed on platform</b>
                                <hr>
                                <div class="author">
                                    <img class="lazy" data-src="{{ asset('assets/front/images/user.png') }}"
                                        alt="Platform">
                                    <div class="content">
                                        <h6>Anonymous seller marketplace</h6>
                                        <small>Events are created by platform. Sellers are regular users.</small>
                                    </div>
                                </div>
                                @if ($content->address != null)
                                <b><i class="fas fa-map-marker-alt"></i> {{ $content->address }}</b>
                           
                                @endif

                                <input type="hidden" id="seatIds" name="seatIds">
                                <input type="hidden" id="seatData" name="seatData">
                                <input type="hidden" id="seatPrice" name="seat_price">


                                @if ($content->event_type == 'online' && $content->pricing_type == 'normal')

                                @php
                                $ticket = App\Models\Event\Ticket::where('event_id', $content->id)->first();
                                $event_count = App\Models\Event\Ticket::where('event_id', $content->id)
                                ->get()
                                ->count();
                                if ($ticket->ticket_available_type == 'limited') {
                                $stock = $ticket->ticket_available;
                                } else {
                                $stock = 'unlimited';
                                }
                                //ticket purchase or not check
                                if (
                                Auth::guard('customer')->user() &&
                                $ticket->max_ticket_buy_type == 'limited'
                                ) {
                                $purchase = isTicketPurchaseOnline(
                                $ticket->event_id,
                                $ticket->max_buy_ticket,
                                );
                                } else {
                                $purchase = ['status' => 'false', 'p_qty' => 0];
                                }
                                @endphp
                                @if ($ticket)

                                <b>{{ __('Select Tickets') }}</b>
                                <hr>
                                <div class="price-count">
                                    <h6 dir="ltr">

                                        @if ($ticket->early_bird_discount == 'enable')
                                        @php
                                        $discount_date = Carbon\Carbon::parse(
                                        $ticket->early_bird_discount_date .
                                        $ticket->early_bird_discount_time,
                                        );
                                        @endphp

                                        @if ($ticket->early_bird_discount_type == 'fixed' && !$discount_date->isPast())
                                        @php
                                        $calculate_price =
                                        $ticket->price -
                                        $ticket->early_bird_discount_amount;
                                        @endphp
                                        {{ symbolPrice($calculate_price) }}
                                        <del>
                                            {{ symbolPrice($ticket->price) }}
                                        </del>
                                        @elseif ($ticket->early_bird_discount_type == 'percentage' &&
                                        !$discount_date->isPast())
                                        @php
                                        $c_price =
                                        ($ticket->price *
                                        $ticket->early_bird_discount_amount) /
                                        100;
                                        $calculate_price = $ticket->price - $c_price;
                                        @endphp
                                        {{ symbolPrice($calculate_price) }}
                                        <del>
                                            {{ symbolPrice($ticket->price) }}
                                        </del>
                                        @else
                                        @php
                                        $calculate_price = $ticket->price;
                                        @endphp
                                        {{ symbolPrice($calculate_price) }}
                                        @endif
                                        @else
                                        @php
                                        $calculate_price = $ticket->price;
                                        @endphp
                                        {{ symbolPrice($calculate_price) }}
                                        @endif


                                    </h6>
                                    <div class="quantity-input">
                                        <button class="quantity-down" type="button" id="quantityDown">
                                            -
                                        </button>
                                        <input class="quantity" type="number" readonly value="0"
                                            data-price="{{ $calculate_price }}"
                                            data-max_buy_ticket="{{ $ticket->max_buy_ticket }}" name="quantity"
                                            data-ticket_id="{{ $ticket->id }}" data-stock="{{ $stock }}"
                                            data-purchase="{{ $purchase['status'] }}"
                                            data-p_qty="{{ $purchase['p_qty'] }}">
                                        <button class="quantity-up" type="button" id="quantityUP">
                                            +
                                        </button>
                                    </div>



                                    @if ($ticket->early_bird_discount == 'enable')
                                    @php
                                    $discount_date = Carbon\Carbon::parse(
                                    $ticket->early_bird_discount_date .
                                    $ticket->early_bird_discount_time,
                                    );
                                    @endphp
                                    @if (!$discount_date->isPast())
                                    <p>{{ __('Discount available') . ' ' }} :
                                        ({{ __('till') . ' ' }} :
                                        <span dir="ltr">{{
                                            \Carbon\Carbon::parse($discount_date)->timezone($websiteInfo->timezone)->translatedFormat('Y/m/d
                                            h:i a') }}</span>)
                                    </p>
                                    @endif
                                    @endif


                                </div>
                                <p
                                    class="text-warning max_error_{{ $ticket->id }}{{ $ticket->max_ticket_buy_type == 'limited' ? $ticket->max_buy_ticket : '' }} ">
                                </p>

                                @endif
                                @elseif($content->event_type == 'online' && $content->pricing_type == 'free')
                                <b>{{ __('Select Tickets') }}</b>
                                <hr>
                                @php
                                $ticket = App\Models\Event\Ticket::where('event_id', $content->id)->first();
                                $event_count = App\Models\Event\Ticket::where('event_id', $content->id)
                                ->get()
                                ->count();

                                if ($ticket->ticket_available_type == 'limited') {
                                $stock = $ticket->ticket_available;
                                } else {
                                $stock = 'unlimited';
                                }

                                //ticket purchase or not check
                                if (
                                Auth::guard('customer')->user() &&
                                $ticket->max_ticket_buy_type == 'limited'
                                ) {
                                $purchase = isTicketPurchaseOnline(
                                $ticket->event_id,
                                $ticket->max_buy_ticket,
                                );
                                $max_buy_ticket = $ticket->max_buy_ticket;
                                } else {
                                $purchase = ['status' => 'false', 'p_qty' => 0];
                                $max_buy_ticket = 999999;
                                }
                                @endphp
                                <div class="price-count">
                                    <h6>
                                        {{ __('Free') }}
                                    </h6>
                                    <div class="quantity-input">
                                        <button class="quantity-down" type="button" id="quantityDown">
                                            -
                                        </button>
                                        <input class="quantity" readonly type="number" value="0"
                                            data-price="{{ $content->price }}"
                                            data-max_buy_ticket="{{ $max_buy_ticket }}" name="quantity"
                                            data-ticket_id="{{ $ticket->id }}" data-stock="{{ $stock }}"
                                            data-purchase="{{ $purchase['status'] }}"
                                            data-p_qty="{{ $purchase['p_qty'] }}">
                                        <button class="quantity-up" type="button" id="quantityUP">
                                            +
                                        </button>
                                    </div>

                                </div>
                                <p
                                    class="text-warning max_error_{{ $ticket->id }}{{ $ticket->max_ticket_buy_type == 'limited' ? $ticket->max_buy_ticket : '' }} ">
                                </p>
                                @elseif($content->event_type == 'venue')
                                @php
                                $tickets = DB::table('tickets')
                                ->where('event_id', $content->id)
                                ->orderBy('id')
                                ->take(1)
                                ->get();
                                @endphp
                                @if (false && count($tickets) > 0)
                                <b>{{ __('Select Tickets') }}</b>
                                <hr>
                                @foreach ($tickets as $ticket)
                                @if ($ticket->pricing_type == 'normal')
                                @php
                                if ($ticket->ticket_available_type == 'limited') {
                                $stock = $ticket->ticket_available;
                                } else {
                                $stock = 'unlimited';
                                }

                                //ticket purchase or not check
                                $ticket_content = App\Models\Event\TicketContent::where([
                                ['language_id', $currentLanguageInfo->id],
                                ['ticket_id', $ticket->id],
                                ])->first();

                                if (
                                Auth::guard('customer')->user() &&
                                $ticket->max_ticket_buy_type == 'limited'
                                ) {
                                $purchase = isTicketPurchaseVenue(
                                $ticket->event_id,
                                $ticket->max_buy_ticket,
                                $ticket->id,
                                @$ticket_content->title,
                                );
                                } else {
                                $purchase = ['status' => 'false', 'p_qty' => 0];
                                }

                                @endphp
                                <p class="mb-0"><strong>Platform ticket</strong> <span class="badge badge-info">Stock:
                                        {{ $stock }}</span></p>
                                <div class="click-show">
                                    <div class="show-content">
                                        {!! @$ticket_content->description !!}
                                    </div>
                                    @if (strlen(@$ticket_content->description) > 50)
                                    <div class="read-more-btn">
                                        <span>{{ __('Read more') }}</span>
                                        <span>{{ __('Read less') }}</span>
                                    </div>
                                    @endif
                                </div>
                                <div class="price-count">
                                    <h6 dir="ltr">
                                        <!------- slot button-------->
                                        @if (isset($ticket) && $ticket->normal_ticket_slot_enable == 1)
                                        @php
                                        $slotItem = app(
                                        \App\Services\BookingServices::class,
                                        )->showSlot(
                                        $event_id,
                                        $ticket->id,
                                        $ticket->normal_ticket_slot_unique_id,
                                        );
                                        $slotPrice = $slotItem['price'];
                                        @endphp
                                        @if ($slotItem['available_seat'] == true)
                                        @if ($ticket->early_bird_discount == 'enable')
                                        @php
                                        $discount_date = Carbon\Carbon::parse(
                                        $ticket->early_bird_discount_date .
                                        $ticket->early_bird_discount_time,
                                        );
                                        @endphp
                                        @if ($ticket->early_bird_discount_type == 'fixed' && !$discount_date->isPast())
                                        @php
                                        $calculate_price =
                                        $slotPrice -
                                        $ticket->early_bird_discount_amount;
                                        @endphp
                                        {{ symbolPrice($calculate_price) }}
                                        <del>

                                            {{ symbolPrice($slotPrice) }}
                                        </del>
                                        @elseif ($ticket->early_bird_discount_type == 'percentage' &&
                                        !$discount_date->isPast())
                                        @php
                                        $c_price =
                                        ($slotPrice *
                                        $ticket->early_bird_discount_amount) /
                                        100;
                                        $calculate_price =
                                        $slotPrice - $c_price;
                                        @endphp
                                        {{ symbolPrice($calculate_price) }}

                                        <del>
                                            {{ symbolPrice($slotPrice) }}
                                        </del>
                                        @else
                                        @php
                                        $calculate_price = $slotPrice;
                                        @endphp
                                        {{ symbolPrice($calculate_price) }}
                                        @endif
                                        @else
                                        @php
                                        $calculate_price = $slotPrice;
                                        @endphp
                                        {{ symbolPrice($calculate_price) }}
                                        @endif
                                        @else
                                        @php
                                        $is_slot = App\Models\Event\Slot::where([
                                        'slot_unique_id' =>
                                        $ticket->normal_ticket_slot_unique_id,
                                        'event_id' => $event_id,
                                        'ticket_id' => $ticket->id,
                                        ])->first();
                                        @endphp
                                        @if (!empty($is_slot) && $slotItem['available_seat'] == false)
                                        <span class="badge badge-warning">
                                            {{ __('Booked') }}
                                        </span>
                                        @else
                                        <span class="badge badge-warning">
                                            {{ __('No Seat Found!') }}
                                        </span>
                                        @endif
                                        @endif
                                        @else
                                        @if ($ticket->early_bird_discount == 'enable')
                                        @php
                                        $discount_date = Carbon\Carbon::parse(
                                        $ticket->early_bird_discount_date .
                                        $ticket->early_bird_discount_time,
                                        );
                                        @endphp

                                        @if ($ticket->early_bird_discount_type == 'fixed' && !$discount_date->isPast())
                                        @php
                                        $calculate_price =
                                        $ticket->price -
                                        $ticket->early_bird_discount_amount;
                                        @endphp
                                        {{ symbolPrice($calculate_price) }}
                                        <del>

                                            {{ symbolPrice($ticket->price) }}
                                        </del>
                                        @elseif ($ticket->early_bird_discount_type == 'percentage' &&
                                        !$discount_date->isPast())
                                        @php
                                        $c_price =
                                        ($ticket->price *
                                        $ticket->early_bird_discount_amount) /
                                        100;
                                        $calculate_price =
                                        $ticket->price - $c_price;
                                        @endphp
                                        {{ symbolPrice($calculate_price) }}

                                        <del>
                                            {{ symbolPrice($ticket->price) }}
                                        </del>
                                        @else
                                        @php
                                        $calculate_price = $ticket->price;
                                        @endphp
                                        {{ symbolPrice($calculate_price) }}
                                        @endif
                                        @else
                                        @php
                                        $calculate_price = $ticket->price;
                                        @endphp
                                        {{ symbolPrice($calculate_price) }}
                                        @endif
                                        @endif
                                    </h6>
                                    <!------- quantity input (seating disabled) -------->
                                    <div class="quantity-input">
                                        <button class="quantity-down" type="button" id="quantityDown">
                                            -
                                        </button>
                                        <input class="quantity" readonly type="number" value="0"
                                            data-price="{{ $calculate_price }}"
                                            data-max_buy_ticket="{{ $ticket->max_buy_ticket }}" name="quantity[]"
                                            data-ticket_id="{{ $ticket->id }}" data-stock="{{ $stock }}"
                                            data-purchase="{{ $purchase['status'] }}"
                                            data-p_qty="{{ $purchase['p_qty'] }}">
                                        <button class="quantity-up" type="button" id="quantityUP">
                                            +
                                        </button>
                                    </div>

                                    @if ($ticket->early_bird_discount == 'enable')
                                    @php
                                    $discount_date = Carbon\Carbon::parse(
                                    $ticket->early_bird_discount_date .
                                    $ticket->early_bird_discount_time,
                                    );
                                    @endphp
                                    @if (!$discount_date->isPast())
                                    <p>{{ __('Discount available') . ' ' }} :
                                        ({{ __('till') . ' ' }} :
                                        <span dir="ltr">{{
                                            \Carbon\Carbon::parse($discount_date)->timezone($websiteInfo->timezone)->translatedFormat('Y/m/d
                                            h:i a') }}</span>)
                                    </p>
                                    @endif
                                    @endif

                                </div>
                                <p
                                    class="text-warning max_error_{{ $ticket->id }}{{ $ticket->max_ticket_buy_type == 'limited' ? $ticket->max_buy_ticket : '' }} ">
                                </p>
                                @elseif($ticket->pricing_type == 'variation')
                                @php
                                $variations = json_decode($ticket->variations);
                                $varition_names = App\Models\Event\VariationContent::where([
                                ['ticket_id', $ticket->id],
                                ['language_id', $currentLanguageInfo->id],
                                ])->get();
                                if (empty($varition_names)) {
                                $varition_names = App\Models\Event\VariationContent::where(
                                'ticket_id',
                                $ticket->id,
                                )->get();
                                }

                                $de_lang = App\Models\Language::where('is_default', 1)->first();
                                $de_varition_names = App\Models\Event\VariationContent::where([
                                ['ticket_id', $ticket->id],
                                ['language_id', $de_lang->id],
                                ])->get();
                                if (empty($de_varition_names)) {
                                $de_varition_names = App\Models\Event\VariationContent::where(
                                [['ticket_id', $ticket->id]],
                                )->get();
                                }
                                @endphp
                                @foreach (array_slice($variations, 0, 1) as $key => $item)
                                @php
                                //ticket purchase or not check
                                if (Auth::guard('customer')->user()) {
                                if (count($de_varition_names) > 0) {
                                $purchase = isTicketPurchaseVenue(
                                $ticket->event_id,
                                $item->v_max_ticket_buy,
                                $ticket->id,
                                $de_varition_names[$key]['name'],
                                );
                                }
                                } else {
                                $purchase = ['status' => 'false', 'p_qty' => 0];
                                }
                                $ticket_content = App\Models\Event\TicketContent::where([
                                ['language_id', $currentLanguageInfo->id],
                                ['ticket_id', $ticket->id],
                                ])->first();
                                if (empty($ticket_content)) {
                                $ticket_content = App\Models\Event\TicketContent::where(
                                [['ticket_id', $ticket->id]],
                                )->first();
                                }
                                @endphp
                                <p class="mb-0"><strong>Platform ticket</strong></p>
                                <div class="click-show">
                                    <div class="show-content">
                                        {!! @$ticket_content->description !!}
                                    </div>
                                    @if (strlen(@$ticket_content->description) > 50)
                                    <div class="read-more-btn">
                                        <span>{{ __('Read more') }}</span>
                                        <span>{{ __('Read less') }}</span>
                                    </div>
                                    @endif
                                </div>
                                <div class="price-count">
                                    <h6 dir="ltr">
                                        @if (!empty($item?->slot_enable) && $item->slot_enable == 1)
                                        @php
                                        $slotItem = app(
                                        \App\Services\BookingServices::class,
                                        )->showSlot(
                                        $event_id,
                                        $ticket->id,
                                        $item->slot_unique_id,
                                        );
                                        $slotPrice = $slotItem['price'];
                                        @endphp

                                        @if ($slotItem['available_seat'] == true)
                                        @if ($ticket->early_bird_discount == 'enable')
                                        @php
                                        $discount_date = Carbon\Carbon::parse(
                                        $ticket->early_bird_discount_date .
                                        $ticket->early_bird_discount_time,
                                        );
                                        @endphp

                                        @if ($ticket->early_bird_discount_type == 'fixed' && !$discount_date->isPast())
                                        @php
                                        $calculate_price =
                                        $slotPrice -
                                        $ticket->early_bird_discount_amount;
                                        @endphp
                                        {{ symbolPrice($calculate_price) }}
                                        <del>
                                            {{ symbolPrice($slotPrice) }}
                                        </del>
                                        @elseif ($ticket->early_bird_discount_type == 'percentage' &&
                                        !$discount_date->isPast())
                                        @php
                                        $c_price =
                                        ($slotPrice *
                                        $ticket->early_bird_discount_amount) /
                                        100;
                                        $calculate_price =
                                        $slotPrice - $c_price;
                                        @endphp
                                        {{ symbolPrice($calculate_price) }}
                                        <del>
                                            {{ symbolPrice($slotPrice) }}
                                        </del>
                                        @else
                                        {{ symbolPrice($slotPrice) }}
                                        @endif
                                        @else
                                        {{ symbolPrice($slotPrice) }}
                                        @endif
                                        @else
                                        @php
                                        $is_slot = App\Models\Event\Slot::where([
                                        'slot_unique_id' =>
                                        $item->slot_unique_id,
                                        'event_id' => $event_id,
                                        'ticket_id' => $ticket->id,
                                        ])->first();
                                        @endphp

                                        @if (!is_null($is_slot) && $slotItem['available_seat'] == false)
                                        <span class="badge badge-warning">
                                            {{ __('Booked') }}
                                        </span>
                                        @else
                                        <span class="badge badge-warning">
                                            {{ __('No Seat Available') }}
                                        </span>
                                        @endif
                                        @endif
                                        @else
                                        @if ($ticket->early_bird_discount == 'enable')
                                        @php
                                        $discount_date = Carbon\Carbon::parse(
                                        $ticket->early_bird_discount_date .
                                        $ticket->early_bird_discount_time,
                                        );
                                        @endphp
                                        @if ($ticket->early_bird_discount_type == 'fixed' && !$discount_date->isPast())
                                        @php
                                        $calculate_price =
                                        $item->price -
                                        $ticket->early_bird_discount_amount;
                                        @endphp
                                        {{ symbolPrice($calculate_price) }}

                                        <del>
                                            {{ symbolPrice($item->price) }}
                                        </del>
                                        @elseif ($ticket->early_bird_discount_type == 'percentage' &&
                                        !$discount_date->isPast())
                                        @php
                                        $c_price =
                                        ($item->price *
                                        $ticket->early_bird_discount_amount) /
                                        100;
                                        $calculate_price =
                                        $item->price - $c_price;
                                        @endphp
                                        {{ symbolPrice($calculate_price) }}

                                        <del>
                                            {{ symbolPrice($item->price) }}
                                        </del>
                                        @else
                                        @php
                                        $calculate_price = $item->price;
                                        @endphp

                                        {{ symbolPrice($calculate_price) }}
                                        @endif
                                        @else
                                        @php
                                        $calculate_price = $item->price;
                                        @endphp
                                        {{ symbolPrice($calculate_price) }}
                                        @endif
                                        @endif
                                    </h6>

                                    @if (!empty($item?->slot_enable) && $item->slot_enable == 1)
                                    <button
                                        class="theme-btn-2 btn-xs mb-1 btn_seat_mapping_slot {{ $over == true ? 'btn-warning' : '' }}"
                                        data-slot_unique_id="{{ $item?->slot_unique_id }}"
                                        data-event_id="{{ request()->id }}" data-ticket_id="{{ $ticket->id }}"
                                        data-url="{{ route('event.slot-mapping-seat') }}" @if ($over==true) disabled
                                        @endif>

                                        {{ $over == true ? __('Over') : __('Seating') }}
                                    </button>
                                    <input type="hidden" value="0" class="quantity" data-price="0"
                                        data-max_buy_ticket="" data-name="{{ $item->name }}" name="quantity[]"
                                        data-ticket_id="{{ $ticket->id }}" readonly data-stock="0">
                                    @else
                                    <div class="quantity-input">
                                        <button class="quantity-down_variation" type="button" id="quantityDown">
                                            -
                                        </button>
                                        <input type="hidden" name="v_name[]" value="{{ $item->name }}">
                                        @php
                                        if ($item->ticket_available_type == 'limited') {
                                        $stock = $item->ticket_available;
                                        } else {
                                        $stock = 'unlimited';
                                        }
                                        if ($item->max_ticket_buy_type == 'limited') {
                                        $max_buy = $item->v_max_ticket_buy;
                                        } else {
                                        $max_buy = 'unlimited';
                                        }
                                        @endphp
                                        <input type="number" value="0" class="quantity"
                                            data-price="{{ $calculate_price }}" data-max_buy_ticket="{{ $max_buy }}"
                                            data-name="{{ $item->name }}" name="quantity[]"
                                            data-ticket_id="{{ $ticket->id }}" readonly data-stock="{{ $stock }}">
                                        <button class="quantity-up" type="button" id="quantityUP">
                                            +
                                        </button>
                                    </div>
                                    @endif

                                    @if ($ticket->early_bird_discount == 'enable')
                                    @php
                                    $discount_date = Carbon\Carbon::parse(
                                    $ticket->early_bird_discount_date .
                                    $ticket->early_bird_discount_time,
                                    );
                                    @endphp
                                    @if (!$discount_date->isPast())
                                    <p>{{ __('Discount available') . ' ' }} :
                                        ({{ __('till') . ' ' }} :
                                        <span dir="ltr">{{
                                            \Carbon\Carbon::parse($discount_date)->translatedFormat('Y/m/d h:i a')
                                            }}</span>)
                                    </p>
                                    @endif
                                    @endif

                                </div>
                                <p class="text-warning max_error_{{ $ticket->id }}{{ $item->v_max_ticket_buy }} ">
                                </p>
                                @endforeach
                                @elseif($ticket->pricing_type == 'free')
                                @php
                                if ($ticket->ticket_available_type == 'limited') {
                                $stock = $ticket->ticket_available;
                                } else {
                                $stock = 'unlimited';
                                }

                                //ticket purchase or not check
                                $de_lang = App\Models\Language::where('is_default', 1)->first();
                                $ticket_content_default = App\Models\Event\TicketContent::where(
                                [['language_id', $de_lang->id], ['ticket_id', $ticket->id]],
                                )->first();
                                if (
                                Auth::guard('customer')->user() &&
                                $ticket->max_ticket_buy_type == 'limited'
                                ) {
                                $purchase = isTicketPurchaseVenue(
                                $ticket->event_id,
                                $ticket->max_buy_ticket,
                                $ticket->id,
                                @$ticket_content_default->title,
                                );
                                } else {
                                $purchase = ['status' => 'false', 'p_qty' => 1];
                                }
                                $ticket_content = App\Models\Event\TicketContent::where([
                                ['language_id', $currentLanguageInfo->id],
                                ['ticket_id', $ticket->id],
                                ])->first();
                                @endphp
                                <p class="mb-0"><strong>Platform ticket</strong></p>
                                <div class="click-show">
                                    <div class="show-content">
                                        {!! @$ticket_content->description !!}
                                    </div>
                                    @if (strlen(@$ticket_content->description) > 50)
                                    <div class="read-more-btn">
                                        <span>{{ __('Read more') }}</span>
                                        <span>{{ __('Read less') }}</span>
                                    </div>
                                    @endif
                                </div>
                                <div class="price-count">
                                    <h6 dir="ltr">
                                        <!------- slot button-------->
                                        @if (isset($ticket) && $ticket->free_tickete_slot_enable == 1)
                                        @php
                                        $slotItem = app(
                                        \App\Services\BookingServices::class,
                                        )->showSlot(
                                        $event_id,
                                        $ticket->id,
                                        $ticket->free_tickete_slot_unique_id,
                                        );
                                        $slotPrice = 0.0;
                                        @endphp
                                        @if ($slotItem['available_seat'] == true)
                                        {{ __('free') }}
                                        @else
                                        @php
                                        $is_slot = App\Models\Event\Slot::where([
                                        'slot_unique_id' =>
                                        $ticket->free_tickete_slot_unique_id,
                                        'event_id' => $event_id,
                                        'ticket_id' => $ticket->id,
                                        ])->first();
                                        @endphp
                                        @if (!empty($is_slot) && $slotItem['available_seat'] == false)
                                        <span class="badge badge-warning">
                                            {{ __('Booked') }}
                                        </span>
                                        @else
                                        <span class="badge badge-warning">
                                            {{ __('No Seat Available') }}
                                        </span>
                                        @endif
                                        @endif
                                        @else
                                        {{ __('free') }}
                                        @endif

                                    </h6>
                                    @if (isset($ticket) && $ticket->free_tickete_slot_enable == 1)
                                    <button
                                        class="theme-btn-2 btn-xs mb-1 btn_seat_mapping_slot {{ $over == true ? 'btn-warning' : '' }}"
                                        data-slot_unique_id="{{ $ticket->free_tickete_slot_unique_id }}"
                                        data-event_id="{{ request()->id }}" data-ticket_id="{{ $ticket->id }}"
                                        data-url="{{ route('event.slot-mapping-seat') }}" @if ($over==true) disabled
                                        @endif>
                                        {{ $over == true ? __('Over') : __('Seating') }}
                                    </button>
                                    <input type="hidden" value="0" class="quantity" data-price="0"
                                        data-max_buy_ticket="" data-name="{{ @$ticket_content->title }}"
                                        name="quantity[]" data-ticket_id="{{ $ticket->id }}" readonly data-stock="0">
                                    @else
                                    <div class="quantity-input">
                                        <button class="quantity-down" type="button" id="quantityDown">
                                            -
                                        </button>
                                        <input class="quantity" data-max_buy_ticket="{{ $ticket->max_buy_ticket }}"
                                            type="number" value="0" data-price="{{ $ticket->price }}" name="quantity[]"
                                            data-ticket_id="{{ $ticket->id }}" readonly data-stock="{{ $stock }}"
                                            data-purchase="{{ $purchase['status'] }}"
                                            data-p_qty="{{ $purchase['p_qty'] }}">
                                        <button class="quantity-up" type="button" id="quantityUP">
                                            +
                                        </button>
                                    </div>
                                    @endif
                                </div>
                                <p
                                    class="text-warning max_error_{{ $ticket->id }}{{ $ticket->max_ticket_buy_type == 'limited' ? $ticket->max_buy_ticket : '' }} ">
                                </p>
                                @endif
                                @endforeach
                                @endif
                                @endif
                                @if (!empty($resaleTickets) && count($resaleTickets) > 0)
                                <hr style="border-top: 2px dashed #e1e1e1;">
                                <b>{{ __('Ticket source') }}</b>
                                <p class="small text-muted mb-0">{{ __('Only user resale offers are available here. Platform stock is not sold from this section.') }}</p>
                                @if (!empty($externalPlatformName))
                                  <p class="small mb-0 mt-1">
                                    <span class="badge badge-info">{{ __('External transfer') }}</span>
                                    {{ __('via') }} <strong>{{ $externalPlatformName }}</strong>
                                  </p>
                                @endif
                                <hr>

                                {{-- Zone summary first; click a zone to deploy Date/Zone/Price offers --}}
                                @if (!empty($resaleZoneSummary) && count($resaleZoneSummary) > 0)
                                <div class="quick-buy-fifo mb-3">
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <b>{{ __('Available by zone') }}</b>
                                        <small class="text-muted">{{ __('Click a zone to see tickets') }}</small>
                                    </div>

                                    <div class="resale-zone-summary">
                                        @foreach ($resaleZoneSummary as $zoneRow)
                                            @php
                                                $zoneKey = $zoneRow->zone_key;
                                                $zonePanelId = 'zone_panel_' . md5($zoneKey);
                                                $zoneOffers = collect($resaleGroups)->filter(function ($g) use ($zoneRow) {
                                                    $gz = trim((string) ($g->zone ?? ''));
                                                    if ($zoneRow->zone === null || $zoneRow->zone === '') {
                                                        return $gz === '';
                                                    }
                                                    return $gz === (string) $zoneRow->zone;
                                                })->values();
                                                $priceLabel = ($basicInfo->base_currency_symbol_position == 'left' ? $basicInfo->base_currency_symbol : '')
                                                    . number_format($zoneRow->min_price, 2)
                                                    . ($basicInfo->base_currency_symbol_position == 'right' ? $basicInfo->base_currency_symbol : '');
                                            @endphp
                                            <div class="resale-zone-block mb-2">
                                                <button type="button"
                                                        class="resale-zone-toggle w-100 text-left border rounded p-2 bg-white"
                                                        data-target="#{{ $zonePanelId }}"
                                                        aria-expanded="false"
                                                        style="cursor:pointer;">
                                                    <div class="d-flex align-items-center justify-content-between flex-wrap" style="gap:8px;">
                                                        <span class="font-weight-bold">
                                                            {{ !empty($zoneRow->zone) ? $zoneRow->zone : __('Other / Unspecified') }}
                                                        </span>
                                                        <span class="d-flex align-items-center" style="gap:10px;">
                                                            <span class="badge badge-light border px-2 py-1" style="font-size:0.95rem;">
                                                                {{ $priceLabel }}
                                                                <small class="text-muted">{{ __('min') }}</small>
                                                            </span>
                                                            <span class="d-inline-flex align-items-center text-danger font-weight-bold" title="{{ __('Tickets available') }}">
                                                                <i class="fas fa-ticket-alt mr-1"></i>
                                                                {{ (int) $zoneRow->total_quantity }}
                                                            </span>
                                                            <i class="fas fa-chevron-down resale-zone-chevron text-muted"></i>
                                                        </span>
                                                    </div>
                                                </button>

                                                <div id="{{ $zonePanelId }}" class="resale-zone-panel border border-top-0 rounded-bottom p-2 d-none" style="background:#fafbfc;">
                                                    @forelse ($zoneOffers as $group)
                                                        <div class="fifo-option mb-2 p-2 border rounded bg-white"
                                                             data-entrance-date="{{ $group->entrance_date ?? '' }}">
                                                            <div class="d-flex align-items-center justify-content-between flex-wrap" style="gap:8px;">
                                                                <div class="flex-grow-1">
                                                                    @if (!empty($group->entrance_date))
                                                                        <span class="badge badge-info mr-1">{{ \Carbon\Carbon::parse($group->entrance_date)->translatedFormat('D, d M Y') }}</span>
                                                                    @endif
                                                                    @if (!empty($group->zone))
                                                                        <span class="badge badge-light border mr-1">{{ $group->zone }}</span>
                                                                    @endif
                                                                    <strong>{{ $basicInfo->base_currency_symbol_position == 'left' ? $basicInfo->base_currency_symbol : '' }}{{ number_format($group->price, 2) }}{{ $basicInfo->base_currency_symbol_position == 'right' ? $basicInfo->base_currency_symbol : '' }}</strong>
                                                                    <small class="text-muted d-block">
                                                                        {{ (int) $group->total_quantity }} {{ __('available') }}
                                                                        · {{ $group->offers_count }} {{ __('offer(s)') }}
                                                                    </small>
                                                                </div>
                                                                @if (Auth::guard('customer')->check())
                                                                    <button type="button" class="btn btn-success btn-sm fifo-buy-btn"
                                                                            data-event-id="{{ $event_id }}"
                                                                            data-zone="{{ $group->zone }}"
                                                                            data-price="{{ $group->price }}"
                                                                            data-entrance-date="{{ $group->entrance_date ?? '' }}">
                                                                        <i class="fas fa-bolt mr-1"></i> {{ __('Buy') }}
                                                                    </button>
                                                                @else
                                                                    <a href="{{ route('customer.login') }}" class="btn btn-outline-success btn-sm">
                                                                        {{ __('Login to buy') }}
                                                                    </a>
                                                                @endif
                                                            </div>
                                                        </div>
                                                    @empty
                                                        <div class="alert alert-secondary mb-0 py-2">{{ __('No offers in this zone.') }}</div>
                                                    @endforelse
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                                <hr>
                                @else
                                    <div class="alert alert-secondary mb-0">{{ __('No resale offers available right now.') }}</div>
                                @endif

                                {{--
                                    Buy flow: zone summary (min price + count) → click zone → Date/Zone/Price offers → Buy (qty = 1).
                                --}}
                                @else
                                <hr style="border-top: 2px dashed #e1e1e1;">
                                <div class="alert alert-secondary mb-0">{{ __('No tickets found') }}</div>
                                @endif
                                <a class="theme-btn style-two w-100 mt-15"
                                    href="{{ Auth::guard('customer')->check() ? route('customer.tickets.sell.create', ['eventId' => $content->id]) : route('customer.login') }}">
                                    Sell ticket
                                </a>
                            </div>
                        </form>
                        {{--
                            The legacy seller-option / Book Now script was removed together with the
                            per-offer resale list above. All resale purchases now go through the
                            "Best available (FIFO)" buttons only (handled by the FIFO script at
                            the bottom of this page), in line with the PRD seller-anonymity rule.
                        --}}
                    </div>
                </div>
            </div>
            @if (!empty(showAd(3)))
            <div class="text-center mt-4">
                {!! showAd(3) !!}
            </div>
            @endif
        </div>
        @if (count($related_events) > 0)
        <hr>
        <div class="releted-event-header mt-50">
            <h3>{{ __('Related Events') }}</h3>
            <div class="related-event-buttons mb-10">
                <div class="related-event-slide-prev slide-btn"><i class="fas fa-chevron-left"></i></div>
                <div class="related-event-slide-next slide-btn"><i class="fas fa-chevron-right"></i></div>
            </div>
        </div>

        <div class="swiper related-event-slider">
            <div class="swiper-wrapper">
                @foreach ($related_events as $event)
                <div class="swiper-slide">
                    <div class="event-item">
                        <div class="event-image">
                            <a href="{{ route('event.details', [$event->slug, $event->id]) }}">
                                <img class="lazy"
                                    data-src="{{ asset('assets/admin/img/event/thumbnail/' . $event->thumbnail) }}"
                                    alt="Event">
                            </a>
                        </div>
                        <div class="event-content">
                            <ul class="time-info">
                                <li>
                                    <i class="far fa-calendar-alt"></i>
                                    <span>
                                        @php
                                        $date = strtotime($event->start_date);
                                        @endphp
                                        {{
                                        \Carbon\Carbon::parse($date)->timezone($websiteInfo->timezone)->translatedFormat('d
                                        M') }}
                                    </span>
                                </li>
                                @php
                                if ($event->date_type == 'multiple') {
                                $event_date = eventLatestDates($event->id);
                                $date = strtotime(@$event_date->start_date);
                                } else {
                                $date = strtotime($event->start_date);
                                }
                                @endphp
                                <li>
                                    <i class="far fa-hourglass"></i>
                                    <span title="Event Duration">{{ $event->date_type == 'multiple' ?
                                        @$event_date->duration : $event->duration }}</span>
                                </li>
                                <li>
                                    <i class="far fa-clock"></i>
                                    <span>
                                        @php
                                        $start_time = strtotime($event->start_time);
                                        @endphp
                                        {{
                                        \Carbon\Carbon::parse($start_time)->timezone($websiteInfo->timezone)->translatedFormat('h:s
                                        A') }}
                                    </span>
                                </li>
                            </ul>
                            <span class="organizer">Anonymous seller</span>
                            <h5>
                                <a href="{{ route('event.details', [$event->slug, $event->id]) }}">
                                    @if (strlen($event->title) > 30)
                                    {{ mb_substr($event->title, 0, 30) . '...' }}
                                    @else
                                    {{ $event->title }}
                                    @endif
                                </a>
                            </h5>
                            @php
                            $desc = strip_tags($event->description);
                            @endphp

                            @if (strlen($desc) > 45)
                            <p>{{ mb_substr($desc, 0, 50) . '....' }}</p>
                            @else
                            <p>{{ $desc }}</p>
                            @endif
                            @php
                            $ticket = DB::table('tickets')->where('event_id', $event->id)->first();
                            $event_count = DB::table('tickets')
                            ->where('event_id', $event->id)
                            ->get()
                            ->count();
                            @endphp


                            <div class="price-remain">
                                <div class="location">
                                    @if ($event->event_type == 'venue')
                                    <i class="fas fa-map-marker-alt"></i>
                                    <span>
                                        {{ $event->address }}
                                    </span>
                                    @else
                                    <i class="fas fa-map-marker-alt"></i>
                                    <span>{{ __('Online') }}</span>
                                    @endif
                                </div>
                                <span>
                                    @if ($ticket)

                                    @if ($ticket->event_type == 'online')
                                    @if ($ticket->price != null)
                                    <span class="price" dir="ltr">
                                        @if ($ticket->early_bird_discount == 'enable')
                                        @php
                                        $discount_date = Carbon\Carbon::parse(
                                        $ticket->early_bird_discount_date .
                                        $ticket->early_bird_discount_time,
                                        );
                                        @endphp
                                        @if ($ticket->early_bird_discount_type == 'fixed' && !$discount_date->isPast())
                                        @php
                                        $calculate_price =
                                        $ticket->price -
                                        $ticket->early_bird_discount_amount;
                                        @endphp
                                        {{ symbolPrice($calculate_price) }}
                                        <span>
                                            <del>
                                                {{ symbolPrice($ticket->price) }}
                                            </del>
                                        </span>
                                        @elseif ($ticket->early_bird_discount_type == 'percentage' &&
                                        !$discount_date->isPast())
                                        @php
                                        $p_price =
                                        ($ticket->price *
                                        $ticket->early_bird_discount_amount) /
                                        100;
                                        $calculate_price =
                                        $ticket->price - $p_price;
                                        @endphp
                                        {{ symbolPrice($calculate_price) }}

                                        <span>
                                            <del>
                                                {{ symbolPrice($ticket->price) }}
                                            </del>
                                        </span>
                                        @else
                                        @php
                                        $calculate_price = $ticket->price;
                                        @endphp
                                        {{ symbolPrice($calculate_price) }}
                                        @endif
                                        @else
                                        @php
                                        $calculate_price = $ticket->price;
                                        @endphp
                                        {{ symbolPrice($calculate_price) }}
                                        @endif
                                    </span>
                                    @else
                                    <span class="price">{{ __('Free') }}</span>
                                    @endif
                                    @endif
                                    @if ($ticket->event_type == 'venue')
                                    @if ($ticket->pricing_type == 'variation')
                                    <span class="price" dir="ltr">
                                        @php
                                        $variation = json_decode($ticket->variations, true);
                                        $v_min_price = array_reduce(
                                        $variation,
                                        function ($a, $b) {
                                        return $a['price'] < $b['price'] ? $a : $b; }, array_shift($variation), ); if
                                            ($v_min_price['slot_enable']==1) { $slot_variations=json_decode($ticket->
                                            variations, true);
                                            $v_slot_min_price = array_reduce(
                                            $slot_variations,
                                            function ($a, $b) {
                                            return $a['slot_seat_min_price'] < $b['slot_seat_min_price'] ? $a : $b; },
                                                array_shift($slot_variations), );
                                                $price=$v_slot_min_price['slot_seat_min_price'] ?? 0.0; } else {
                                                $price=$v_min_price['price']; } @endphp <span class="price">
                                                @if ($currentLanguageInfo->direction == 1)
                                                <strong>{{ $event_count > 1 ? '*' : '' }}</strong>
                                                @endif
                                                @if ($ticket->early_bird_discount == 'enable')
                                                @php
                                                $discount_date = Carbon\Carbon::parse(
                                                $ticket->early_bird_discount_date .
                                                $ticket->early_bird_discount_time,
                                                );
                                                @endphp
                                                @if ($ticket->early_bird_discount_type == 'fixed' &&
                                                !$discount_date->isPast())
                                                @php
                                                $calculate_price =
                                                $price -
                                                $ticket->early_bird_discount_amount;
                                                @endphp
                                                @if ($calculate_price > 0)
                                                {{ symbolPrice($calculate_price) }}
                                                <span>
                                                    <del>
                                                        {{ symbolPrice($price) }}
                                                    </del>
                                                </span>
                                                @endif
                                                @elseif ($ticket->early_bird_discount_type == 'percentage' &&
                                                !$discount_date->isPast())
                                                @php
                                                $p_price =
                                                ($price *
                                                $ticket->early_bird_discount_amount) /
                                                100;
                                                $calculate_price = $price - $p_price;
                                                @endphp

                                                @if ($calculate_price > 0)
                                                {{ symbolPrice($calculate_price) }}
                                                <span>
                                                    <del>
                                                        {{ symbolPrice($price) }}
                                                    </del>
                                                </span>
                                                @endif
                                                @else
                                                @php
                                                $calculate_price = $price;
                                                @endphp
                                                @if ($calculate_price > 0)
                                                {{ symbolPrice($calculate_price) }}
                                                @endif
                                                @endif
                                                @else
                                                @php
                                                $calculate_price = $price;
                                                @endphp
                                                @if ($calculate_price > 0)
                                                {{ symbolPrice($calculate_price) }}
                                                @endif
                                                @endif
                                                @if ($currentLanguageInfo->direction != 1)
                                                <strong>{{ $event_count > 1 ? '*' : '' }}</strong>
                                                @endif
                                    </span>
                                </span>
                                @elseif($ticket->pricing_type == 'normal')
                                <span class="price" dir="ltr">
                                    @if ($currentLanguageInfo->direction == 1)
                                    <strong>{{ $event_count > 1 ? '*' : '' }}</strong>
                                    @endif
                                    @php
                                    if ($ticket->normal_ticket_slot_enable == 1) {
                                    $ticketPrice = $ticket->slot_seat_min_price;
                                    } else {
                                    $ticketPrice = $ticket->price;
                                    }
                                    @endphp

                                    @if ($ticket->early_bird_discount == 'enable')
                                    {{-- check discount date over or not --}}
                                    @php
                                    $discount_date = Carbon\Carbon::parse(
                                    $ticket->early_bird_discount_date .
                                    $ticket->early_bird_discount_time,
                                    );
                                    @endphp

                                    @if ($ticket->early_bird_discount_type == 'fixed' && !$discount_date->isPast())
                                    @php
                                    $calculate_price =
                                    $ticketPrice -
                                    $ticket->early_bird_discount_amount;
                                    @endphp
                                    @if ($calculate_price > 0)
                                    {{ symbolPrice($calculate_price) }}
                                    <span>
                                        <del>
                                            {{ symbolPrice($ticketPrice) }}
                                        </del>
                                    </span>
                                    @endif
                                    @elseif ($ticket->early_bird_discount_type == 'percentage' &&
                                    !$discount_date->isPast())
                                    @php
                                    $p_price =
                                    ($ticketPrice *
                                    $ticket->early_bird_discount_amount) /
                                    100;
                                    $calculate_price = $ticketPrice - $p_price;
                                    @endphp
                                    @if ($calculate_price > 0)
                                    {{ symbolPrice($calculate_price) }}
                                    <span>
                                        <del>
                                            {{ symbolPrice($ticketPrice) }}
                                        </del>
                                    </span>
                                    @endif
                                    @else
                                    @php
                                    $calculate_price = $ticketPrice;
                                    @endphp
                                    @if ($calculate_price > 0)
                                    {{ symbolPrice($calculate_price) }}
                                    @endif
                                    @endif
                                    @else
                                    @php
                                    $calculate_price = $ticketPrice;
                                    @endphp
                                    @if ($calculate_price > 0)
                                    {{ symbolPrice($calculate_price) }}
                                    @endif
                                    @endif
                                    @if ($currentLanguageInfo->direction != 1)
                                    <strong>{{ $event_count > 1 ? '*' : '' }}</strong>
                                    @endif
                                </span>
                                @else
                                <span class="price">
                                    {{ __('Free') }}
                                    <strong>{{ $event_count > 1 ? '*' : '' }}</strong>
                                </span>
                                @endif
                                @endif
                                @endif
                                </span>
                            </div>
                        </div>
                        @if (Auth::guard('customer')->check())
                        @php
                        $customer_id = Auth::guard('customer')->user()->id;
                        $event_id = $event->id;
                        $checkWishList = checkWishList($event_id, $customer_id);
                        @endphp
                        @else
                        @php
                        $checkWishList = false;
                        @endphp
                        @endif
                        <a href="{{ $checkWishList == false ? route('addto.wishlist', $event->id) : route('remove.wishlist', $event->id) }}"
                            class="wishlist-btn {{ $checkWishList == true ? 'bg-success' : '' }}">
                            <i class="far fa-bookmark"></i>
                        </a>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif
    </div>
</section>
<!-- Event Page End -->

@endsection
@section('script')
<script>
    "use strict"
    var currency_symbol = "{{ $basicInfo->base_currency_symbol }}";
    var price_text = "{{ __('Price') }}";
    var lang_No_seat_selected_yet = "{{ __('No seat selected yet') }}";
    var seat_has_been_selected_msg = "{{ __('Seat has been selected!') }}";
    var seat_has_been_unselected_msg = "{{ __('Seat has been unselected!') }}";
    var seleted_text = "{{ __('Selected') }}";
    var select_text = "{{ __('Select') }}";
    var slot_already_booked_msg = "{{ __('Slot already Booked!') }}";
    var booked_text = "{{ __('Booked') }}";
</script>
<script src="{{ asset('assets/front/js/slot.js') }}"></script>
<script>
    // Zone summary → expand offers → Buy (qty always 1).
    (function () {
        document.querySelectorAll('.resale-zone-toggle').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                var targetSel = this.getAttribute('data-target');
                var panel = targetSel ? document.querySelector(targetSel) : null;
                if (!panel) return;

                var isOpen = !panel.classList.contains('d-none');

                // Accordion: close other zone panels
                document.querySelectorAll('.resale-zone-panel').forEach(function (p) {
                    p.classList.add('d-none');
                });
                document.querySelectorAll('.resale-zone-toggle').forEach(function (b) {
                    b.setAttribute('aria-expanded', 'false');
                    var chev = b.querySelector('.resale-zone-chevron');
                    if (chev) {
                        chev.classList.remove('fa-chevron-up');
                        chev.classList.add('fa-chevron-down');
                    }
                });

                if (!isOpen) {
                    panel.classList.remove('d-none');
                    this.setAttribute('aria-expanded', 'true');
                    var chevron = this.querySelector('.resale-zone-chevron');
                    if (chevron) {
                        chevron.classList.remove('fa-chevron-down');
                        chevron.classList.add('fa-chevron-up');
                    }
                }
            });
        });

        document.querySelectorAll('.fifo-buy-btn').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                var eventId = this.getAttribute('data-event-id');
                var zone = this.getAttribute('data-zone') || '';
                var price = this.getAttribute('data-price') || '';
                var entranceDate = this.getAttribute('data-entrance-date') || '';

                var form = document.createElement('form');
                form.method = 'POST';
                form.action = "{{ route('customer.tickets.reserve') }}";
                form.style.display = 'none';

                var token = document.createElement('input');
                token.type = 'hidden';
                token.name = '_token';
                token.value = "{{ csrf_token() }}";
                form.appendChild(token);

                [['event_id', eventId], ['zone', zone], ['price', price], ['quantity', '1'], ['entrance_date', entranceDate]].forEach(function (pair) {
                    var i = document.createElement('input');
                    i.type = 'hidden';
                    i.name = pair[0];
                    i.value = pair[1];
                    form.appendChild(i);
                });

                document.body.appendChild(form);
                form.submit();
            });
        });
    })();
</script>
@endsection
@section('modals')
@includeIf('frontend.partials.modals')
@includeIf('frontend.event.slots.seat-mapping-modal')
@includeIf('frontend.event.slots.slots-seat')
@endsection
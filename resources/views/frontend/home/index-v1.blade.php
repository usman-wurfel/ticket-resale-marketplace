@extends('frontend.layout')
@section('pageHeading')
    {{ __('Home') }}
@endsection

@php
    $metaKeywords = !empty($seo->meta_keyword_home) ? $seo->meta_keyword_home : '';
    $metaDescription = !empty($seo->meta_description_home) ? $seo->meta_description_home : '';
@endphp
@section('meta-keywords', "{{ $metaKeywords }}")
@section('meta-description', "$metaDescription")

@section('hero-section')
    <!-- Hero Section Start -->
    @if ($heroSection)
        <section class="hero-section overlay pt-105 pb-120 lazy"
            data-bg="{{ asset('assets/admin/img/hero-section/' . $heroSection->background_image) }}">
        @else
            <section class="hero-section overlay pt-105 pb-120 lazy" data-bg="{{ asset('assets/front/images/hero-bg.jpg') }}">
    @endif
    <div class="container">
        <div class="hero-content">
            <h1>
                {{ __('Resell Tickets Safely & Easily') }}
            </h1>
            <p>
                {{ __('A simple marketplace where buyers reserve tickets and sellers list theirs for resale. No online payments yet – just reserve your ticket and pay at the event.') }}
            </p>
            <form id="event-search" class="event-search mt-35" name="event-search" action="{{ route('events') }}"
                method="get">
                <div class="search-item">
                    <label for="borwseby"><i class="fas fa-list"></i></label>
                    <select name="category" id="borwseby">
                        <option value="">{{ __('All Tickets') }}</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->slug }}">{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="search-item">
                    <label for="search"><i class="fas fa-search"></i></label>
                    <input type="search" id="search" name="search-input" data-event-suggest
                        placeholder="{{ __('Search tickets, events or venues') }}"
                        value="{{ request()->input('search-input') }}"
                        autocomplete="off">
                </div>
                <button type="submit"
                    class="theme-btn">{{ __('Find Tickets') }}</button>
            </form>
        </div>
    </div>
    </section>
    <!-- Hero Section End -->
@endsection
@section('content')

    <!-- Events Section Start -->
    @if ($secInfo->featured_section_status == 1)
        <section class="events-section pt-110 rpt-90 pb-90 rpb-70 bg-lighter">
            <div class="container">

                <div class="section-title text-center mb-45">
                    <h2>{{ $secTitleInfo ? $secTitleInfo->event_section_title : __('Featured Events') }}</h2>
                </div>

                @if ($eventCategories->isEmpty())
                    <p class="text-center">{{ __('No Events Found') }}</p>
                @else
                    <nav>
                        <div class="nav nav-tabs events-tabs mb-40" id="nav-tab" role="tablist">
                            <button class="nav-link active" id="nav-all-tab" data-toggle="tab" data-target="#nav-all"
                                type="button" role="tab" aria-controls="nav-all"
                                aria-selected="true">{{ __('All') }}</button>
                            @foreach ($eventCategories as $item)
                                <button class="nav-link" id="nav-{{ $item->id }}-tab" data-toggle="tab"
                                    data-target="#nav-{{ $item->id }}" type="button" role="tab"
                                    aria-controls="nav-{{ $item->id }}"
                                    aria-selected="false">{{ $item->name }}</button>
                            @endforeach
                        </div>
                    </nav>

                    <div class="tab-content" id="nav-tabContent">
                        <div class="tab-pane fade show active" id="nav-all" role="tabpanel" aria-labelledby="nav-all-tab">
                            <div class="row">
                                @php
                                    $now_time = \Carbon\Carbon::now();
                                    $eventsall = DB::table('event_contents')
                                        ->join('events', 'events.id', '=', 'event_contents.event_id')
                                        ->where([
                                            ['event_contents.language_id', '=', $currentLanguageInfo->id],
                                            ['events.status', 1],
                                            ['events.end_date_time', '>=', $now_time],
                                            ['events.is_featured', '=', 'yes'],
                                        ])
                                        ->orderBy('events.created_at', 'desc')
                                        ->get();
                                @endphp
                                @foreach ($eventsall as $event)
                                    <div class="col-lg-4 col-md-6 item  motivational">
                                        <div class="event-item">
                                            <div class="event-image">
                                                <a href="{{ route('event.details', [$event->slug, $event->id]) }}">
                                                    <img src="{{ asset('assets/admin/img/event/thumbnail/' . $event->thumbnail) }}"
                                                        alt="Event">
                                                </a>
                                            </div>
                                            <div class="event-content">
                                                <ul class="time-info">
                                                    @php
                                                        if ($event->date_type == 'multiple') {
                                                            $event_date = eventLatestDates($event->id);
                                                            $date = strtotime(@$event_date->start_date);
                                                        } else {
                                                            $date = strtotime($event->start_date);
                                                        }
                                                    @endphp
                                                    <li>
                                                        <i class="far fa-calendar-alt"></i>
                                                        <span>
                                                            {{ \Carbon\Carbon::parse($date)->timezone($websiteInfo->timezone)->translatedFormat('d M') }}
                                                        </span>
                                                    </li>
                                                    <li>
                                                        <i class="far fa-hourglass"></i>
                                                        <span
                                                            title="{{ __('Event Duration') }}">{{ $event->date_type == 'multiple' ? @$event_date->duration : $event->duration }}</span>
                                                    </li>
                                                    <li>
                                                        <i class="far fa-clock"></i>
                                                        <span>
                                                            @php
                                                                $start_time = strtotime($event->start_time);
                                                            @endphp
                                                            {{ \Carbon\Carbon::parse($start_time)->timezone($websiteInfo->timezone)->translatedFormat('h:i A') }}
                                                        </span>
                                                    </li>
                                                </ul>
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

                                                @if (strlen($desc) > 100)
                                                    <p class="event-description">{{ mb_substr($desc, 0, 100) . '....' }}
                                                    </p>
                                                @else
                                                    <p class="event-description">{{ $desc }}</p>
                                                @endif
                                                @php
                                                    if ($event->event_type == 'online') {
                                                        $ticket = App\Models\Event\Ticket::where('event_id', $event->id)
                                                            ->orderBy('price', 'asc')
                                                            ->first();
                                                    } else {
                                                        $ticket = App\Models\Event\Ticket::where([
                                                            ['event_id', $event->id],
                                                            ['price', '!=', null],
                                                        ])
                                                            ->orderBy('price', 'asc')
                                                            ->first();
                                                        if (empty($ticket)) {
                                                            $ticket = App\Models\Event\Ticket::where([
                                                                ['event_id', $event->id],
                                                                ['f_price', '!=', null],
                                                            ])
                                                                ->orderBy('price', 'asc')
                                                                ->first();
                                                        }
                                                    }
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
                                                                            @elseif ($ticket->early_bird_discount_type == 'percentage' && !$discount_date->isPast())
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
                                                                            $variation = json_decode(
                                                                                $ticket->variations,
                                                                                true,
                                                                            );
                                                                            $v_min_price = array_reduce(
                                                                                $variation,
                                                                                function ($a, $b) {
                                                                                    return $a['price'] < $b['price']
                                                                                        ? $a
                                                                                        : $b;
                                                                                },
                                                                                array_shift($variation),
                                                                            );

                                                                            if ($v_min_price['slot_enable'] == 1) {
                                                                                $slot_variations = json_decode(
                                                                                    $ticket->variations,
                                                                                    true,
                                                                                );
                                                                                $v_slot_min_price = array_reduce(
                                                                                    $slot_variations,
                                                                                    function ($a, $b) {
                                                                                        return $a[
                                                                                            'slot_seat_min_price'
                                                                                        ] < $b['slot_seat_min_price']
                                                                                            ? $a
                                                                                            : $b;
                                                                                    },
                                                                                    array_shift($slot_variations),
                                                                                );
                                                                                $price =
                                                                                    $v_slot_min_price[
                                                                                        'slot_seat_min_price'
                                                                                    ] ?? 0.0;
                                                                            } else {
                                                                                $price = $v_min_price['price'];
                                                                            }

                                                                        @endphp


                                                                        <span class="price">
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
                                                                                @if ($ticket->early_bird_discount_type == 'fixed' && !$discount_date->isPast())
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
                                                                                @elseif ($ticket->early_bird_discount_type == 'percentage' && !$discount_date->isPast())
                                                                                    @php
                                                                                        $p_price =
                                                                                            ($price *
                                                                                                $ticket->early_bird_discount_amount) /
                                                                                            100;
                                                                                        $calculate_price =
                                                                                            $price - $p_price;
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
                                                                                    {{ symbolPrice($calculate_price) }}
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
                                                                            if (
                                                                                $ticket->normal_ticket_slot_enable == 1
                                                                            ) {
                                                                                $ticketPrice =
                                                                                    $ticket->slot_seat_min_price;
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
                                                                            @elseif ($ticket->early_bird_discount_type == 'percentage' && !$discount_date->isPast())
                                                                                @php
                                                                                    $p_price =
                                                                                        ($ticketPrice *
                                                                                            $ticket->early_bird_discount_amount) /
                                                                                        100;
                                                                                    $calculate_price =
                                                                                        $ticketPrice - $p_price;
                                                                                @endphp
                                                                                @if ($calculate_price > 0)
                                                                                    {{ symbolPrice($calculate_price) }}
                                                                                    <span>
                                                                                        <del>
                                                                                            {{ symbolPrice($ticket->price) }}
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
                                                                    <span class="price">{{ __('Free') }}
                                                                        <strong>{{ $event_count > 1 ? '*' : '' }}</strong>
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
                        @foreach ($eventCategories as $item)
                            @php
                                $now_time = \Carbon\Carbon::now();
                                $events = DB::table('event_contents')
                                    ->join('events', 'events.id', '=', 'event_contents.event_id')
                                    ->where([
                                        ['event_contents.event_category_id', '=', $item->id],
                                        ['event_contents.language_id', '=', $currentLanguageInfo->id],
                                        ['events.status', 1],
                                        ['events.end_date_time', '>=', $now_time],
                                        ['events.is_featured', '=', 'yes'],
                                    ])
                                    ->orderBy('events.created_at', 'desc')
                                    ->get();
                            @endphp
                            <div class="tab-pane fade" id="nav-{{ $item->id }}" role="tabpanel"
                                aria-labelledby="nav-{{ $item->id }}-tab">
                                <div class="row">
                                    @foreach ($events as $event)
                                        <div class="col-lg-4 col-md-6 item  motivational">
                                            <div class="event-item">
                                                <div class="event-image">
                                                    <a href="{{ route('event.details', [$event->slug, $event->id]) }}">
                                                        <img src="{{ asset('assets/admin/img/event/thumbnail/' . $event->thumbnail) }}"
                                                            alt="Event">
                                                    </a>
                                                </div>
                                                <div class="event-content">
                                                    <ul class="time-info">
                                                        @php
                                                            if ($event->date_type == 'multiple') {
                                                                $event_date = eventLatestDates($event->id);
                                                                $date = strtotime(@$event_date->start_date);
                                                            } else {
                                                                $date = strtotime($event->start_date);
                                                            }
                                                        @endphp
                                                        <li>
                                                            <i class="far fa-calendar-alt"></i>
                                                            <span>
                                                                {{ \Carbon\Carbon::parse($date)->timezone($websiteInfo->timezone)->translatedFormat('d M') }}
                                                            </span>
                                                        </li>
                                                        <li>
                                                            <i class="far fa-hourglass"></i>
                                                            <span
                                                                title="{{ __('Event Duration') }}">{{ $event->date_type == 'multiple' ? @$event_date->duration : $event->duration }}</span>
                                                        </li>
                                                        <li>
                                                            <i class="far fa-clock"></i>
                                                            <span>
                                                                @php
                                                                    $start_time = strtotime($event->start_time);
                                                                @endphp
                                                                {{ \Carbon\Carbon::parse($start_time)->timezone($websiteInfo->timezone)->translatedFormat('h:i A') }}
                                                            </span>
                                                        </li>
                                                    </ul>
                                                    <h5>
                                                        <a
                                                            href="{{ route('event.details', [$event->slug, $event->id]) }}">
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

                                                    @if (strlen($desc) > 100)
                                                        <p class="event-description">
                                                            {{ mb_substr($desc, 0, 100) . '....' }}</p>
                                                    @else
                                                        <p class="event-description">{{ $desc }}</p>
                                                    @endif
                                                    @php
                                                        if ($event->event_type == 'online') {
                                                            $ticket = App\Models\Event\Ticket::where(
                                                                'event_id',
                                                                $event->id,
                                                            )
                                                                ->orderBy('price', 'asc')
                                                                ->first();
                                                        } else {
                                                            $ticket = App\Models\Event\Ticket::where([
                                                                ['event_id', $event->id],
                                                                ['price', '!=', null],
                                                            ])
                                                                ->orderBy('price', 'asc')
                                                                ->first();
                                                            if (empty($ticket)) {
                                                                $ticket = App\Models\Event\Ticket::where([
                                                                    ['event_id', $event->id],
                                                                    ['f_price', '!=', null],
                                                                ])
                                                                    ->orderBy('price', 'asc')
                                                                    ->first();
                                                            }
                                                        }
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
                                                                                @elseif ($ticket->early_bird_discount_type == 'percentage' && !$discount_date->isPast())
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
                                                                                        $calculate_price =
                                                                                            $ticket->price;
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
                                                                                $variation = json_decode(
                                                                                    $ticket->variations,
                                                                                    true,
                                                                                );
                                                                                $v_min_price = array_reduce(
                                                                                    $variation,
                                                                                    function ($a, $b) {
                                                                                        return $a['price'] < $b['price']
                                                                                            ? $a
                                                                                            : $b;
                                                                                    },
                                                                                    array_shift($variation),
                                                                                );
                                                                                if ($v_min_price['slot_enable'] == 1) {
                                                                                    $slot_variations = json_decode(
                                                                                        $ticket->variations,
                                                                                        true,
                                                                                    );
                                                                                    $v_slot_min_price = array_reduce(
                                                                                        $slot_variations,
                                                                                        function ($a, $b) {
                                                                                            return $a[
                                                                                                'slot_seat_min_price'
                                                                                            ] <
                                                                                                $b[
                                                                                                    'slot_seat_min_price'
                                                                                                ]
                                                                                                ? $a
                                                                                                : $b;
                                                                                        },
                                                                                        array_shift($slot_variations),
                                                                                    );
                                                                                    $price =
                                                                                        $v_slot_min_price[
                                                                                            'slot_seat_min_price'
                                                                                        ] ?? 0.0;
                                                                                } else {
                                                                                    $price = $v_min_price['price'];
                                                                                }
                                                                            @endphp

                                                                            <span class="price">
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
                                                                                    @if ($ticket->early_bird_discount_type == 'fixed' && !$discount_date->isPast())
                                                                                        @php
                                                                                            $calculate_price =
                                                                                                $price -
                                                                                                $ticket->early_bird_discount_amount;
                                                                                        @endphp
                                                                                        @if ($calculate_price > 0)
                                                                                            {{ symbolPrice($calculate_price) }}
                                                                                            <span><del>
                                                                                                    {{ symbolPrice($price) }}
                                                                                                </del>
                                                                                            </span>
                                                                                        @endif
                                                                                    @elseif($ticket->early_bird_discount_type == 'percentage' && !$discount_date->isPast())
                                                                                        @php
                                                                                            $p_price =
                                                                                                ($price *
                                                                                                    $ticket->early_bird_discount_amount) /
                                                                                                100;
                                                                                            $calculate_price =
                                                                                                $p_price - $price;
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
                                                                                        {{ symbolPrice($calculate_price) }}
                                                                                    @endif
                                                                                @else
                                                                                    @if ($calculate_price > 0)
                                                                                        @php
                                                                                            $calculate_price = $price;
                                                                                        @endphp
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
                                                                                if (
                                                                                    $ticket->normal_ticket_slot_enable ==
                                                                                    1
                                                                                ) {
                                                                                    $ticketPrice =
                                                                                        $ticket->slot_seat_min_price;
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
                                                                                @elseif ($ticket->early_bird_discount_type == 'percentage' && !$discount_date->isPast())
                                                                                    @php
                                                                                        $p_price =
                                                                                            ($ticketPrice *
                                                                                                $ticket->early_bird_discount_amount) /
                                                                                            100;
                                                                                        $calculate_price =
                                                                                            $ticketPrice - $p_price;
                                                                                    @endphp
                                                                                    @if ($calculate_price > 0)
                                                                                        {{ symbolPrice($calculate_price) }}
                                                                                        <span>
                                                                                            <del>
                                                                                                {{ symbolPrice($ticket->price) }}
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
                                                                        <span class="price">{{ __('Free') }}
                                                                            <strong>{{ $event_count > 1 ? '*' : '' }}</strong>
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
                        @endforeach
                    </div>
                @endif

            </div>
            @if (!empty(showAd(3)))
                <div class="text-center mt-4">
                    {!! showAd(3) !!}
                </div>
            @endif
        </section>
    @endif
    <!-- Events Section End -->

    <!-- Category Section Start -->
    @if ($secInfo->categories_section_status == 1)
        <section class="category-section pt-110 rpt-90 pb-80 rpb-60">
            <div class="container">
                <div class="section-title mb-60">
                    <h2>{{ $secTitleInfo ? $secTitleInfo->category_section_title : __('Categories') }}</h2>
                </div>
                <div class="category-wrap text-white">
                    @if (count($eventCategories) > 0)
                        @foreach ($eventCategories as $item)
                            <a href="{{ route('events', ['category' => $item->slug]) }}" class="category-item">
                                <img class="lazy"
                                    data-src="{{ asset('assets/admin/img/event-category/' . $item->image) }}"
                                    alt="Category">
                                <div class="category-content">
                                    <h5>{{ $item->name }}</h5>
                                </div>
                            </a>
                        @endforeach
                    @else
                        <h3 class="text-dark">{{ __('No Category Found') }}</h3>
                    @endif


                </div>
            </div>
        </section>
    @endif
    <!-- Category Section End -->

    <!-- About Section Start -->
    @if ($secInfo->about_section_status == 1)
        <section class="about-section pb-120 rpb-95">
            <div class="container">
                @if (is_null($aboutUsSection))
                    <h2 class="text-center">{{ __('No data found for about section') }}</h2>
                @endif
                <div class="row align-items-center">
                    <div class="col-lg-6">
                        <div class="about-image-part pt-10 rmb-55">
                            @if (!is_null($aboutUsSection))
                                <img class="lazy"
                                    data-src="{{ asset('assets/admin/img/about-us-section/' . $aboutUsSection->image) }}"
                                    alt="About">
                            @endif
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="about-content">
                            <div class="section-title mb-30">
                                <h2>{{ __('Know more about ticket resell') }}</h2>
                            </div>
                            <p>{{ __('This prototype focuses on the essentials of a secondary ticket marketplace so you can validate flows before investing in full development.') }}</p>
                            <div>
                                <p>{{ __('Buyers discover events, reserve tickets and manage their reservations from a simple dashboard.') }}</p>
                                <p>{{ __('Sellers list tickets they no longer need, set prices and see when their tickets are reserved or confirmed.') }}</p>
                                <p>{{ __('Admins keep an overview of users, tickets, transactions and incidents, ready for when payment and fraud logic are added later.') }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    @endif
    <!-- About Section End -->


    <!-- Feature Section Start -->
    <section class="feature-section pt-110 rpt-90 bg-lighter">
        @if ($secInfo->features_section_status == 1)
            <div class="container pb-90 rpb-70">
                <div class="section-title text-center mb-55">
                    <h2>{{ __('Why use our ticket marketplace?') }}</h2>
                    <p>{{ __('Simple flows for buyers, sellers and admins so you can validate the product quickly.') }}</p>
                </div>
                <div class="row justify-content-center">
                    @foreach ($featureEventItems as $item)
                        <div class="col-xl-4 col-md-6">
                            <div class="feature-item">
                                <i class="{{ $item->icon }}"></i>
                                <div class="feature-content">
                                    @if ($loop->index === 0)
                                        <h5>{{ __('Core resell flows') }}</h5>
                                        <p>{{ __('Users can list tickets, search events, reserve seats for 15 minutes and pay at the venue.') }}</p>
                                    @elseif ($loop->index === 1)
                                        <h5>{{ __('Buyer & seller areas') }}</h5>
                                        <p>{{ __('Separate dashboards for buyers and sellers so each role only sees its own actions.') }}</p>
                                    @elseif ($loop->index === 2)
                                        <h5>{{ __('Admin tools') }}</h5>
                                        <p>{{ __('Admin can review tickets, users and basic incidents before you plug in payments.') }}</p>
                                    @else
                                        <h5>{{ $item->title }}</h5>
                                        <p>{{ $item->text }}</p>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

            </div>
        @endif
        @if ($secInfo->how_work_section_status == 1)
            <div class="work-process text-center">
                <div class="container">
                    <div class="work-process-inner pt-110 rpt-90 pb-80 rpb-60">
                        <div class="section-title mb-60">
                            <h2>{{ __('How does it work?') }}</h2>
                            <p>{{ __('A lightweight prototype of a ticket resell marketplace focused on the core flows you need to validate the idea.') }}</p>
                        </div>
                        <div class="row justify-content-center">
                            <div class="col-xl-3 col-md-6">
                                <div class="work-process-item">
                                    <div class="icon">
                                        <span class="number">1</span>
                                        <i class="fas fa-user-plus"></i>
                                    </div>
                                    <div class="content">
                                        <h4>{{ __('Create account') }}</h4>
                                        <p>{{ __('Sign up as a buyer or seller with a simple email and password.') }}</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-3 col-md-6">
                                <div class="work-process-item">
                                    <div class="icon">
                                        <span class="number">2</span>
                                        <i class="fas fa-ticket-alt"></i>
                                    </div>
                                    <div class="content">
                                        <h4>{{ __('List or reserve') }}</h4>
                                        <p>{{ __('Sellers list tickets for events and buyers reserve them for 15 minutes.') }}</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-3 col-md-6">
                                <div class="work-process-item">
                                    <div class="icon">
                                        <span class="number">3</span>
                                        <i class="fas fa-check-circle"></i>
                                    </div>
                                    <div class="content">
                                        <h4>{{ __('Confirm & pay') }}</h4>
                                        <p>{{ __('Reservations are stored in the system and buyers pay offline at the venue.') }}</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-3 col-md-6">
                                <div class="work-process-item">
                                    <div class="icon">
                                        <span class="number">4</span>
                                        <i class="fas fa-user-shield"></i>
                                    </div>
                                    <div class="content">
                                        <h4>{{ __('Admin dashboard') }}</h4>
                                        <p>{{ __('Admins review users, tickets and incidents before you plug in payments.') }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </section>
    <!-- Feature Section End -->

    {{-- Testimonial and Partner sections removed as per client request --}}
@endsection

@section('script')
  @includeIf('frontend.partials.event-search-suggest')
@endsection

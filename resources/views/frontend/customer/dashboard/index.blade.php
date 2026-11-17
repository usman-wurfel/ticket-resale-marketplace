@extends('frontend.layout')
@section('pageHeading')
    @if (!empty($pageHeading))
        {{ $pageHeading->customer_dashboard_page_title ?? __('Dashboard') }}
    @else
        {{ __('Dashboard') }}
    @endif
@endsection
@section('hero-section')
    <!-- Page Banner Start -->
    <section class="page-banner overlay pt-120 pb-125 rpt-90 rpb-95 lazy"
        data-bg="{{ asset('assets/admin/img/' . $basicInfo->breadcrumb) }}">
        <div class="container">
            <div class="banner-inner">
                <h2 class="page-title">
                    @if (!empty($pageHeading))
                        {{ $pageHeading->customer_dashboard_page_title ?? __('Dashboard') }}
                    @else
                        {{ __('Dashboard') }}
                    @endif
                </h2>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('index') }}">{{ __('Home') }}</a></li>
                        <li class="breadcrumb-item active">
                            @if (!empty($pageHeading))
                                {{ $pageHeading->customer_dashboard_page_title ?? __('Dashboard') }}
                            @else
                                {{ __('Dashboard') }}
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
    <!--====== Start Dashboard Section ======-->
    <section class="user-dashbord">
        <div class="container">
            <div class="row">
                @includeIf('frontend.customer.partials.sidebar')
                <div class="col-lg-9">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="account-info">
                                <div class="title">
                                    <h4>{{ __('Bought tickets') }}</h4>
                                </div>
                                <div class="main-info d-flex align-items-center flex-wrap" style="gap: 12px;">
                                    <h5 class="mb-0">{{ $boughtTicketsCount ?? 0 }}</h5>
                                    @include('frontend.customer.partials.btn-action-modern', [
                                        'href' => route('customer.tickets.bought'),
                                        'title' => __('View'),
                                    ])
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="account-info">
                                <div class="title">
                                    <h4>{{ __('Sold tickets') }} <small class="text-muted font-weight-normal">({{ __('qty sold') }})</small></h4>
                                </div>
                                <div class="main-info d-flex align-items-center flex-wrap" style="gap: 12px;">
                                    <h5 class="mb-0">{{ $soldTicketsQuantity ?? 0 }}</h5>
                                    @include('frontend.customer.partials.btn-action-modern', [
                                        'href' => route('customer.tickets.sold'),
                                        'title' => __('View'),
                                    ])
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-lg-12">
                            <div class="account-info">
                                <div class="title">
                                    <h4>{{ __('Recent purchases') }}</h4>
                                </div>
                                <div class="main-info">
                                    <div class="main-table">
                                        <div class="table-responsiv">
                                            <table id="dashboard-recent-purchases"
                                                class="table table-striped w-100">
                                                <thead>
                                                    <tr>
                                                        <th>{{ __('Event') }}</th>
                                                        <th>{{ __('Source') }}</th>
                                                        <th>{{ __('Event date') }}</th>
                                                        <th>{{ __('Status') }}</th>
                                                        <th class="text-center">{{ __('View') }}</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @forelse ($recentPurchases as $row)
                                                        @if ($row['kind'] === 'platform')
                                                            @php
                                                                $item = $row['booking'];
                                                                $platformStatus = boughtTicketDisplayStatus('platform', $item);
                                                                $event = $item
                                                                    ->event()
                                                                    ->where('language_id', $currentLanguageInfo->id)
                                                                    ->select('title', 'slug', 'event_id')
                                                                    ->first();
                                                                if (empty($event)) {
                                                                    $language = App\Models\Language::where('is_default', 1)->first();
                                                                    $event = $item
                                                                        ->event()
                                                                        ->where('language_id', $language->id)
                                                                        ->select('title', 'slug', 'event_id')
                                                                        ->first();
                                                                }
                                                            @endphp
                                                            @if (!empty($event))
                                                                <tr>
                                                                    <td>
                                                                        <a target="_blank"
                                                                            href="{{ route('event.details', ['slug' => $event->slug, 'id' => $event->event_id]) }}">{{ strlen($event->title) > 30 ? mb_substr($event->title, 0, 30) . '...' : $event->title }}</a>
                                                                    </td>
                                                                    <td><span class="badge badge-success">{{ __('Platform') }}</span></td>
                                                                    <td>
                                                                        @if ($item->event_date)
                                                                            {{ \Carbon\Carbon::parse($item->event_date)->timezone($websiteInfo->timezone)->translatedFormat('D, M d, Y h:i a') }}
                                                                        @else
                                                                            —
                                                                        @endif
                                                                    </td>
                                                                    <td><span class="badge {{ $platformStatus['badge'] }}">{{ $platformStatus['label'] }}</span></td>
                                                                    <td class="text-center">
                                                                        @include('frontend.customer.partials.btn-action-modern', [
                                                                            'href' => route('customer.booking_details', ['id' => $item->id]) . '?from=bought',
                                                                            'title' => __('View'),
                                                                            'compact' => true,
                                                                        ])
                                                                    </td>
                                                                </tr>
                                                            @else
                                                                <tr>
                                                                    <td>— <small class="text-muted">#{{ $item->booking_id }}</small></td>
                                                                    <td><span class="badge badge-success">{{ __('Platform') }}</span></td>
                                                                    <td>
                                                                        @if ($item->event_date)
                                                                            {{ \Carbon\Carbon::parse($item->event_date)->timezone($websiteInfo->timezone)->translatedFormat('D, M d, Y h:i a') }}
                                                                        @else
                                                                            —
                                                                        @endif
                                                                    </td>
                                                                    <td><span class="badge {{ $platformStatus['badge'] }}">{{ $platformStatus['label'] }}</span></td>
                                                                    <td class="text-center">
                                                                        @include('frontend.customer.partials.btn-action-modern', [
                                                                            'href' => route('customer.booking_details', ['id' => $item->id]) . '?from=bought',
                                                                            'title' => __('View'),
                                                                            'compact' => true,
                                                                        ])
                                                                    </td>
                                                                </tr>
                                                            @endif
                                                        @else
                                                            @php
                                                                $r = $row['resale'];
                                                                $evModel = $row['eventModel'];
                                                                $resaleStatus = boughtTicketDisplayStatus('resale', $r);
                                                                $resaleEvent = App\Models\Event\EventContent::where('event_id', $r->event_id)
                                                                    ->where('language_id', $currentLanguageInfo->id)
                                                                    ->select('title', 'slug', 'event_id')
                                                                    ->first();
                                                                if (empty($resaleEvent)) {
                                                                    $resaleEvent = App\Models\Event\EventContent::where('event_id', $r->event_id)
                                                                        ->select('title', 'slug', 'event_id')
                                                                        ->first();
                                                                }
                                                            @endphp
                                                            <tr>
                                                                <td>
                                                                    @if (!empty($resaleEvent?->slug))
                                                                        <a target="_blank"
                                                                            href="{{ route('event.details', [$resaleEvent->slug, $r->event_id]) }}">{{ strlen($resaleEvent->title ?? '') > 30 ? mb_substr($resaleEvent->title, 0, 30) . '...' : $resaleEvent->title ?? '—' }}</a>
                                                                    @else
                                                                        {{ $resaleEvent->title ?? '—' }}
                                                                    @endif
                                                                </td>
                                                                <td><span class="badge badge-warning">{{ __('Resale') }}</span></td>
                                                                <td>
                                                                    @if ($evModel)
                                                                        {{ \Carbon\Carbon::parse($evModel->start_date . ' ' . $evModel->start_time)->timezone($websiteInfo->timezone)->translatedFormat('D, M d, Y h:i a') }}
                                                                    @else
                                                                        —
                                                                    @endif
                                                                </td>
                                                                <td><span class="badge {{ $resaleStatus['badge'] }}">{{ $resaleStatus['label'] }}</span></td>
                                                                <td class="text-center">
                                                                    @include('frontend.customer.partials.btn-action-modern', [
                                                                        'href' => route('customer.tickets.bought.resale', ['id' => $r->id]),
                                                                        'title' => __('View'),
                                                                        'compact' => true,
                                                                    ])
                                                                </td>
                                                            </tr>
                                                        @endif
                                                    @empty
                                                        <tr>
                                                            <td colspan="5" class="text-center text-muted">{{ __('No recent purchases.') }}</td>
                                                        </tr>
                                                    @endforelse
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <!--====== End Dashboard Section ======-->
@endsection

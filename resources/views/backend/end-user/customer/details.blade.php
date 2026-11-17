@extends('backend.layout')

@section('content')
    <div class="page-header">
        <h4 class="page-title">Buyer / Seller details</h4>
        <ul class="breadcrumbs">
            <li class="nav-home">
                <a href="{{ route('admin.dashboard') }}">
                    <i class="flaticon-home"></i>
                </a>
            </li>
            <li class="separator">
                <i class="flaticon-right-arrow"></i>
            </li>
            <li class="nav-item">
                <a href="#">Customer management</a>
            </li>
            <li class="separator">
                <i class="flaticon-right-arrow"></i>
            </li>
            <li class="nav-item">
                <a
                    href="{{ route('admin.organizer_management.registered_customer', ['language' => $defaultLang->code]) }}">Buyer / Seller</a>
            </li>
            <li class="separator">
                <i class="flaticon-right-arrow"></i>
            </li>
            <li class="nav-item">
                <a href="#">Buyer / Seller details</a>
            </li>
        </ul>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="row">
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <div class="row align-items-center">
                                <div class="col-lg-8">
                                    <div class="author">
                                        @if (!is_null($customer->photo))
                                            <img src="{{ asset('assets/admin/img/customer-profile/' . $customer->photo) }}"
                                                alt="" class="rounded-circle customer-img">
                                        @else
                                            <img src="{{ asset('assets/admin/img/blank_user.jpg') }}" alt=""
                                                class="rounded-circle customer-img">
                                        @endif

                                        <div class="h6 card-title">Buyer / Seller information</div>
                                    </div>
                                </div>
                                <div class="col-lg-4">
                                    <a class="btn btn-info btn-sm float-right d-inline-block mr-2"
                                        href="{{ route('admin.organizer_management.registered_customer', ['language' => $defaultLang->code]) }}">
                                        <span class="btn-label">
                                            <i class="fas fa-backward"></i>
                                        </span>
                                        {{ __('Back') }}
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div class="card-body">
                            <div class="payment-information">
                                <div class="row mb-2">
                                    <div class="col-lg-4">
                                        <strong>{{ __('Name') . ' :' }}</strong>
                                    </div>
                                    <div class="col-lg-8">
                                        {{ $customer->fname }} {{ $customer->lname }}
                                    </div>
                                </div>

                                <div class="row mb-2">
                                    <div class="col-lg-4">
                                        <strong>{{ __('Email') . ' :' }}</strong>
                                    </div>
                                    <div class="col-lg-8">
                                        {{ $customer->email }}
                                    </div>
                                </div>

                                <div class="row mb-2">
                                    <div class="col-lg-4">
                                        <strong>{{ __('Username') . ' :' }}</strong>
                                    </div>
                                    <div class="col-lg-8">
                                        {{ !empty($customer->username) ? $customer->username : '-' }}
                                    </div>
                                </div>

                                <div class="row mb-2">
                                    <div class="col-lg-4">
                                        <strong>{{ __('Phone') . ' :' }}</strong>
                                    </div>
                                    <div class="col-lg-8">
                                        {{ $customer->phone }}
                                    </div>
                                </div>

                                <div class="row mb-2">
                                    <div class="col-lg-4">
                                        <strong>{{ __('Country') . ' :' }}</strong>
                                    </div>
                                    <div class="col-lg-8">
                                        {{ $customer->country }}
                                    </div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col-lg-4">
                                        <strong>{{ __('City') . ' :' }}</strong>
                                    </div>
                                    <div class="col-lg-8">
                                        {{ $customer->city }}
                                    </div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col-lg-4">
                                        <strong>{{ __('State') . ' :' }}</strong>
                                    </div>
                                    <div class="col-lg-8">
                                        {{ $customer->state }}
                                    </div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col-lg-4">
                                        <strong>{{ __('Zip Code') . ' :' }}</strong>
                                    </div>
                                    <div class="col-lg-8">
                                        {{ $customer->zip_code }}
                                    </div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col-lg-4">
                                        <strong>{{ __('Gender') . ' :' }}</strong>
                                    </div>
                                    <div class="col-lg-8">
                                        {{ $customer->gender }}
                                    </div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col-lg-4">
                                        <strong>{{ __('Address') . ' :' }}</strong>
                                    </div>
                                    <div class="col-lg-8">
                                        {{ $customer->address }}
                                    </div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col-lg-4">
                                        <strong>{{ __('Confidence') . ' :' }}</strong>
                                    </div>
                                    <div class="col-lg-8">
                                        @php
                                            $riskLabel = $customer->resaleRiskLabelForDisplay();
                                            $riskBadge = match ($riskLabel) {
                                                'LOW RISK' => 'badge-success',
                                                'MEDIUM RISK' => 'badge-info',
                                                'HIGH RISK' => 'badge-warning',
                                                'BANNED' => 'badge-danger',
                                                default => 'badge-secondary',
                                            };
                                        @endphp
                                        <span class="badge {{ $riskBadge }}">{{ $riskLabel }}</span>
                                        @if (!empty($customer->confidence_score))
                                            <small class="text-muted">({{ number_format((float) $customer->confidence_score, 2) }})</small>
                                        @endif
                                    </div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col-lg-4">
                                        <strong>{{ __('Manual reinstatements') . ' :' }}</strong>
                                    </div>
                                    <div class="col-lg-8">
                                        {{ (int) ($customer->manual_reinstatement_count ?? 0) }}
                                    </div>
                                </div>
                                @if ($customer->confidence_level === \App\Models\Customer::CONFIDENCE_BANNED)
                                    <div class="row mb-2">
                                        <div class="col-lg-12">
                                            <form
                                                action="{{ route('admin.customer_management.customer.reinstate_banned', ['id' => $customer->id]) }}"
                                                method="POST"
                                                onsubmit="return confirm('{{ __('Reinstate this BANNED user? Account will be activated and confidence set to HIGH RISK.') }}');">
                                                @csrf
                                                <button type="submit" class="btn btn-warning btn-sm btn-block">
                                                    <i class="fas fa-user-check mr-1"></i>
                                                    {{ __('Reinstate BANNED account') }}
                                                </button>
                                            </form>
                                            <small class="text-muted d-block mt-1">
                                                {{ __('BANNED users are not reactivated automatically. Manual reinstatement sets confidence to HIGH RISK.') }}
                                            </small>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <div class="row">
                                <div class="col-lg-4">
                                    <div class="card-title">Ticket bookings</div>
                                </div>

                                <div class="col-lg-6 offset-lg-2">
                                    <button class="btn btn-danger btn-sm float-right d-none bulk-delete ml-3 mt-1"
                                        data-href="{{ route('admin.event_booking.bulk_delete') }}">
                                        <i class="flaticon-interface-5"></i> {{ __('Delete') }}
                                    </button>

                                    <form class="float-right ml-3"
                                        action="{{ route('admin.customer_management.customer_details', ['id' => $customer->id]) }}"
                                        method="GET">
                                        <input name="booking_id" type="text" class="form-control"
                                            placeholder="Search By Order ID"
                                            value="{{ !empty(request()->input('booking_id')) ? request()->input('booking_id') : '' }}">
                                    </form>

                                    <form id="searchByStatusForm" class="float-right d-flex flex-row align-items-center"
                                        action="{{ route('admin.event.booking') }}" method="GET">
                                        <label class="mr-2">{{ __('Payment') }}</label>
                                        <select class="form-control" name="status"
                                            onchange="document.getElementById('searchByStatusForm').submit()">
                                            <option value=""
                                                {{ empty(request()->input('status')) ? 'selected' : '' }}>
                                                {{ __('All') }}
                                            </option>
                                            <option value="completed"
                                                {{ request()->input('status') == 'completed' ? 'selected' : '' }}>
                                                {{ __('Completed') }}
                                            </option>
                                            <option value="pending"
                                                {{ request()->input('status') == 'pending' ? 'selected' : '' }}>
                                                {{ __('Pending') }}
                                            </option>
                                            <option value="rejected"
                                                {{ request()->input('status') == 'rejected' ? 'selected' : '' }}>
                                                {{ __('Rejected') }}
                                            </option>
                                        </select>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <div class="card-body">
                            <div class="row">
                                <div class="col-lg-12">
                                    @if (count($bookings) == 0 && (empty($resaleBookings) || count($resaleBookings) == 0))
                                        <h3 class="text-center mt-2">NO TICKET BOOKINGS FOUND!</h3>
                                    @else
                                        <div class="table-responsive">
                                            <table class="table table-striped mt-3">
                                                <thead>
                                                    <tr>
                                                        <th scope="col">
                                                            <input type="checkbox" class="bulk-check" data-val="all">
                                                        </th>
                                                        <th scope="col">{{ __('Booking ID') }}</th>
                                                        <th scope="col">{{ __('Ticket') }}</th>
                                                        <th scope="col">{{ __('Username') }}</th>
                                                        <th scope="col">{{ __('Paid via') }}</th>
                                                        <th scope="col">{{ __('Payment Status') }}</th>
                                                        <th scope="col">{{ __('Actions') }}</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach ($bookings as $booking)
                                                        <tr>
                                                            <td>
                                                                <input type="checkbox" class="bulk-check"
                                                                    data-val="{{ $booking->id }}">
                                                            </td>
                                                            <td>{{ '#' . $booking->booking_id }}</td>

                                                            @php
                                                                $eventInfo = \App\Models\Event\EventContent::where('language_id', $defaultLang->id)->first();
                                                                $title = $eventInfo->title;
                                                                $slug = $eventInfo->slug;
                                                            @endphp

                                                            <td>
                                                                <a href="{{ route('event.details', ['slug' => $slug, 'id' => $eventInfo->event_id]) }}"
                                                                    target="_blank">
                                                                    {{ strlen($title) > 35 ? mb_substr($title, 0, 35, 'utf-8') . '...' : $title }}
                                                                </a>
                                                            </td>

                                                            @php
                                                                $customer = $booking->customerInfo()->first();
                                                            @endphp

                                                            <td>{{ $customer->fname }} {{ $customer->lname }}</td>
                                                            <td>{{ !is_null($booking->paymentMethod) ? ($booking->paymentMethod === 'pay_at_event' ? 'Online booking' : $booking->paymentMethod) : '-' }}
                                                            </td>
                                                            <td>
                                                                @if ($booking->gatewayType == 'online')
                                                                    <h2 class="d-inline-block"><span
                                                                            class="badge badge-success">{{ __('Completed') }}</span>
                                                                    </h2>
                                                                @elseif ($booking->gatewayType == 'offline')
                                                                    <form id="paymentStatusForm-{{ $booking->id }}"
                                                                        class="d-inline-block"
                                                                        action="{{ route('admin.event_booking.update_payment_status', $booking->id) }}"
                                                                        method="post">
                                                                        @csrf
                                                                        <select
                                                                            class="form-control form-control-sm @if ($booking->paymentStatus == 'completed') bg-success @elseif ($booking->paymentStatus == 'pending') bg-warning text-dark @else bg-danger @endif"
                                                                            name="payment_status"
                                                                            onchange="document.getElementById('paymentStatusForm-{{ $booking->id }}').submit()">
                                                                            <option value="completed"
                                                                                {{ $booking->paymentStatus == 'completed' ? 'selected' : '' }}>
                                                                                {{ __('Completed') }}
                                                                            </option>
                                                                            <option value="pending"
                                                                                {{ $booking->paymentStatus == 'pending' ? 'selected' : '' }}>
                                                                                {{ __('Pending') }}
                                                                            </option>
                                                                            <option value="rejected"
                                                                                {{ $booking->paymentStatus == 'rejected' ? 'selected' : '' }}>
                                                                                {{ __('Rejected') }}
                                                                            </option>
                                                                        </select>
                                                                    </form>
                                                                @else
                                                                    -
                                                                @endif
                                                            </td>
                                                            <td>
                                                                <div class="dropdown">
                                                                    <button
                                                                        class="btn btn-secondary btn-sm dropdown-toggle"
                                                                        type="button" id="dropdownMenuButton"
                                                                        data-toggle="dropdown" aria-haspopup="true"
                                                                        aria-expanded="false">
                                                                        {{ __('Select') }}
                                                                    </button>

                                                                    <div class="dropdown-menu"
                                                                        aria-labelledby="dropdownMenuButton">
                                                                        <a href="{{ route('admin.event_booking.details', ['id' => $booking->id]) }}"
                                                                            class="dropdown-item">
                                                                            {{ __('Details') }}
                                                                        </a>

                                                                        <a href="{{ asset('assets/admin/file/invoices/' . $booking->invoice) }}"
                                                                            class="dropdown-item" target="_blank">
                                                                            {{ __('Invoice') }}
                                                                        </a>

                                                                        <form class="deleteForm d-block"
                                                                            action="{{ route('admin.event_booking.delete', ['id' => $booking->id]) }}"
                                                                            method="post">

                                                                            @csrf
                                                                            <button type="submit" class="deleteBtn">
                                                                                {{ __('Delete') }}
                                                                            </button>
                                                                        </form>
                                                                    </div>
                                                                </div>
                                                            </td>
                                                        </tr>

                                                    @endforeach

                                                    @if (!empty($resaleBookings))
                                                        @foreach ($resaleBookings as $resale)
                                                            @php
                                                                $eventInfo = \App\Models\Event\EventContent::where('language_id', $defaultLang->id)
                                                                    ->where('event_id', $resale->event_id)
                                                                    ->first();
                                                                if (is_null($eventInfo)) {
                                                                    $eventInfo = \App\Models\Event\EventContent::where('event_id', $resale->event_id)->first();
                                                                }
                                                                $title = $eventInfo ? $eventInfo->title : '-';
                                                                $slug = $eventInfo ? $eventInfo->slug : '';
                                                                $sellerName = trim(($resale->seller->fname ?? '') . ' ' . ($resale->seller->lname ?? ''));
                                                                if ($sellerName === '') {
                                                                    $sellerName = $resale->seller->username ?? '-';
                                                                }
                                                                $resaleStatus = strtolower((string) $resale->status);
                                                            @endphp
                                                            <tr>
                                                                <td>-</td>
                                                                <td>{{ $resale->booking_id ? '#' . $resale->booking_id : '-' }}</td>
                                                                <td>
                                                                    @if ($eventInfo)
                                                                        <a href="{{ route('event.details', ['slug' => $slug, 'id' => $eventInfo->event_id]) }}"
                                                                            target="_blank">
                                                                            {{ strlen($title) > 35 ? mb_substr($title, 0, 35, 'utf-8') . '...' : $title }}
                                                                        </a>
                                                                    @else
                                                                        -
                                                                    @endif
                                                                </td>
                                                                <td>
                                                                    <span class="badge badge-warning">{{ $sellerName }}</span>
                                                                </td>
                                                                <td>{{ __('Resale') }}</td>
                                                                <td>
                                                                    @if ($resaleStatus === 'sold')
                                                                        <span class="badge badge-success">{{ __('Completed') }}</span>
                                                                    @elseif ($resaleStatus === 'reserved')
                                                                        <span class="badge badge-warning">{{ __('Pending') }}</span>
                                                                    @elseif ($resaleStatus === 'cancelled')
                                                                        <span class="badge badge-danger">{{ __('Rejected') }}</span>
                                                                    @else
                                                                        <span class="badge badge-secondary">{{ ucfirst($resale->status) }}</span>
                                                                    @endif
                                                                </td>
                                                                <td>-</td>
                                                            </tr>
                                                        @endforeach
                                                    @endif
                                                </tbody>
                                            </table>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="card-footer text-center">
                            <div class="d-inline-block mt-3">
                                {{ $bookings->appends([
                                        'booking_id' => request()->input('booking_id'),
                                        'status' => request()->input('status'),
                                    ])->links() }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mt-4">
                <div class="col-lg-12">
                    <div class="card mb-4">
                        <div class="card-header">
                            <div class="card-title">{{ __('Seller summary') }}</div>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3"><strong>{{ __('Sold') }}:</strong> {{ (int) ($soldQty ?? 0) }}</div>
                                <div class="col-md-3"><strong>{{ __('On hold') }}:</strong> {{ (int) ($onHoldQty ?? 0) }}</div>
                                <div class="col-md-3"><strong>{{ __('In hand') }}:</strong> {{ (int) ($inHandQty ?? 0) }}</div>
                                <div class="col-md-3"><strong>{{ __('Earnings') }}:</strong> {{ number_format((float) ($earnings ?? 0), 2) }}</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-12">
                    <div class="card mb-4">
                        <div class="card-header">
                            <div class="card-title">{{ __('Active listings') }}</div>
                        </div>
                        <div class="card-body">
                            @if (empty($listings) || count($listings) === 0)
                                <h6 class="text-center mt-2 mb-0">{{ __('NO ACTIVE LISTINGS FOUND!') }}</h6>
                            @else
                                <div class="table-responsive">
                                    <table class="table table-striped mt-2">
                                        <thead>
                                            <tr>
                                                <th>{{ __('Event') }}</th>
                                                <th>{{ __('Price') }}</th>
                                                <th>{{ __('Qty') }}</th>
                                                <th>{{ __('Status') }}</th>
                                                <th>{{ __('Sold') }}</th>
                                                <th>{{ __('In hand') }}</th>
                                                <th>{{ __('PDFs') }}</th>
                                                <th>{{ __('Actions') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($listings as $ticket)
                                                @php
                                                    $st = $listingStats[$ticket->id] ?? ['sold_qty' => 0, 'in_hand_qty' => 0, 'show_pdf' => false, 'pdf_count' => 0];
                                                    $eventTitle = optional($ticket->eventContent)->title ?: ('Event #' . $ticket->event_id);
                                                @endphp
                                                <tr>
                                                    <td>{{ $eventTitle }}</td>
                                                    <td>{{ $ticket->price }}</td>
                                                    <td>{{ $ticket->quantity }}</td>
                                                    <td><span class="badge badge-secondary">{{ ucfirst($ticket->status) }}</span></td>
                                                    <td>{{ (int) $st['sold_qty'] }}</td>
                                                    <td>{{ (int) $st['in_hand_qty'] }}</td>
                                                    <td>{{ (int) $st['pdf_count'] }}</td>
                                                    <td>
                                                        @if (!empty($st['show_pdf']))
                                                            <a class="btn btn-sm btn-info" title="View PDFs"
                                                                href="{{ route('admin.event_management.event.reseller_ticket_pdfs', ['id' => $ticket->event_id, 'ticketId' => $ticket->id]) }}">
                                                                <i class="fas fa-file-pdf"></i>
                                                            </a>
                                                        @endif
                                                        <form class="d-inline-block" method="POST"
                                                            action="{{ route('admin.event_management.event.delete_reseller_ticket', ['id' => $ticket->event_id, 'ticketId' => $ticket->id]) }}"
                                                            onsubmit="return confirm('Are you sure you want to remove this reseller ticket?');">
                                                            @csrf
                                                            <button class="btn btn-sm btn-danger" type="submit" title="Delete">
                                                                <i class="fas fa-trash-alt"></i>
                                                            </button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-header">
                            <div class="card-title">{{ __('Sold Tickets (Resale)') }}</div>
                        </div>
                        <div class="card-body">
                            @if (empty($resaleSoldTickets) || count($resaleSoldTickets) === 0)
                                <h5 class="text-center mt-2">{{ __('NO SOLD TICKETS FOUND!') }}</h5>
                            @else
                                <div class="table-responsive">
                                    <table class="table table-striped mt-2">
                                        <thead>
                                            <tr>
                                                <th>{{ __('Booking ID') }}</th>
                                                <th>{{ __('Ticket') }}</th>
                                                <th>{{ __('Buyer') }}</th>
                                                <th>{{ __('Qty') }}</th>
                                                <th>{{ __('Amount') }}</th>
                                                <th>{{ __('Date') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($resaleSoldTickets as $sold)
                                                @php
                                                    $soldEvent = \App\Models\Event\EventContent::where('language_id', $defaultLang->id)
                                                        ->where('event_id', $sold->event_id)
                                                        ->first();
                                                    if (is_null($soldEvent)) {
                                                        $soldEvent = \App\Models\Event\EventContent::where('event_id', $sold->event_id)->first();
                                                    }
                                                    $buyerName = trim(($sold->buyer->fname ?? '') . ' ' . ($sold->buyer->lname ?? ''));
                                                    if ($buyerName === '') {
                                                        $buyerName = $sold->buyer->username ?? '-';
                                                    }
                                                @endphp
                                                <tr>
                                                    <td>{{ $sold->booking_id ? '#' . $sold->booking_id : '-' }}</td>
                                                    <td>
                                                        @if ($soldEvent)
                                                            <a href="{{ route('event.details', ['slug' => $soldEvent->slug, 'id' => $soldEvent->event_id]) }}"
                                                                target="_blank">
                                                                {{ strlen($soldEvent->title) > 35 ? mb_substr($soldEvent->title, 0, 35, 'utf-8') . '...' : $soldEvent->title }}
                                                            </a>
                                                        @else
                                                            -
                                                        @endif
                                                    </td>
                                                    <td>{{ $buyerName }}</td>
                                                    <td>{{ (int) $sold->quantity }}</td>
                                                    @php
                                                        $netAmount = (float) ($resaleSoldTicketNetAmounts[$sold->id] ?? ((float) $sold->price * (int) $sold->quantity));
                                                    @endphp
                                                    <td>{{ number_format($netAmount, 2) }}</td>
                                                    <td>{{ \Carbon\Carbon::parse($sold->created_at)->format('d M Y h:i A') }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

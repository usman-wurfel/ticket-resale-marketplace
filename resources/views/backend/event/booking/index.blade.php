@extends('backend.layout')

@section('style')
  <link rel="stylesheet" href="{{ asset('assets/front/css/daterangepicker.css') }}">
@endsection

@section('content')
  <div class="page-header">
    <h4 class="page-title">Ticket bookings</h4>
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
        <a href="#">Ticket bookings</a>
      </li>
      @if (!request()->filled('status'))
        <li class="separator">
          <i class="flaticon-right-arrow"></i>
        </li>
        <li class="nav-item">
          <a href="#">{{ __('All Bookings') }}</a>
        </li>
      @endif
      @if (request()->filled('status') && request()->input('status') == 'reserved')
        <li class="separator">
          <i class="flaticon-right-arrow"></i>
        </li>
        <li class="nav-item">
          <a href="#">{{ __('Reserved Bookings') }}</a>
        </li>
      @endif
      @if (request()->filled('status') && request()->input('status') == 'on_hold')
        <li class="separator">
          <i class="flaticon-right-arrow"></i>
        </li>
        <li class="nav-item">
          <a href="#">{{ __('On hold Bookings') }}</a>
        </li>
      @endif
      @if (request()->filled('status') && request()->input('status') == 'rejected')
        <li class="separator">
          <i class="flaticon-right-arrow"></i>
        </li>
        <li class="nav-item">
          <a href="#">{{ __('Rejected Bookings') }}</a>
        </li>
      @endif

    </ul>
  </div>

  <div class="row">
    <div class="col-md-12">
      <div class="card">
        <div class="card-header">
          <div class="row">
            <div class="col-lg-3">
              <div class="card-title">Ticket bookings</div>
            </div>

            <div class="col-lg-9 mt-2 mt-lg-0">
              @php
                $bookingDateRangeValue = '';
                if (request()->filled('from_date') && request()->filled('to_date')) {
                    $bookingDateRangeValue = request()->input('from_date') . ' - ' . request()->input('to_date');
                }
              @endphp
              <div class="d-flex flex-nowrap align-items-center justify-content-lg-end" style="gap:8px;">
                <form action="{{ route('admin.event.booking') }}" method="GET" class="d-flex flex-nowrap align-items-center js-range-form mb-0" style="gap:8px;">
                  <input name="booking_id" type="text" class="form-control"
                    placeholder="{{ __('Search By Booking Id') }}"
                    value="{{ !empty(request()->input('booking_id')) ? request()->input('booking_id') : '' }}" style="width:180px;">

                  <input name="event_title" type="text" class="form-control"
                    placeholder="Search by ticket title"
                    value="{{ !empty(request()->input('event_title')) ? request()->input('event_title') : '' }}" style="width:190px;">

                  <select class="form-control" name="status" style="width:130px;">
                    <option value="" {{ empty(request()->input('status')) ? 'selected' : '' }}>
                      {{ __('Payment: All') }}
                    </option>
                    <option value="reserved" {{ request()->input('status') == 'reserved' ? 'selected' : '' }}>
                      {{ __('Reserved') }}
                    </option>
                    <option value="on_hold" {{ request()->input('status') == 'on_hold' ? 'selected' : '' }}>
                      {{ __('On hold') }}
                    </option>
                    <option value="rejected" {{ request()->input('status') == 'rejected' ? 'selected' : '' }}>
                      {{ __('Rejected') }}
                    </option>
                  </select>

                  <input type="text" class="form-control js-date-range" value="{{ $bookingDateRangeValue }}"
                    placeholder="YYYY-MM-DD - YYYY-MM-DD" autocomplete="off" style="width:210px;">
                  <input type="hidden" name="from_date" value="{{ request()->input('from_date') }}" class="js-from-date">
                  <input type="hidden" name="to_date" value="{{ request()->input('to_date') }}" class="js-to-date">
                  <button type="submit" class="btn btn-primary btn-sm">{{ __('Filter') }}</button>
                </form>

                <form action="{{ route('admin.event.booking.export_list') }}" method="GET" class="d-inline-block">
                  <input type="hidden" name="booking_id" value="{{ request()->input('booking_id') }}">
                  <input type="hidden" name="event_title" value="{{ request()->input('event_title') }}">
                  <input type="hidden" name="status" value="{{ request()->input('status') }}">
                  <input type="hidden" name="from_date" value="{{ request()->input('from_date') }}">
                  <input type="hidden" name="to_date" value="{{ request()->input('to_date') }}">
                  <button type="submit" class="btn btn-success btn-sm" title="CSV Format">{{ __('Export CSV') }}</button>
                </form>

                <button class="btn btn-danger btn-sm d-none bulk-delete"
                  data-href="{{ route('admin.event_booking.bulk_delete') }}">
                  <i class="flaticon-interface-5"></i> {{ __('Delete') }}
                </button>
              </div>
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
                        <th scope="col" width="25%">{{ __('Ticket') }}</th>
                        <th scope="col">Sellers</th>
                        <th scope="col">{{ __('Customer') }}</th>
                        <th scope="col">{{ __('Cust. Paid') }}</th>
                        <th scope="col">{{ __('Seller Amount') }}</th>
                        <th scope="col">{{ __('Admin Amount') }}</th>
                        <th scope="col">{{ __('Payment Status') }}</th>
                        <th scope="col">{{ __('Actions') }}</th>
                      </tr>
                    </thead>
                    <tbody>
                      @foreach ($bookings as $booking)
                        <tr>
                          <td>
                            <input type="checkbox" class="bulk-check" data-val="{{ $booking->id }}">
                          </td>
                          <td>{{ '#' . $booking->booking_id }}</td>
                          @php
                            $eventInfo = \App\Models\Event\EventContent::where('language_id', $defaultLang->id)
                                ->where('event_id', $booking->event_id)
                                ->first();
                            if (is_null($eventInfo)) {
                                $eventInfo = \App\Models\Event\EventContent::where(
                                    'event_id',
                                    $booking->event_id,
                                )->first();
                            }
                            $title = $eventInfo ? $eventInfo->title : '';
                            $slug = $eventInfo ? $eventInfo->slug : '';
                          @endphp

                          <td>
                            @if ($eventInfo)
                              <a href="{{ route('event.details', ['slug' => $slug, 'id' => $eventInfo ? $eventInfo->event_id : 0]) }}"
                                target="_blank">
                                {{ strlen($title) > 30 ? mb_substr($title, 0, 30, 'utf-8') . '...' : $title }}
                              </a>
                            @else
                              {{ '-' }}
                            @endif

                          </td>

                          <td>
                            @if ($booking->organizer)
                              <a target="_blank"
                                href="{{ route('admin.organizer_management.organizer_details', ['id' => $booking->organizer_id, 'language' => $defaultLang->code]) }}">{{ strlen($booking->organizer->username) > 20 ? mb_substr($booking->organizer->username, 0, 20, 'UTF-8') . '....' : $booking->organizer->username }}</a>
                            @else
                              <span class="badge badge-success">{{ __('Admin') }}</span>
                            @endif
                          </td>

                          @php
                            $customer = $booking->customerInfo()->first();
                          @endphp

                          <td>
                            @if ($customer)
                              <a
                                href="{{ route('admin.customer_management.customer_details', ['id' => $customer->id, 'language' => $defaultLang->code]) }}">{{ $customer->fname }}
                                {{ $customer->lname }}</a>
                            @else
                              @if($booking->customer_id == 'guest')
                                {{ __('Guest') }}
                              @else
                                {{ '-' }}
                              @endif
                            @endif
                          </td>
                          @php
                            $position = $booking->currencyTextPosition;
                            $symbol = $booking->currencySymbol;
                          @endphp

                          <td>
                            {{ $position == 'left' ? $symbol . ' ' : '' }}{{ $booking->price + $booking->tax }}{{ $position == 'right' ? ' ' . $symbol : '' }}
                          </td>
                          <td>
                            @php
                              $platformSellerAmount = null;
                              if (!empty($booking->organizer_id)) {
                                  $platformSellerAmount = ((float) $booking->price - (float) ($booking->commission ?? 0));
                              }
                            @endphp
                            @if (is_null($platformSellerAmount))
                              -
                            @else
                              {{ $position == 'left' ? $symbol . ' ' : '' }}{{ number_format($platformSellerAmount, 2) }}{{ $position == 'right' ? ' ' . $symbol : '' }}
                            @endif
                          </td>
                          <td>
                            @php
                              $platformAdminAmount = !empty($booking->organizer_id)
                                  ? ((float) ($booking->commission ?? 0) + (float) ($booking->tax ?? 0))
                                  : ((float) $booking->price + (float) ($booking->tax ?? 0));
                            @endphp
                            {{ $position == 'left' ? $symbol . ' ' : '' }}{{ number_format($platformAdminAmount, 2) }}{{ $position == 'right' ? ' ' . $symbol : '' }}
                          </td>
                          <td>
                            @php
                              $paymentStatus = strtolower(trim((string) $booking->paymentStatus));
                              $isOnHold = in_array($paymentStatus, ['reserved', 'pending', 'on_hold'], true);
                              $isReserved = in_array($paymentStatus, ['completed', '1'], true) || $booking->paymentStatus === 1;
                              $canReviewStatus = !$isReserved && $paymentStatus !== 'rejected' && $paymentStatus !== 'free';
                            @endphp

                            @if ($isReserved || $booking->gatewayType == 'online')
                              <span class="badge badge-success">{{ __('Reserved') }}</span>
                            @elseif ($canReviewStatus)
                              <span class="badge badge-warning">{{ __('On hold') }}</span>
                            @elseif ($paymentStatus === 'rejected')
                              <span class="badge badge-danger">{{ __('Rejected') }}</span>
                            @elseif ($paymentStatus === 'free')
                              <span class="badge badge-primary">{{ __('Free') }}</span>
                            @else
                              -
                            @endif
                          </td>
                          <td>
                            <div class="dropdown">
                              <button class="btn btn-secondary btn-sm dropdown-toggle" type="button"
                                id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true"
                                aria-expanded="false">
                                {{ __('Select') }}
                              </button>

                              <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                                @if ($canReviewStatus)
                                  <a href="#" class="dropdown-item text-success"
                                    onclick="event.preventDefault(); document.getElementById('confirm-booking-form-{{ $booking->id }}').submit();">
                                    {{ __('Confirm') }}
                                  </a>
                                  <a href="#" class="dropdown-item text-danger"
                                    onclick="event.preventDefault(); document.getElementById('reject-booking-form-{{ $booking->id }}').submit();">
                                    {{ __('Reject') }}
                                  </a>
                                  <div class="dropdown-divider"></div>
                                @endif
                                <a href="{{ route('admin.event_booking.details', ['id' => $booking->id]) }}"
                                  class="dropdown-item">
                                  {{ __('Details') }}
                                </a>

                                @if (!is_null($booking->attachmentFile))
                                  <a href="#" data-toggle="modal"
                                    data-target="#attachmentModal-{{ $booking->id }}" class="dropdown-item"
                                    target="_blank">
                                    {{ __('Attachment') }}
                                  </a>
                                @endif
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
                            @if ($canReviewStatus)
                              <form id="confirm-booking-form-{{ $booking->id }}" class="d-none"
                                action="{{ route('admin.event_booking.update_payment_status', $booking->id) }}"
                                method="post">
                                @csrf
                                <input type="hidden" name="payment_status" value="completed">
                              </form>
                              <form id="reject-booking-form-{{ $booking->id }}" class="d-none"
                                action="{{ route('admin.event_booking.update_payment_status', $booking->id) }}"
                                method="post">
                                @csrf
                                <input type="hidden" name="payment_status" value="rejected">
                              </form>
                            @endif
                          </td>
                        </tr>

                        @includeIf('backend.event.booking.show-attachment')
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
                            $title = $eventInfo ? $eventInfo->title : '';
                            $slug = $eventInfo ? $eventInfo->slug : '';

                            $sellerLabel = trim(($resale->seller->fname ?? '') . ' ' . ($resale->seller->lname ?? ''));
                            if ($sellerLabel === '') {
                                $sellerLabel = $resale->seller->username ?? '-';
                            }

                            $buyerLabel = trim(($resale->buyer->fname ?? '') . ' ' . ($resale->buyer->lname ?? ''));
                            if ($buyerLabel === '') {
                                $buyerLabel = $resale->buyer->username ?? '-';
                            }

                            $resaleStatus = strtolower((string) $resale->status);
                          @endphp
                          <tr>
                            <td>-</td>
                            <td>{{ $resale->booking_id ? '#' . $resale->booking_id : '-' }}</td>
                            <td>
                              @if ($eventInfo)
                                <a href="{{ route('event.details', ['slug' => $slug, 'id' => $eventInfo ? $eventInfo->event_id : 0]) }}"
                                  target="_blank">
                                  {{ strlen($title) > 30 ? mb_substr($title, 0, 30, 'utf-8') . '...' : $title }}
                                </a>
                              @else
                                -
                              @endif
                            </td>
                            <td>
                              @if (!empty($resale->seller))
                                <a href="{{ route('admin.customer_management.customer_details', ['id' => $resale->seller->id, 'language' => $defaultLang->code]) }}">
                                  <span class="badge badge-warning">{{ $sellerLabel }}</span>
                                </a>
                              @else
                                <span class="badge badge-warning">{{ $sellerLabel }}</span>
                              @endif
                            </td>
                            <td>
                              @if (!empty($resale->buyer))
                                <a href="{{ route('admin.customer_management.customer_details', ['id' => $resale->buyer->id, 'language' => $defaultLang->code]) }}">
                                  {{ $buyerLabel }}
                                </a>
                              @else
                                {{ $buyerLabel }}
                              @endif
                            </td>
                            <td>
                              {{ number_format(((float) $resale->price * (int) $resale->quantity), 2) }}
                              <small class="text-muted d-block">{{ __('Qty') }}: {{ (int) $resale->quantity }}</small>
                            </td>
                            <td>
                              @php
                                $resaleTransaction = $resaleTransactionMap[$resale->id] ?? null;
                                $resaleSellerAmount = null;
                                if (!empty($resaleTransaction)) {
                                    $resaleSellerAmount = (float) ($resaleTransaction['grand_total'] ?? 0) - (float) ($resaleTransaction['commission'] ?? 0);
                                }
                              @endphp
                              @if (is_null($resaleSellerAmount))
                                -
                              @else
                                {{ number_format($resaleSellerAmount, 2) }}
                              @endif
                            </td>
                            <td>
                              @if (!empty($resaleTransaction))
                                {{ number_format((float) ($resaleTransaction['commission'] ?? 0), 2) }}
                              @else
                                -
                              @endif
                            </td>
                            <td>
                              @if ($resaleStatus === 'sold')
                                <span class="badge badge-success">{{ __('Reserved') }}</span>
                              @elseif ($resaleStatus === 'reserved')
                                <span class="badge badge-warning">{{ __('On hold') }}</span>
                              @elseif ($resaleStatus === 'cancelled')
                                <span class="badge badge-danger">{{ __('Rejected') }}</span>
                              @else
                                <span class="badge badge-secondary">{{ ucfirst($resale->status) }}</span>
                              @endif
                            </td>
                            <td><span class="text-muted">-</span></td>
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
                    'event_title' => request()->input('event_title'),
                    'status' => request()->input('status'),
                    'from_date' => request()->input('from_date'),
                    'to_date' => request()->input('to_date'),
                ])->links() }}
          </div>
        </div>
      </div>
    </div>
  </div>
@endsection

@section('script')
  <script src="{{ asset('assets/front/js/moment.min.js') }}"></script>
  <script src="{{ asset('assets/front/js/daterangepicker.min.js') }}"></script>
  <script>
    (function () {
      var $form = $('.js-range-form');
      if (!$form.length) return;
      var $range = $form.find('.js-date-range');
      var $from = $form.find('.js-from-date');
      var $to = $form.find('.js-to-date');

      $range.daterangepicker({
        autoUpdateInput: false,
        locale: { cancelLabel: 'Clear', format: 'YYYY-MM-DD' }
      });

      if ($from.val() && $to.val()) {
        $range.val($from.val() + ' - ' + $to.val());
      }

      $range.on('apply.daterangepicker', function (ev, picker) {
        $from.val(picker.startDate.format('YYYY-MM-DD'));
        $to.val(picker.endDate.format('YYYY-MM-DD'));
        $range.val($from.val() + ' - ' + $to.val());
      });

      $range.on('cancel.daterangepicker', function () {
        $from.val('');
        $to.val('');
        $range.val('');
      });
    })();
  </script>
@endsection

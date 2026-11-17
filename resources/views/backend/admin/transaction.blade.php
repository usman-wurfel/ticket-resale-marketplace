@extends('backend.layout')

@section('style')
  <link rel="stylesheet" href="{{ asset('assets/front/css/daterangepicker.css') }}">
@endsection

@section('content')
  <div class="page-header">
    <h4 class="page-title">{{ __('Transactions') }}</h4>
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
        <a href="#">{{ __('Transactions') }}</a>
      </li>
    </ul>
  </div>

  <div class="row">
    <div class="col-md-12">
      <div class="card">
        <div class="card-header">
          <div class="row">
            <div class="col-lg-4">
              <div class="card-title d-inline-block">{{ __('Transactions') }}</div>
            </div>

            <div class="col-lg-8 mt-2 mt-lg-0">
              @php
                $transactionDateRangeValue = '';
                if (request()->filled('from_date') && request()->filled('to_date')) {
                    $transactionDateRangeValue = request()->input('from_date') . ' - ' . request()->input('to_date');
                }
              @endphp
              <div class="d-flex flex-nowrap align-items-center justify-content-lg-end" style="gap:8px;">
                <form action="" method="get" class="d-flex flex-nowrap align-items-center js-range-form mb-0" style="gap:8px;">
                  <input type="text" value="{{ request()->input('transcation_id') }}" name="transcation_id"
                    placeholder="Enter Transaction Id" class="form-control" style="width: 220px;">
                  <span class="align-self-center font-weight-bold">{{ __('Date Range') }}</span>
                  <input type="text" class="form-control js-date-range" value="{{ $transactionDateRangeValue }}"
                    placeholder="YYYY-MM-DD - YYYY-MM-DD" autocomplete="off" style="width: 230px;">
                  <input type="hidden" name="from_date" value="{{ request()->input('from_date') }}" class="js-from-date">
                  <input type="hidden" name="to_date" value="{{ request()->input('to_date') }}" class="js-to-date">
                  <button type="submit" class="btn btn-primary btn-sm">{{ __('Apply') }}</button>
                </form>

                <form action="{{ route('admin.transcation.export') }}" method="GET" class="d-inline-block">
                  <input type="hidden" name="transcation_id" value="{{ request()->input('transcation_id') }}">
                  <input type="hidden" name="from_date" value="{{ request()->input('from_date') }}">
                  <input type="hidden" name="to_date" value="{{ request()->input('to_date') }}">
                  <button type="submit" class="btn btn-success btn-sm" title="CSV Format">{{ __('Export CSV') }}</button>
                </form>
              </div>
            </div>
          </div>
        </div>

        <div class="card-body">
          <div class="row">
            <div class="col-lg-12">
              @if (count($transcations) == 0)
                <h3 class="text-center mt-3">{{ __('NO TRANSACTION FOUND') . '!' }}</h3>
              @else
                <div class="table-responsive">
                  <table class="table table-striped mt-3">
                    <thead>
                      <tr>
                        <th scope="col">{{ __('Transaction Id') }}</th>
                        <th scope="col">{{ __('Seller') }}</th>
                        <th scope="col">{{ __('Transaction Type') }}</th>
                        <th scope="col">{{ __('Payment Method') }}</th>
                        <th scope="col">{{ __('Amount') }}</th>
                        <th scope="col">{{ __('Commission') }}</th>
                        <th scope="col">{{ __('Seller Amount') }}</th>
                        <th scope="col">{{ __('Status') }}</th>
                        <th scope="col">{{ __('Actions') }}</th>
                      </tr>
                    </thead>
                    <tbody>
                      @foreach ($transcations as $transcation)
                        <tr>
                          <td>#{{ $transcation->transcation_id }}</td>
                          <td>
                            @php
                              $organizer = $transcation->organizer()->first();
                              $seller = \App\Models\Customer::where('id', $transcation->customer_id)->first();
                            @endphp
                            @if ($organizer)
                              <a target="_blank"
                                href="{{ route('admin.organizer_management.organizer_details', ['id' => $organizer->id, 'language' => $defaultLang->code]) }}">{{ $organizer->username }}</a>
                            @elseif ($seller)
                              <a target="_blank"
                                href="{{ route('admin.customer_management.customer_details', ['id' => $seller->id, 'language' => $defaultLang->code]) }}">
                                {{ trim(($seller->fname ?? '') . ' ' . ($seller->lname ?? '')) !== '' ? trim(($seller->fname ?? '') . ' ' . ($seller->lname ?? '')) : ($seller->username ?? '-') }}
                              </a>
                            @else
                              <span class="badge badge-success">{{ __('Admin') }}</span>
                            @endif
                          </td>
                          <td>
                            @if ($transcation->transcation_type == 1)
                              {{ 'Event Booking' }}
                            @elseif ($transcation->transcation_type == 2)
                              {{ 'Product Order' }}
                            @elseif ($transcation->transcation_type == 3)
                              {{ 'Withdraw' }}
                            @elseif ($transcation->transcation_type == 4)
                              {{ 'Balance Add' }}
                            @elseif ($transcation->transcation_type == 5)
                              {{ 'Balance Subtract' }}
                            @elseif ($transcation->transcation_type == 6)
                              {{ 'Resale Sold' }}
                            @endif
                          </td>
                          <td>
                            @if ($transcation->transcation_type == 3)
                              @php
                                $method = $transcation->method()->first();
                              @endphp
                              @if ($method)
                                {{ $method->name }}
                              @else
                                {{ '-' }}
                              @endif
                            @else
                              @if (!is_null($transcation->payment_method))
                                {{ $transcation->payment_method === 'pay_at_event' ? 'Online booking' : $transcation->payment_method }}
                              @else
                                -
                              @endif
                            @endif
                          </td>
                          <td>
                            @if ($transcation->transcation_type == 3 || $transcation->transcation_type == 5)
                              <span class="text-danger">{{ '(-)' }}</span>
                            @else
                              <span class="text-success">{{ '(+)' }}</span>
                            @endif

                            {{ $transcation->currency_symbol_position == 'left' ? $transcation->currency_symbol : '' }}
                            {{ $transcation->grand_total }}
                            {{ $transcation->currency_symbol_position == 'right' ? $transcation->currency_symbol : '' }}
                          </td>
                          <td>
                            {{ $transcation->currency_symbol_position == 'left' ? $transcation->currency_symbol : '' }}
                            {{ $transcation->commission }}
                            {{ $transcation->currency_symbol_position == 'right' ? $transcation->currency_symbol : '' }}
                          </td>
                          <td>
                            {{ $transcation->currency_symbol_position == 'left' ? $transcation->currency_symbol : '' }}
                            {{ $transcation->grand_total - $transcation->commission }}
                            {{ $transcation->currency_symbol_position == 'right' ? $transcation->currency_symbol : '' }}
                          </td>
                          <td>
                            @if ($transcation->payment_status == 1)
                              <span class="badge badge-success">{{ __('Paid') }}</span>
                            @elseif ($transcation->payment_status == 2)
                              <span class="badge badge-warning">{{ __('Decline') }}</span>
                            @else
                              <span class="badge badge-danger">{{ __('Unpaid') }}</span>
                            @endif
                          </td>

                          <td>
                            @if ($transcation->transcation_type == 1)
                              @php
                                $t_invoice = $transcation->event_booking()->first();
                              @endphp
                              @if ($t_invoice)
                                <a target="_blank" class="btn btn-secondary btn-sm mr-1"
                                  href="{{ asset('assets/admin/file/invoices/' . $t_invoice->invoice) }}">
                                  <i class="fas fa-eye"></i>
                                </a>
                              @else
                                {{ '-' }}
                              @endif
                            @elseif ($transcation->transcation_type == 2)
                              @php
                                $t_invoice = $transcation->product_order()->first();
                              @endphp
                              @if ($t_invoice)
                                <a target="_blank" class="btn btn-secondary btn-sm mr-1"
                                  href="{{ asset('assets/admin/file/order/invoices/' . $t_invoice->invoice) }}">
                                  <i class="fas fa-eye"></i>
                                </a>
                              @else
                                {{ '-' }}
                              @endif
                            @else
                              {{ '-' }}
                            @endif
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

        <div class="card-footer text-center">
          <div class="d-inline-block mt-3">
            {{ $transcations->appends([
                    'transcation_id' => request()->input('transcation_id'),
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

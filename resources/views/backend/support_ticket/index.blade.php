@extends('backend.layout')

@section('style')
  <link rel="stylesheet" href="{{ asset('assets/front/css/daterangepicker.css') }}">
@endsection

@section('content')
  <div class="page-header">
    <h4 class="page-title">Incident tickets</h4>
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
        <a href="#">Incident tickets</a>
      </li>
      @if (!request()->filled('status'))
        <li class="separator">
          <i class="flaticon-right-arrow"></i>
        </li>
        <li class="nav-item">
          <a href="#">All incidents</a>
        </li>
      @endif
      @if (request()->filled('status') && request()->input('status') == 1)
        <li class="separator">
          <i class="flaticon-right-arrow"></i>
        </li>
        <li class="nav-item">
          <a href="#">Pending incidents</a>
        </li>
      @endif
      @if (request()->filled('status') && request()->input('status') == 2)
        <li class="separator">
          <i class="flaticon-right-arrow"></i>
        </li>
        <li class="nav-item">
          <a href="#">Open incidents</a>
        </li>
      @endif
      @if (request()->filled('status') && request()->input('status') == 3)
        <li class="separator">
          <i class="flaticon-right-arrow"></i>
        </li>
        <li class="nav-item">
          <a href="#">Closed incidents</a>
        </li>
      @endif
    </ul>
  </div>

  <div class="row">
    <div class="col-md-12">
      <div class="card">
        <div class="card-header">


          <div class="row">
            <div class="col-lg-4">
              <div class="card-title d-inline-block">
                Incident tickets
              </div>
              <a href="{{ route('admin.support_tickets.create') }}" class="btn btn-success btn-sm ml-2">
                <i class="fas fa-plus"></i> {{ __('Open incident') }}
              </a>
            </div>

            <div class="col-lg-8 mt-2 mt-lg-0">
              @php
                $incidentDateRangeValue = '';
                if (request()->filled('from_date') && request()->filled('to_date')) {
                    $incidentDateRangeValue = request()->input('from_date') . ' - ' . request()->input('to_date');
                }
              @endphp
              <div class="d-flex flex-nowrap align-items-center justify-content-lg-end" style="gap:8px;">
                <form action="" method="GET" class="d-flex flex-nowrap align-items-center js-range-form mb-0" style="gap:8px;">
                  <input type="hidden" name="status" value="{{ request()->input('status') }}">
                  <input type="text" name="ticket_id" class="form-control" placeholder="Search by Ticket ID"
                    value="{{ !empty(request()->input('ticket_id')) ? request()->input('ticket_id') : '' }}" style="width:220px;">
                  <span class="align-self-center font-weight-bold">{{ __('Date Range') }}</span>
                  <input type="text" class="form-control js-date-range" value="{{ $incidentDateRangeValue }}"
                    placeholder="YYYY-MM-DD - YYYY-MM-DD" autocomplete="off" style="width:230px;">
                  <input type="hidden" name="from_date" value="{{ request()->input('from_date') }}" class="js-from-date">
                  <input type="hidden" name="to_date" value="{{ request()->input('to_date') }}" class="js-to-date">
                  <button type="submit" class="btn btn-primary btn-sm">{{ __('Apply') }}</button>
                </form>

                <form action="{{ route('admin.support_tickets.export') }}" method="GET" class="d-inline-block">
                  <input type="hidden" name="ticket_id" value="{{ request()->input('ticket_id') }}">
                  <input type="hidden" name="status" value="{{ request()->input('status') }}">
                  <input type="hidden" name="from_date" value="{{ request()->input('from_date') }}">
                  <input type="hidden" name="to_date" value="{{ request()->input('to_date') }}">
                  <button type="submit" class="btn btn-success btn-sm" title="CSV Format">{{ __('Export CSV') }}</button>
                </form>

                <button class="btn btn-danger btn-sm bulk-delete d-none"
                  data-href="{{ route('admin.support_tickets.bulk_delete') }}">
                  <i class="flaticon-interface-5"></i> {{ __('Delete') }}
                </button>
              </div>
            </div>

          </div>
        </div>

        <div class="card-body">
          <div class="row">
            <div class="col-lg-12">

              @if (session()->has('course_status_warning'))
                <div class="alert alert-warning">
                  <p class="text-dark mb-0">{{ session()->get('course_status_warning') }}</p>
                </div>
              @endif

              @if (count($collection) == 0)
                <h3 class="text-center mt-2">NO INCIDENT TICKETS FOUND!</h3>
              @else
                <div class="table-responsive">
                  <table class="table table-striped mt-3">
                    <thead>
                      <tr>
                        <th scope="col">
                          <input type="checkbox" class="bulk-check" data-val="all">
                        </th>
                        <th scope="col">{{ __('Ticket ID') }}</th>
                        <th scope="col">{{ __('Seller') }}</th>
                        <th scope="col">{{ __('Buyer') }}</th>
                        <th scope="col">{{ __('Email') }}</th>
                        <th scope="col">{{ __('Subject') }}</th>
                        <th scope="col">{{ __('Status') }}</th>
                        <th scope="col">{{ __('Action') }}</th>
                      </tr>
                    </thead>
                    <tbody>
                      @foreach ($collection as $item)
                        <tr>
                          <td>
                            <input type="checkbox" class="bulk-check" data-val="{{ $item->id }}">
                          </td>
                          <td>
                            {{ $item->id }}
                          </td>
                          <td>
                            @if (strtolower((string) $item->counterparty_user_type) === 'customer' && $item->counterpartyCustomer)
                              <a
                                href="{{ route('admin.customer_management.customer_details', ['id' => $item->counterpartyCustomer->id, 'language' => $defaultLang->code]) }}">
                                Seller
                              </a>
                            @elseif ($item->user_type == 'organizer' && $item->organizer)
                              <a
                                href="{{ route('admin.organizer_management.organizer_details', ['id' => $item->organizer->id, 'language' => $defaultLang->code]) }}">
                                {{ $item->organizer->username }}
                              </a>
                            @elseif ($item->counterparty_name)
                              {{ $item->counterparty_name }}
                            @else
                              -
                            @endif
                          </td>
                          <td>
                            @if ($item->user_type == 'customer' && $item->customer)
                              <a
                                href="{{ route('admin.customer_management.customer_details', ['id' => $item->customer->id, 'language' => $defaultLang->code]) }}">
                                {{ trim(($item->customer->fname ?? '') . ' ' . ($item->customer->lname ?? '')) !== '' ? trim(($item->customer->fname ?? '') . ' ' . ($item->customer->lname ?? '')) : ($item->customer->username ?? '-') }}
                              </a>
                            @elseif ($item->user_type == 'organizer' && $item->organizer)
                              <a
                                href="{{ route('admin.organizer_management.organizer_details', ['id' => $item->organizer->id, 'language' => $defaultLang->code]) }}">
                                {{ $item->organizer->username }}
                              </a>
                            @else
                              -
                            @endif
                          </td>
                          <td>
                            {{ $item->email != '' ? $item->email : '-' }}
                          </td>
                          <td>
                            {{ $item->subject }}
                            @if (!empty($item->is_litigation) && $item->is_litigation)
                              <span class="badge badge-warning ml-1" title="{{ __('Litigation') }}">
                                <i class="fas fa-balance-scale"></i>
                                {{ __('LITIGATION') }}
                                @if (($item->litigation_status ?? 'open') === 'resolved')
                                  · {{ __('resolved') }}
                                @endif
                              </span>
                            @endif
                          </td>
                          <td>
                            @if ($item->status == 1)
                              <span class="badge badge-info">{{ __('Pending') }}</span>
                            @elseif($item->status == 2)
                              <span class="badge badge-success">{{ __('Open') }}</span>
                            @elseif($item->status == 3)
                              <span class="badge badge-danger">{{ __('Closed') }}</span>
                            @endif
                          </td>
                          <td>
                            <div class="dropdown">
                              <button class="btn btn-secondary dropdown-toggle btn-sm" type="button"
                                id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                {{ __('Select') }}
                              </button>

                              <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                                <a href="{{ route('admin.support_tickets.message', $item->id) }}" class="dropdown-item">
                                  {{ __('Message') }}
                                </a>
                                <form class="deleteForm d-block"
                                  action="{{ route('admin.support_tickets.delete', $item->id) }}" method="post">
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
                    </tbody>
                  </table>
                </div>
              @endif
            </div>
          </div>
        </div>

        <div class="card-footer">
          {{ $collection->appends([
                  'ticket_id' => request()->input('ticket_id'),
                  'status' => request()->input('status'),
                  'from_date' => request()->input('from_date'),
                  'to_date' => request()->input('to_date'),
              ])->links() }}
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

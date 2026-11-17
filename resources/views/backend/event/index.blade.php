@extends('backend.layout')

@section('style')
  <link rel="stylesheet" href="{{ asset('assets/front/css/daterangepicker.css') }}">
@endsection

@section('content')
  <div class="page-header">
    <h4 class="page-title">Tickets</h4>
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
        <a href="#">Ticket management</a>
      </li>
      @if (!request()->filled('event_type'))
        <li class="separator">
          <i class="flaticon-right-arrow"></i>
        </li>
        <li class="nav-item">
          <a
            href="{{ route('admin.event_management.event', ['language' => $defaultLang->code]) }}">All tickets</a>
        </li>
      @endif
      @if (request()->filled('event_type') && request()->input('event_type') == 'venue')
        <li class="separator">
          <i class="flaticon-right-arrow"></i>
        </li>
        <li class="nav-item">
          <a href="#">Venue tickets</a>
        </li>
      @endif
      @if (request()->filled('event_type') && request()->input('event_type') == 'online')
        <li class="separator">
          <i class="flaticon-right-arrow"></i>
        </li>
        <li class="nav-item">
          <a href="#">Online tickets</a>
        </li>
      @endif
    </ul>
  </div>

  <div class="row">
    <div class="col-md-12">
      <div class="card">
        <div class="card-header">
          <div class="row">
            <div class="col-lg-2">
              <div class="card-title d-inline-block">
                Tickets listed
              </div>
            </div>

            <div class="col-lg-10 mt-2 mt-lg-0">
              @php
                $eventDateRangeValue = '';
                if (request()->filled('from_date') && request()->filled('to_date')) {
                    $eventDateRangeValue = request()->input('from_date') . ' - ' . request()->input('to_date');
                }
              @endphp
              <div class="d-flex flex-nowrap align-items-center justify-content-lg-end" style="gap:8px;">
                <form action="" method="get" class="d-flex flex-nowrap align-items-center js-range-form mb-0" style="gap:8px;">
                  <input type="hidden" name="language" value="{{ request()->input('language') }}">
                  <input type="hidden" name="event_type" value="{{ request()->input('event_type') }}">
                  <input type="text" name="title" value="{{ request()->input('title') }}"
                    placeholder="Enter Ticket Name" class="form-control" style="width: 190px;">
                  <input type="text" class="form-control js-date-range" value="{{ $eventDateRangeValue }}"
                    placeholder="{{ __('Event start date') }}" title="{{ __('Filters by event start date') }}"
                    autocomplete="off" style="width: 210px;">
                  <input type="hidden" name="from_date" value="{{ request()->input('from_date') }}" class="js-from-date">
                  <input type="hidden" name="to_date" value="{{ request()->input('to_date') }}" class="js-to-date">
                  <button type="submit" class="btn btn-primary btn-sm">{{ __('Filter') }}</button>
                </form>

                <a href="{{ route('add.event.event', ['type' => 'venue']) }}" class="btn btn-secondary btn-sm">
                  {{ __('Add Ticket') }}
                </a>

                <form action="{{ route('admin.event_management.event.export') }}" method="GET" class="d-inline-block">
                  <input type="hidden" name="language" value="{{ request()->input('language') }}">
                  <input type="hidden" name="title" value="{{ request()->input('title') }}">
                  <input type="hidden" name="event_type" value="{{ request()->input('event_type') }}">
                  <input type="hidden" name="from_date" value="{{ request()->input('from_date') }}">
                  <input type="hidden" name="to_date" value="{{ request()->input('to_date') }}">
                  <button type="submit" class="btn btn-success btn-sm" title="CSV Format">{{ __('Export CSV') }}</button>
                </form>

                <button class="btn btn-danger btn-sm d-none bulk-delete"
                  data-href="{{ route('admin.event_management.bulk_delete_event') }}">
                  <i class="flaticon-interface-5"></i> {{ __('Delete') }}
                </button>
              </div>
            </div>
          </div>
        </div>

        <div class="card-body">
          <div class="row">
            <div class="col-lg-12">

              @if (count($events) == 0)
                <h3 class="text-center mt-2">{{ 'NO TICKET CONTENT FOUND FOR ' . $language->name . '!' }}</h3>
              @else
                <div class="table-responsive">
                  <table class="table table-striped mt-3">
                    <thead>
                      <tr>
                        <th scope="col">
                          <input type="checkbox" class="bulk-check" data-val="all">
                        </th>
                        <th scope="col" width="22%">{{ __('Title') }}</th>
                        <th scope="col">{{ __('Schedule') }}</th>
                        <th scope="col">Sellers</th>
                        <th scope="col">{{ __('Type') }}</th>
                        <th scope="col">{{ __('Category') }}</th>
                        <th scope="col">{{ __('Counts') }}</th>
                        <th scope="col">{{ __('Status') }}</th>
                        <th scope="col">{{ __('Featured') }}</th>
                        <th scope="col">{{ __('Actions') }}</th>
                      </tr>
                    </thead>
                    <tbody>
                      @foreach ($events as $event)
                        <tr>
                          <td>
                            <input type="checkbox" class="bulk-check" data-val="{{ $event->id }}">
                          </td>
                          <td width="18%">
                            <a target="_blank"
                              href="{{ route('event.details', ['slug' => $event->slug, 'id' => $event->id]) }}">{{ strlen($event->title) > 30 ? mb_substr($event->title, 0, 30, 'UTF-8') . '....' : $event->title }}</a>
                          </td>
                          <td class="small text-nowrap">
                            @if (!empty($event->start_date))
                              @php
                                try {
                                    $evScheduleStart = \Carbon\Carbon::parse(
                                        trim($event->start_date . ' ' . ($event->start_time ?? ''))
                                    )->format('M j, Y g:i A');
                                } catch (\Throwable $e) {
                                    $evScheduleStart = trim($event->start_date . ' ' . ($event->start_time ?? ''));
                                }
                                $evScheduleEnd = null;
                                if (!empty($event->end_date_time)) {
                                    try {
                                        $evScheduleEnd = \Carbon\Carbon::parse($event->end_date_time)->format(
                                            'M j, Y g:i A'
                                        );
                                    } catch (\Throwable $e) {
                                        $evScheduleEnd = (string) $event->end_date_time;
                                    }
                                } elseif (!empty($event->end_date)) {
                                    try {
                                        $evScheduleEnd = \Carbon\Carbon::parse(
                                            trim($event->end_date . ' ' . ($event->end_time ?? ''))
                                        )->format('M j, Y g:i A');
                                    } catch (\Throwable $e) {
                                        $evScheduleEnd = trim($event->end_date . ' ' . ($event->end_time ?? ''));
                                    }
                                }
                              @endphp
                              <div><span class="text-muted">{{ __('Start') }}:</span> {{ $evScheduleStart }}</div>
                              @if ($evScheduleEnd)
                                <div><span class="text-muted">{{ __('End') }}:</span> {{ $evScheduleEnd }}</div>
                              @endif
                            @else
                              <span class="text-muted">—</span>
                            @endif
                          </td>
                          <td>
                            @if ($event->organizer)
                              <a target="_blank"
                                href="{{ route('admin.organizer_management.organizer_details', ['id' => $event->organizer_id, 'language' => $defaultLang->code]) }}">
                                {{ strlen($event->organizer->username) > 20 ? mb_substr($event->organizer->username, 0, 20, 'UTF-8') . '....' : $event->organizer->username }}</a>
                            @else
                              <span class="badge badge-success">{{ __('Admin') }}</span>
                            @endif
                          </td>
                          <td>
                         
                            <span class="badge badge-info mt-1">
                              {{ ucfirst($event->event_ticket_transfer_type ?? 'external') }}
                            </span>
                          </td>
                          <td>
                            {{ $event->category }}
                          </td>
                          <td>
                            <span class="badge badge-primary mr-1">Resellers: {{ (int) ($event->reseller_count ?? 0) }}</span>
                            <span class="badge badge-success mr-1">Sold: {{ (int) ($event->resale_sold_qty ?? 0) }}</span>
                            <span class="badge badge-warning">On hold: {{ (int) ($event->resale_pending_qty ?? 0) }}</span>
                          </td>
                          <td>
                            <form id="statusForm-{{ $event->id }}" class="d-inline-block"
                              action="{{ route('admin.event_management.event.event_status', ['id' => $event->id, 'language' => request()->input('language')]) }}"
                              method="post">

                              @csrf
                              <select
                                class="form-control form-control-sm {{ $event->status == 0 ? 'bg-warning text-dark' : 'bg-primary' }}"
                                name="status"
                                onchange="document.getElementById('statusForm-{{ $event->id }}').submit()">
                                <option value="1" {{ $event->status == 1 ? 'selected' : '' }}>
                                  {{ __('Active') }}
                                </option>
                                <option value="0" {{ $event->status == 0 ? 'selected' : '' }}>
                                  {{ __('Deactive') }}
                                </option>
                              </select>
                            </form>
                          </td>
                          <td>

                            <form id="featuredForm-{{ $event->id }}" class="d-inline-block"
                              action="{{ route('admin.event_management.event.update_featured', ['id' => $event->id]) }}"
                              method="post">

                              @csrf
                              <select
                                class="form-control form-control-sm {{ $event->is_featured == 'yes' ? 'bg-success' : 'bg-danger' }}"
                                name="is_featured"
                                onchange="document.getElementById('featuredForm-{{ $event->id }}').submit()">
                                <option value="yes" {{ $event->is_featured == 'yes' ? 'selected' : '' }}>
                                  {{ __('Yes') }}
                                </option>
                                <option value="no" {{ $event->is_featured == 'no' ? 'selected' : '' }}>
                                  {{ __('No') }}
                                </option>
                              </select>
                            </form>
                          </td>
                          <td>
                            <div class="dropdown">
                              <button class="btn btn-secondary dropdown-toggle btn-sm" type="button"
                                id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true"
                                aria-expanded="false">
                                {{ __('Select') }}
                              </button>

                              <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                                <a href="{{ route('admin.event_management.edit_event', ['id' => $event->id]) }}"
                                  class="dropdown-item">
                                  {{ __('Edit') }}
                                </a>
                                <a href="{{ route('admin.event_management.event.resellers', ['id' => $event->id, 'language' => request()->input('language')]) }}"
                                  class="dropdown-item">
                                  {{ __('Resellers') }}
                                </a>

                                <form class="deleteForm d-block"
                                  action="{{ route('admin.event_management.delete_event', ['id' => $event->id]) }}"
                                  method="post">

                                  @csrf
                                  <button type="submit" class="btn btn-sm deleteBtn">
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

        <div class="card-footer text-center">
          <div class="d-inline-block mt-3">
            {{ $events->appends([
                    'language' => request()->input('language'),
                    'title' => request()->input('title'),
                    'event_type' => request()->input('event_type'),
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

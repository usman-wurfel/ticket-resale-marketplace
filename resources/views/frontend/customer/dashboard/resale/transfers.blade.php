@extends('frontend.layout')

@section('pageHeading')
  {{ __('My Transfers') }}
@endsection

@section('hero-section')
  <section class="page-banner overlay pt-120 pb-125 rpt-90 rpb-95 lazy"
    data-bg="{{ asset('assets/admin/img/' . $basicInfo->breadcrumb) }}">
    <div class="container">
      <div class="banner-inner">
        <h2 class="page-title">{{ __('My Transfers') }}</h2>
      </div>
    </div>
  </section>
@endsection

@section('content')
  <section class="user-dashbord">
    <div class="container">
      <div class="row">
        @includeIf('frontend.customer.partials.sidebar')
        <div class="col-lg-9">
          <div class="account-info">
            <div class="title d-flex justify-content-between align-items-center flex-wrap">
              <h4>{{ __('My Transfers') }}</h4>
              <a href="{{ route('customer.tickets.sold') }}" class="btn btn-sm btn-outline-secondary">
                {{ __('All my listings') }}
              </a>
            </div>
            <div class="main-info">
              @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
              @endif
              @if (session('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
              @endif

              {{-- 1. PENDING ───────────────────────────────────────────────── --}}
              <div class="mb-4">
                <div class="d-flex align-items-center justify-content-between">
                  <h5 class="mb-0">
                    <i class="fas fa-paper-plane text-warning mr-1"></i>
                    {{ __('Tickets awaiting your transfer') }}
                    <span class="badge badge-warning ml-1">{{ $pending->count() }}</span>
                  </h5>
                  <small class="text-muted">{{ __('Action required') }}</small>
                </div>
                <hr class="my-2">

                @if ($pending->isEmpty())
                  <div class="alert alert-secondary mb-0">
                    {{ __('No pending transfers. You are all caught up!') }}
                  </div>
                @else
                  <div class="row">
                    @foreach ($pending as $ticket)
                      @php
                        $eventTitle = optional($ticket->eventContent)->title ?? ('#' . $ticket->event_id);
                        $deadline = $ticket->transfer_deadline
                          ? \Carbon\Carbon::parse($ticket->transfer_deadline)
                          : null;
                        $isOverdue = $deadline && $deadline->isPast();
                        $availabilityNotYet = $ticket->event
                          && !empty($ticket->event->ticket_availability_datetime)
                          && \Carbon\Carbon::parse($ticket->event->ticket_availability_datetime)->isFuture();
                        $transferType = $ticket->event
                          ? ($ticket->event->event_ticket_transfer_type ?? 'external')
                          : 'external';
                      @endphp
                      <div class="col-md-6 mb-3">
                        <div class="card h-100 {{ $isOverdue ? 'border-danger' : 'border-warning' }}">
                          <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start">
                              <h6 class="mb-1" style="line-height:1.3;">
                                {{ strlen($eventTitle) > 60 ? mb_substr($eventTitle, 0, 60) . '…' : $eventTitle }}
                              </h6>
                              <span class="badge {{ $transferType === 'internal' ? 'badge-primary' : 'badge-info' }}">
                                {{ ucfirst($transferType) }}
                              </span>
                            </div>
                            <div class="small text-muted mb-2">
                              {{ __('Booking') }}: <strong>{{ $ticket->booking_id ?: '-' }}</strong>
                              @if ($ticket->zone)
                                · {{ __('Zone') }}: <strong>{{ $ticket->zone }}</strong>
                              @endif
                              · {{ __('Qty') }}: <strong>{{ $ticket->quantity }}</strong>
                              · {{ __('Price') }}:
                              <strong>{{ number_format((float) $ticket->price, 2) }}</strong>
                            </div>

                            @if ($availabilityNotYet)
                              <div class="alert alert-info py-2 mb-2 small">
                                <i class="fas fa-clock mr-1"></i>
                                {{ __('Tickets available from') }}
                                <strong>{{ \Carbon\Carbon::parse($ticket->event->ticket_availability_datetime)->format('Y-m-d H:i') }}</strong>
                              </div>
                            @endif

                            @if ($deadline)
                              <div class="{{ $isOverdue ? 'text-danger' : 'text-warning' }} font-weight-bold small mb-2"
                                   data-countdown="{{ $deadline->getTimestamp() * 1000 }}">
                                <i class="fas fa-hourglass-half mr-1"></i>
                                @if ($isOverdue)
                                  {{ __('Deadline passed') }} ({{ $deadline->diffForHumans() }})
                                @else
                                  {{ __('Deadline') }}: {{ $deadline->format('d M Y H:i') }}
                                  · <span class="countdown-value">--:--:--</span>
                                @endif
                              </div>
                            @endif

                            <a href="{{ route('customer.tickets.transfer', $ticket->id) }}"
                               class="btn btn-{{ $isOverdue ? 'danger' : 'warning' }} btn-block {{ $availabilityNotYet ? 'disabled' : '' }}"
                               @if ($availabilityNotYet) aria-disabled="true" @endif>
                              <i class="fas fa-paper-plane mr-1"></i>
                              @if ($transferType === 'internal')
                                {{ __('Upload ticket PDF') }}
                              @else
                                {{ __('Confirm external transfer') }}
                              @endif
                            </a>
                          </div>
                        </div>
                      </div>
                    @endforeach
                  </div>
                @endif
              </div>

              {{-- 2. DONE ──────────────────────────────────────────────────── --}}
              <div class="mb-4">
                <div class="d-flex align-items-center justify-content-between">
                  <h5 class="mb-0">
                    <i class="fas fa-check-circle text-success mr-1"></i>
                    {{ __('Transferred (awaiting payment release)') }}
                    <span class="badge badge-success ml-1">{{ $done->count() }}</span>
                  </h5>
                </div>
                <hr class="my-2">

                @if ($done->isEmpty())
                  <p class="text-muted mb-0">{{ __('Nothing here yet.') }}</p>
                @else
                  <div class="table-responsive">
                    <table class="table table-sm table-striped">
                      <thead>
                        <tr>
                          <th>{{ __('Event') }}</th>
                          <th>{{ __('Booking') }}</th>
                          <th>{{ __('Qty') }}</th>
                          <th>{{ __('Price') }}</th>
                          <th>{{ __('Transferred at') }}</th>
                          <th>{{ __('Payment release') }}</th>
                          <th></th>
                        </tr>
                      </thead>
                      <tbody>
                        @foreach ($done as $t)
                          @php
                            $releaseDue = $t->payment_release_due_at
                              ? \Carbon\Carbon::parse($t->payment_release_due_at)
                              : null;
                          @endphp
                          <tr>
                            <td>{{ optional($t->eventContent)->title ?? ('#' . $t->event_id) }}</td>
                            <td>{{ $t->booking_id ?: '-' }}</td>
                            <td>{{ $t->quantity }}</td>
                            <td>{{ number_format((float) $t->price, 2) }}</td>
                            <td>
                              {{ $t->transferred_at
                                ? \Carbon\Carbon::parse($t->transferred_at)->format('d M Y H:i')
                                : '-' }}
                            </td>
                            <td>
                              @if ($t->payment_released_at)
                                <span class="badge badge-success">
                                  {{ __('Released') }}
                                  · {{ \Carbon\Carbon::parse($t->payment_released_at)->format('d M') }}
                                </span>
                              @elseif ($releaseDue)
                                <span class="badge badge-light border"
                                      title="{{ $releaseDue->format('Y-m-d H:i') }}">
                                  {{ $releaseDue->format('d M Y H:i') }}
                                </span>
                              @else
                                -
                              @endif
                            </td>
                            <td>
                              <a href="{{ route('customer.tickets.transfer', $t->id) }}"
                                 class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-eye"></i>
                              </a>
                            </td>
                          </tr>
                        @endforeach
                      </tbody>
                    </table>
                  </div>
                @endif
              </div>

              {{-- 3. FAILED ────────────────────────────────────────────────── --}}
              @if ($failed->count() > 0)
                <div class="mb-4">
                  <h5>
                    <i class="fas fa-exclamation-triangle text-danger mr-1"></i>
                    {{ __('Missed transfer deadlines') }}
                    <span class="badge badge-danger ml-1">{{ $failed->count() }}</span>
                  </h5>
                  <hr class="my-2">
                  <div class="alert alert-danger small">
                    {{ __('These sales were cancelled because the transfer was not delivered in time. The buyer has been refunded. Your confidence level has been impacted.') }}
                  </div>
                  <div class="table-responsive">
                    <table class="table table-sm table-striped">
                      <thead>
                        <tr>
                          <th>{{ __('Event') }}</th>
                          <th>{{ __('Booking') }}</th>
                          <th>{{ __('Qty') }}</th>
                          <th>{{ __('Price') }}</th>
                          <th>{{ __('Missed on') }}</th>
                        </tr>
                      </thead>
                      <tbody>
                        @foreach ($failed as $t)
                          <tr>
                            <td>{{ optional($t->eventContent)->title ?? ('#' . $t->event_id) }}</td>
                            <td>{{ $t->booking_id ?: '-' }}</td>
                            <td>{{ $t->quantity }}</td>
                            <td>{{ number_format((float) $t->price, 2) }}</td>
                            <td>{{ $t->updated_at ? $t->updated_at->format('d M Y H:i') : '-' }}</td>
                          </tr>
                        @endforeach
                      </tbody>
                    </table>
                  </div>
                </div>
              @endif
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
@endsection

@section('script')
  <script>
    (function () {
      function pad(n) { return n < 10 ? '0' + n : '' + n; }
      document.querySelectorAll('[data-countdown]').forEach(function (el) {
        var deadline = parseInt(el.getAttribute('data-countdown'), 10);
        var target = el.querySelector('.countdown-value');
        if (!deadline || !target) return;
        function tick() {
          var remaining = deadline - Date.now();
          if (remaining <= 0) { target.innerText = '00:00:00'; return false; }
          var sec = Math.floor(remaining / 1000);
          var h = Math.floor(sec / 3600);
          var m = Math.floor((sec % 3600) / 60);
          var s = sec % 60;
          target.innerText = pad(h) + ':' + pad(m) + ':' + pad(s);
          return true;
        }
        tick();
        var h = setInterval(function () { if (!tick()) clearInterval(h); }, 1000);
      });
    })();
  </script>
@endsection

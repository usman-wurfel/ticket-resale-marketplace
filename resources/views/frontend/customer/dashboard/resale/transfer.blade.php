@extends('frontend.layout')

@section('pageHeading')
  {{ __('Transfer Ticket') }}
@endsection

@section('hero-section')
  <section class="page-banner overlay pt-120 pb-125 rpt-90 rpb-95 lazy"
    data-bg="{{ asset('assets/admin/img/' . $basicInfo->breadcrumb) }}">
    <div class="container">
      <div class="banner-inner">
        <h2 class="page-title">{{ __('Transfer ticket to buyer') }}</h2>
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
              <h4>{{ __('Transfer ticket') }}</h4>
              <a href="{{ route('customer.tickets.sold') }}" class="btn btn-sm btn-secondary">
                {{ __('Back to sold tickets') }}
              </a>
            </div>
            <div class="main-info">
              @if (session('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
              @endif
              @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
              @endif
              @if ($errors->any())
                <div class="alert alert-danger">
                  <ul class="mb-0">
                    @foreach ($errors->all() as $err)
                      <li>{{ $err }}</li>
                    @endforeach
                  </ul>
                </div>
              @endif

              @php
                $event = $ticket->event;
                $transferType = $event ? ($event->event_ticket_transfer_type ?? 'external') : 'external';
                $existingPdfs = [];
                if (!empty($ticket->ticket_pdfs)) {
                    $decoded = json_decode((string) $ticket->ticket_pdfs, true);
                    if (is_array($decoded)) { $existingPdfs = array_values(array_filter($decoded)); }
                }
                $needed = max(1, (int) $ticket->quantity);
                $remainingToUpload = max(0, $needed - count($existingPdfs));

                $availabilityNotYet = $event
                    && !empty($event->ticket_availability_datetime)
                    && \Carbon\Carbon::parse($event->ticket_availability_datetime)->isFuture();
              @endphp

              {{-- Summary card --}}
              <div class="card mb-4">
                <div class="card-body">
                  <h5 class="mb-3">{{ __('Sale summary') }}</h5>
                  <div class="row">
                    <div class="col-md-6">
                      <p class="mb-1"><strong>{{ __('Event') }}:</strong>
                        {{ optional($ticket->eventContent)->title ?? ('#' . $ticket->event_id) }}
                      </p>
                      @if ($ticket->zone)
                        <p class="mb-1"><strong>{{ __('Zone') }}:</strong> {{ $ticket->zone }}</p>
                      @endif
                      <p class="mb-1"><strong>{{ __('Booking ID') }}:</strong> {{ $ticket->booking_id ?: '-' }}</p>
                      <p class="mb-1"><strong>{{ __('Quantity') }}:</strong> {{ $ticket->quantity }}</p>
                      <p class="mb-1"><strong>{{ __('Unit price') }}:</strong>
                        {{ number_format((float) $ticket->price, 2) }}
                      </p>
                    </div>
                    <div class="col-md-6">
                      <p class="mb-1"><strong>{{ __('Transfer type') }}:</strong>
                        <span class="badge {{ $transferType === 'internal' ? 'badge-primary' : 'badge-info' }}">
                          {{ ucfirst($transferType) }}
                        </span>
                      </p>
                      @if ($transferType === 'external' && !empty($event->external_platform_name))
                        <p class="mb-1"><strong>{{ __('Platform') }}:</strong> {{ $event->external_platform_name }}</p>
                      @endif
                      <p class="mb-1"><strong>{{ __('Status') }}:</strong>
                        @if ($ticket->transfer_status === \App\Models\Event\ResaleTicket::TRANSFER_DONE)
                          <span class="badge badge-success">{{ __('Transferred') }}</span>
                        @elseif ($ticket->transfer_status === \App\Models\Event\ResaleTicket::TRANSFER_MISSED)
                          <span class="badge badge-danger">{{ __('Deadline missed') }}</span>
                        @else
                          <span class="badge badge-warning">{{ __('Pending transfer') }}</span>
                        @endif
                      </p>
                      @if ($ticket->paid_at)
                        <p class="mb-1"><strong>{{ __('Paid at') }}:</strong>
                          {{ \Carbon\Carbon::parse($ticket->paid_at)->format('Y-m-d H:i') }}
                        </p>
                      @endif
                    </div>
                  </div>
                </div>
              </div>

              {{-- Deadline + live countdown --}}
              @if ($ticket->transfer_deadline && in_array($ticket->transfer_status, [\App\Models\Event\ResaleTicket::TRANSFER_PENDING, null], true))
                @php
                  $deadlineTs = \Carbon\Carbon::parse($ticket->transfer_deadline)->getTimestamp() * 1000;
                  $deadlineHuman = \Carbon\Carbon::parse($ticket->transfer_deadline)->format('Y-m-d H:i');
                @endphp
                <div id="transfer-countdown-box"
                     class="alert alert-warning d-flex align-items-center justify-content-between flex-wrap"
                     data-deadline-ts="{{ $deadlineTs }}">
                  <div>
                    <strong>{{ __('Transfer deadline') }}:</strong> {{ $deadlineHuman }}<br>
                    <small>{{ __('If you do not transfer before this time, the sale is cancelled, the buyer is refunded, and your confidence level drops.') }}</small>
                  </div>
                  <div class="text-right">
                    <div class="small text-uppercase text-muted" style="letter-spacing:1px;">
                      {{ __('Time left') }}
                    </div>
                    <div id="transfer-countdown"
                         style="font-size: 28px; font-weight: bold; line-height: 1; font-variant-numeric: tabular-nums;">
                      --:--:--
                    </div>
                  </div>
                </div>
              @endif

              {{-- Availability gate --}}
              @if ($availabilityNotYet)
                <div class="alert alert-info">
                  <i class="fas fa-clock mr-1"></i>
                  {{ __('Tickets are not yet available from the organizer. You can transfer as soon as:') }}
                  <strong>{{ \Carbon\Carbon::parse($event->ticket_availability_datetime)->format('Y-m-d H:i') }}</strong>
                </div>
              @endif

              {{-- Transferred: read-only view --}}
              @if ($ticket->transfer_status === \App\Models\Event\ResaleTicket::TRANSFER_DONE)
                <div class="alert alert-success">
                  <i class="fas fa-check-circle mr-1"></i>
                  <strong>{{ __('Ticket transferred.') }}</strong>
                  @if ($ticket->transferred_at)
                    {{ __('on') }} {{ \Carbon\Carbon::parse($ticket->transferred_at)->format('Y-m-d H:i') }}.
                  @endif
                  @if ($ticket->payment_release_due_at)
                    <br>{{ __('Your payment is scheduled to be released on') }}
                    <strong>{{ \Carbon\Carbon::parse($ticket->payment_release_due_at)->format('Y-m-d H:i') }}</strong>
                    ({{ __('48h after the event, if no litigation is open') }}).
                  @endif
                </div>

                @if (!empty($existingPdfs) && $transferType === 'internal')
                  <h5>{{ __('Files delivered to the buyer') }}</h5>
                  <ul class="list-group mb-3">
                    @foreach ($existingPdfs as $idx => $pdf)
                      <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-file-pdf text-danger mr-2"></i> {{ $pdf }}</span>
                        <a href="{{ route('customer.tickets.pdf_download', [$ticket->id, $idx]) }}"
                           class="btn btn-sm btn-outline-primary" target="_blank">
                          <i class="fas fa-download mr-1"></i> {{ __('Preview') }}
                        </a>
                      </li>
                    @endforeach
                  </ul>
                @endif

              @elseif ($ticket->transfer_status === \App\Models\Event\ResaleTicket::TRANSFER_MISSED)
                <div class="alert alert-danger">
                  <i class="fas fa-exclamation-triangle mr-1"></i>
                  {{ __('You missed the transfer deadline. The buyer has been refunded and your confidence level has been updated.') }}
                </div>

              @else
                {{-- INTERNAL: upload PDFs --}}
                @if ($transferType === 'internal')
                  <h5>{{ __('Internal transfer') }} — {{ __('upload ticket PDF(s)') }}</h5>
                  <p class="text-muted">
                    {{ __('Please attach one PDF per seat. These files will be delivered to the buyer.') }}
                  </p>

                  @if (count($existingPdfs) > 0)
                    <div class="alert alert-info">
                      {{ __('Files already attached to this listing') }}:
                      <strong>{{ count($existingPdfs) }}</strong>
                      / {{ $needed }}.
                      @if ($remainingToUpload > 0)
                        {{ __('You still need to upload') }}
                        <strong>{{ $remainingToUpload }}</strong>
                        {{ __('more PDF(s).') }}
                      @else
                        {{ __('All required files are already attached — you can simply confirm the transfer below.') }}
                      @endif
                    </div>
                    <ul class="list-group mb-3">
                      @foreach ($existingPdfs as $idx => $pdf)
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                          <span><i class="fas fa-file-pdf text-danger mr-2"></i> {{ $pdf }}</span>
                          <a href="{{ route('customer.tickets.pdf_download', [$ticket->id, $idx]) }}"
                             class="btn btn-sm btn-outline-secondary" target="_blank">
                            <i class="fas fa-eye mr-1"></i> {{ __('Preview') }}
                          </a>
                        </li>
                      @endforeach
                    </ul>
                  @endif

                  <form method="POST" action="{{ route('customer.tickets.transfer.upload', $ticket->id) }}"
                        enctype="multipart/form-data" id="internal-transfer-form">
                    @csrf
                    @if ($remainingToUpload > 0)
                      <div class="form-group">
                        <label>
                          {{ __('Ticket PDFs') }}
                          ({{ __('you still need :n file(s)', ['n' => $remainingToUpload]) }})
                        </label>
                        <input type="file" class="form-control-file" name="ticket_pdfs[]"
                               accept="application/pdf" multiple
                               @if ($remainingToUpload > 0) required @endif>
                        <small class="form-text text-muted">
                          {{ __('PDF only, max 20 MB each.') }}
                        </small>
                      </div>
                    @endif
                    <button type="submit" class="btn btn-primary btn-lg"
                            @if ($availabilityNotYet) disabled @endif>
                      <i class="fas fa-paper-plane mr-1"></i>
                      {{ $remainingToUpload > 0 ? __('Upload and transfer') : __('Confirm transfer with attached files') }}
                    </button>
                  </form>

                {{-- EXTERNAL: just a confirmation --}}
                @else
                  @php
                    $platformName = $event->external_platform_name ?: __('external platform');
                    $buyer = $ticket->buyer ?: (!empty($ticket->buyer_customer_id) ? \App\Models\Customer::find($ticket->buyer_customer_id) : null);
                  @endphp
                  <h5>{{ __('External transfer') }} — {{ $platformName }}</h5>
                  <p>
                    {{ __('Go to :platform and transfer the ticket to the buyer using the details below.', ['platform' => $platformName]) }}
                    {{ __('Then click the button below to mark the transfer as completed.') }}
                  </p>

                  @if ($buyer)
                    <div class="alert alert-secondary">
                      <strong>{{ __('Buyer details for the transfer') }}:</strong>
                      <div class="mt-2">
                        @if (!empty($buyer->phone))
                          <div class="mb-1">
                            <i class="fas fa-mobile-alt mr-1"></i>
                            <strong>{{ __('Cellphone') }}:</strong> {{ $buyer->phone }}
                          </div>
                        @else
                          <div class="mb-1 text-muted">
                            <i class="fas fa-mobile-alt mr-1"></i>
                            <strong>{{ __('Cellphone') }}:</strong> {{ __('Not provided') }}
                          </div>
                        @endif
                        <div class="mb-1">
                          <i class="fas fa-user-tag mr-1"></i>
                          <strong>{{ __(':platform account', ['platform' => $platformName]) }}:</strong>
                          {{ $ticket->buyer_external_account ?: __('Not provided') }}
                        </div>
                        <div class="small text-muted mt-2">
                          {{ __('Transfer the ticket to this exact account on :platform.', ['platform' => $platformName]) }}
                        </div>
                      </div>
                    </div>
                  @endif

                  <form method="POST" action="{{ route('customer.tickets.transfer.external', $ticket->id) }}"
                        onsubmit="return confirm('{{ __('I confirm I have transferred the ticket on the external platform.') }}');">
                    @csrf
                    <button type="submit" class="btn btn-primary btn-lg"
                            @if ($availabilityNotYet) disabled @endif>
                      <i class="fas fa-check mr-1"></i>
                      {{ __('I confirm the transfer has been completed') }}
                    </button>
                  </form>
                @endif

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
      var box = document.getElementById('transfer-countdown-box');
      if (!box) return;
      var deadline = parseInt(box.getAttribute('data-deadline-ts'), 10);
      if (!deadline || isNaN(deadline)) return;
      var display = document.getElementById('transfer-countdown');

      function pad(n) { return n < 10 ? '0' + n : '' + n; }

      function tick() {
        var remaining = deadline - Date.now();
        if (remaining <= 0) {
          display.innerText = '00:00:00';
          box.classList.remove('alert-warning');
          box.classList.add('alert-danger');
          return false;
        }
        var sec = Math.floor(remaining / 1000);
        var h = Math.floor(sec / 3600);
        var m = Math.floor((sec % 3600) / 60);
        var s = sec % 60;
        display.innerText = pad(h) + ':' + pad(m) + ':' + pad(s);
        if (remaining < 60 * 60 * 1000) {
          box.classList.remove('alert-warning');
          box.classList.add('alert-danger');
        }
        return true;
      }

      tick();
      var handle = setInterval(function () {
        if (!tick()) clearInterval(handle);
      }, 1000);
    })();
  </script>
@endsection

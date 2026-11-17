@extends('frontend.layout')

@section('pageHeading')
  {{ __('Confirm Payment') }}
@endsection

@section('content')
  <div class="container py-5">
    <div class="row justify-content-center">
      <div class="col-lg-8">
        <div class="card shadow-sm">
          <div class="card-body">
            <h3 class="mb-4">{{ __('Confirm your payment') }}</h3>

            @if(session('error'))
              <div class="alert alert-danger">{{ session('error') }}</div>
            @endif
            @if(session('info'))
              <div class="alert alert-info">{{ session('info') }}</div>
            @endif

            <p class="mb-1">
              <strong>{{ __('Event') }}:</strong>
              {{ optional($ticket->eventContent)->title ?? ('#' . $ticket->event_id) }}
            </p>
            @if($ticket->zone)
              <p class="mb-1"><strong>{{ __('Zone') }}:</strong> {{ $ticket->zone }}</p>
            @endif
            @if($ticket->entrance_date)
              <p class="mb-1"><strong>{{ __('Entrance day') }}:</strong> {{ \Carbon\Carbon::parse($ticket->entrance_date)->translatedFormat('D, d M Y') }}</p>
            @endif
            @if(($eventTransferType ?? 'external') === 'external' && !empty($externalPlatformName))
              <p class="mb-1">
                <strong>{{ __('External transfer platform') }}:</strong> {{ $externalPlatformName }}
              </p>
            @endif

            <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 p-2 mb-2 border rounded bg-light">
              <div>
                <div class="small text-muted">{{ __('Quantity reserved') }}</div>
                <div class="h4 mb-0">1 {{ __('ticket') }}</div>
                <small class="text-muted">
                  {{ __('Unit price') }}: {{ number_format($ticket->price, 2) }}
                </small>
              </div>
              <div class="d-flex flex-column align-items-stretch" style="gap: 6px; min-width: 200px;">
                <form action="{{ route('customer.tickets.reservation.cancel', $ticket->id) }}"
                      method="POST"
                      onsubmit="return confirm(@json(__('Cancel this reservation? The hold will end and you will return to your bought tickets list.')));"
                      class="mb-0">
                  @csrf
                  <button type="submit" class="btn btn-outline-danger btn-sm btn-block">
                    <i class="fas fa-times mr-1"></i>{{ __('Cancel reservation') }}
                  </button>
                </form>
              </div>
            </div>
            <hr />
            <p class="mb-1">
              <strong>{{ __('Total') }}:</strong> {{ number_format($quote['gross'], 2) }}
            </p>
            <p class="mb-1 text-muted">
              {{ __('Platform balance available') }}: {{ number_format($quote['balance_available'], 2) }}
            </p>
            <p class="mb-1">
              <strong>{{ __('Balance applied as discount') }}:</strong>
              {{ number_format($quote['balance_used'], 2) }}
            </p>
            <p class="mb-3 h5">
              <strong>{{ __('Amount due now') }}:</strong>
              {{ number_format($quote['payable'], 2) }}
            </p>

            @if($ticket->payment_deadline)
              @php
                $deadlineIso = \Carbon\Carbon::parse($ticket->payment_deadline)->toIso8601String();
                $deadlineTs  = \Carbon\Carbon::parse($ticket->payment_deadline)->getTimestamp() * 1000;
                $deadlineHuman = \Carbon\Carbon::parse($ticket->payment_deadline)->format('Y-m-d H:i');
              @endphp
              <div id="payment-countdown-box" class="alert alert-warning d-flex align-items-center justify-content-between"
                   data-deadline-iso="{{ $deadlineIso }}"
                   data-deadline-ts="{{ $deadlineTs }}">
                <div>
                  {{ __('You have until') }}
                  <strong>{{ $deadlineHuman }}</strong>
                  {{ __('to complete this payment, otherwise the reservation is cancelled.') }}
                </div>
                <div class="text-right">
                  <div class="small text-muted text-uppercase" style="letter-spacing:1px;">
                    {{ __('Time left') }}
                  </div>
                  <div id="payment-countdown"
                       class="font-weight-bold"
                       style="font-size: 28px; line-height: 1; font-variant-numeric: tabular-nums;">
                    10:00
                  </div>
                </div>
              </div>

              <div class="progress mb-3" style="height: 6px;">
                <div id="payment-countdown-bar"
                     class="progress-bar bg-warning"
                     role="progressbar"
                     style="width: 100%;"
                     aria-valuemin="0" aria-valuemax="100" aria-valuenow="100"></div>
              </div>
            @endif

            <form method="POST" action="{{ route('customer.tickets.pay.confirm', $ticket->id) }}" id="payment-form">
              @csrf
              @if(($eventTransferType ?? 'external') === 'external')
                <div class="form-group mb-3 p-3 border rounded" style="background:#f8fafc;">
                  <label for="buyer_external_account" class="font-weight-bold">
                    {{ __('Your :platform account', ['platform' => $externalPlatformName ?: __('external platform')]) }}
                    <span class="text-danger">*</span>
                  </label>
                  <input type="text"
                         class="form-control @error('buyer_external_account') is-invalid @enderror"
                         name="buyer_external_account"
                         id="buyer_external_account"
                         value="{{ old('buyer_external_account', $ticket->buyer_external_account) }}"
                         placeholder="{{ __('e.g. your Ticketmaster email / username / account ID') }}"
                         maxlength="255"
                         required>
                  <small class="text-muted d-block mt-1">
                    {{ __('The seller will transfer the ticket to this account on :platform.', ['platform' => $externalPlatformName ?: __('the external platform')]) }}
                  </small>
                  @error('buyer_external_account')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                  @enderror
                </div>
              @endif
              <button type="submit" id="payment-submit-btn" class="btn btn-primary btn-lg">
                {{ __('Confirm payment') }}
              </button>
              <a href="{{ route('customer.tickets.bought') }}" class="btn btn-link">
                {{ __('Back to my tickets') }}
              </a>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
@endsection

@section('script')
  <script>
    (function () {
      var box = document.getElementById('payment-countdown-box');
      if (!box) return;

      var display = document.getElementById('payment-countdown');
      var bar = document.getElementById('payment-countdown-bar');
      var submitBtn = document.getElementById('payment-submit-btn');
      var form = document.getElementById('payment-form');

      var deadline = parseInt(box.getAttribute('data-deadline-ts'), 10);
      if (!deadline || isNaN(deadline)) return;

      // Try to estimate when the reservation started so the progress bar has
      // a sensible width. Fallback to a 10-minute window.
      var windowMs = 10 * 60 * 1000;
      var startMs = deadline - windowMs;

      function pad(n) { return n < 10 ? '0' + n : '' + n; }

      function tick() {
        var now = Date.now();
        var remaining = deadline - now;

        if (remaining <= 0) {
          display.innerText = '00:00';
          display.classList.remove('text-warning');
          display.classList.add('text-danger');
          bar.style.width = '0%';
          bar.classList.remove('bg-warning');
          bar.classList.add('bg-danger');

          if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerText = "{{ __('Reservation expired') }}";
          }

          // Show a message & redirect back to "My tickets" after a short delay
          if (!box.dataset.expiredNotified) {
            box.dataset.expiredNotified = '1';
            box.classList.remove('alert-warning');
            box.classList.add('alert-danger');
            box.querySelector('div').innerHTML =
              "<strong>{{ __('Your 10-minute reservation window has expired.') }}</strong>";
            setTimeout(function () {
              window.location.href = "{{ route('customer.tickets.bought') }}";
            }, 2500);
          }
          return false;
        }

        var totalSec = Math.floor(remaining / 1000);
        var mm = Math.floor(totalSec / 60);
        var ss = totalSec % 60;
        display.innerText = pad(mm) + ':' + pad(ss);

        var elapsed = now - startMs;
        var pct = Math.max(0, Math.min(100, 100 - (elapsed / windowMs) * 100));
        bar.style.width = pct + '%';
        bar.setAttribute('aria-valuenow', Math.round(pct));

        // Colour cue: last minute turns red
        if (remaining <= 60 * 1000) {
          display.classList.remove('text-muted');
          display.classList.add('text-danger');
          bar.classList.remove('bg-warning');
          bar.classList.add('bg-danger');
          box.classList.remove('alert-warning');
          box.classList.add('alert-danger');
        }
        return true;
      }

      tick();
      var handle = setInterval(function () {
        if (!tick()) clearInterval(handle);
      }, 1000);

      if (form) {
        form.addEventListener('submit', function () {
          if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerText = "{{ __('Processing...') }}";
          }
        });
      }
    })();
  </script>
@endsection

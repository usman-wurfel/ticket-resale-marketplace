@extends('frontend.layout')

@section('pageHeading')
  {{ __('Resale purchase details') }}
@endsection

@section('hero-section')
  <section class="page-banner overlay pt-120 pb-125 rpt-90 rpb-95 lazy"
    data-bg="{{ asset('assets/admin/img/' . $basicInfo->breadcrumb) }}">
    <div class="container">
      <div class="banner-inner">
        <h2 class="page-title">{{ __('Resale purchase details') }}</h2>
        <nav aria-label="breadcrumb">
          <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('index') }}">{{ __('Home') }}</a></li>
            <li class="breadcrumb-item active">{{ __('Resale purchase details') }}</li>
          </ol>
        </nav>
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
              <h4>{{ __('Resale purchase details') }}</h4>
              <a href="{{ route('customer.tickets.bought') }}" class="btn btn-sm btn-secondary">{{ __('Back') }}</a>
            </div>
            <div class="main-info">
              @php
                $unitPrice = (float) $ticket->price;
                $totalAmount = $unitPrice * (int) $ticket->quantity;
                $resaleDetailStatus = boughtTicketDisplayStatus('resale', $ticket);
                $ec = App\Models\Event\EventContent::where('event_id', $ticket->event_id)
                    ->where('language_id', $currentLanguageInfo->id)
                    ->select('title', 'slug', 'event_id')
                    ->first();
                if (empty($ec)) {
                    $ec = App\Models\Event\EventContent::where('event_id', $ticket->event_id)
                        ->select('title', 'slug', 'event_id')
                        ->first();
                }
              @endphp
              <ul class="list-unstyled mb-4" style="font-size: 15px; line-height: 1.9;">
                <li>
                  <strong>{{ __('Status') }}:</strong>
                  <span class="badge {{ $resaleDetailStatus['badge'] }}">{{ $resaleDetailStatus['label'] }}</span>
                </li>
                @if ($ec)
                  <li>
                    <strong>{{ __('Event') }}:</strong>
                    @if (!empty($ec->slug))
                      <a href="{{ route('event.details', [$ec->slug, $ticket->event_id]) }}">{{ $ec->title }}</a>
                    @else
                      {{ $ec->title }}
                    @endif
                  </li>
                @endif
                <li><strong>{{ __('Booking ID') }}:</strong> {{ $ticket->booking_id ?: '-' }}</li>
                <li><strong>{{ __('Quantity') }}:</strong> {{ $ticket->quantity }}</li>
                <li><strong>{{ __('Price') }} ({{ __('each') }}):</strong> {{ number_format($unitPrice, 2) }}</li>
                <li><strong>{{ __('Total') }}:</strong> {{ number_format($totalAmount, 2) }}</li>
                <li><strong>{{ __('Reserved until') }}:</strong> {{ boughtTicketReservedUntilCell('resale', $ticket) }}</li>
                <li class="text-muted"><em>{{ __('Resale is anonymous — the platform coordinates the other party; their identity is not shown.') }}</em></li>
              </ul>

              @php
                $event = \App\Models\Event::find($ticket->event_id);
                $transferType = $event ? ($event->event_ticket_transfer_type ?? 'external') : 'external';
                $buyerPdfs = [];
                if (!empty($ticket->ticket_pdfs) && $ticket->transfer_status === \App\Models\Event\ResaleTicket::TRANSFER_DONE) {
                    $decoded = json_decode((string) $ticket->ticket_pdfs, true);
                    if (is_array($decoded)) { $buyerPdfs = array_values(array_filter($decoded)); }
                }
              @endphp

              {{-- Payment pending: show pay button + countdown reminder --}}
              @if ($ticket->status === 'reserved' && $ticket->payment_deadline && \Carbon\Carbon::parse($ticket->payment_deadline)->isFuture())
                <div class="alert alert-warning">
                  <strong>{{ __('Payment pending.') }}</strong>
                  {{ __('Please complete the payment before the 10-minute window closes.') }}
                  <a href="{{ route('customer.tickets.pay', $ticket->id) }}" class="btn btn-sm btn-success ml-2">
                    <i class="fas fa-credit-card mr-1"></i> {{ __('Pay now') }}
                  </a>
                </div>
              @endif

              {{-- Paid, waiting for seller transfer --}}
              @if ($ticket->status === 'paid' && in_array($ticket->transfer_status, [\App\Models\Event\ResaleTicket::TRANSFER_PENDING, null], true))
                <div class="alert alert-info">
                  <i class="fas fa-hourglass-half mr-1"></i>
                  <strong>{{ __('Waiting for the seller to transfer the ticket.') }}</strong>
                  @if ($ticket->transfer_deadline)
                    <br>{{ __('Seller has until') }}
                    <strong>{{ \Carbon\Carbon::parse($ticket->transfer_deadline)->format('Y-m-d H:i') }}</strong>.
                    {{ __('If they miss the deadline, you will be refunded and can ask for an equivalent ticket.') }}
                  @endif
                </div>
              @endif

              {{-- Transfer failed (seller missed deadline): reassignment button --}}
              @if ($ticket->transfer_status === \App\Models\Event\ResaleTicket::TRANSFER_MISSED)
                <div class="alert alert-danger">
                  <i class="fas fa-exclamation-triangle mr-1"></i>
                  <strong>{{ __('The seller missed the transfer deadline.') }}</strong>
                  {{ __('Your payment has been refunded to your wallet.') }}
                  <form action="{{ route('customer.tickets.reassign', $ticket->id) }}" method="POST" class="mt-2 m-0">
                    @csrf
                    <button type="submit" class="btn btn-warning btn-sm">
                      <i class="fas fa-sync-alt mr-1"></i>
                      {{ __('Reassign me an equivalent ticket') }}
                    </button>
                  </form>
                </div>
              @endif

              {{-- Transferred: show delivery --}}
              @if ($ticket->transfer_status === \App\Models\Event\ResaleTicket::TRANSFER_DONE)
                <div class="alert alert-success">
                  <i class="fas fa-check-circle mr-1"></i>
                  <strong>{{ __('Ticket transferred.') }}</strong>
                  @if ($ticket->transferred_at)
                    {{ __('on') }} {{ \Carbon\Carbon::parse($ticket->transferred_at)->format('Y-m-d H:i') }}.
                  @endif
                </div>

                @if ($transferType === 'internal' && count($buyerPdfs) > 0)
                  <h5 class="mt-3">{{ __('Your tickets') }}</h5>
                  <p class="text-muted small">
                    {{ __('Download your PDF ticket(s) below. Keep them safe - they contain the QR code used at the entrance.') }}
                  </p>
                  <ul class="list-group mb-3">
                    @foreach ($buyerPdfs as $idx => $pdf)
                      <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span>
                          <i class="fas fa-file-pdf text-danger mr-2"></i>
                          {{ __('Ticket') }} {{ $idx + 1 }}
                        </span>
                        <a href="{{ route('customer.tickets.pdf_download', [$ticket->id, $idx]) }}"
                           class="btn btn-sm btn-primary" target="_blank">
                          <i class="fas fa-download mr-1"></i> {{ __('Download PDF') }}
                        </a>
                      </li>
                    @endforeach
                  </ul>
                @elseif ($transferType === 'external')
                  <div class="alert alert-secondary">
                    <i class="fas fa-info-circle mr-1"></i>
                    {{ __('The seller has transferred your ticket on the external platform. Please check your email / external account.') }}
                  </div>
                @endif

                @if ($ticket->payment_release_due_at && empty($ticket->payment_released_at))
                  <p class="small text-muted">
                    <i class="fas fa-lock mr-1"></i>
                    {{ __('If everything is fine, the seller will be paid at') }}
                    <strong>{{ \Carbon\Carbon::parse($ticket->payment_release_due_at)->format('Y-m-d H:i') }}</strong>
                    ({{ __('48h after the event') }}).
                    {{ __('You can open a litigation until that time if there is a problem.') }}
                  </p>
                @endif
              @endif

              <div class="mt-3 d-flex flex-wrap align-items-center" style="gap: 8px;">
                @if (boughtTicketResaleBuyerCanCancel($ticket))
                  <form action="{{ route('customer.tickets.reservation.cancel', $ticket->id) }}"
                        method="POST"
                        class="d-inline mb-0"
                        onsubmit="return confirm(@json(__('Cancel this reservation? The tickets will be released back to the seller.')));">
                    @csrf
                    <button type="submit" class="btn btn-outline-secondary btn-sm">
                      {{ __('Cancel reservation') }}
                    </button>
                  </form>
                @endif
                @include('frontend.customer.partials.btn-action-incident-modern', [
                  'href' => route('customer.support_tickert.create', ['source_type' => 'resale_ticket', 'source_id' => $ticket->id]),
                  'title' => __('Open incident / litigation'),
                ])
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
@endsection

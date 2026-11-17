@extends('frontend.layout')

@section('pageHeading')
  {{ __('Bought tickets') }}
@endsection

@section('hero-section')
  <section class="page-banner overlay pt-120 pb-125 rpt-90 rpb-95 lazy"
    data-bg="{{ asset('assets/admin/img/' . $basicInfo->breadcrumb) }}">
    <div class="container">
      <div class="banner-inner">
        <h2 class="page-title">{{ __('Bought tickets') }}</h2>
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
            <div class="title">
              <h4>{{ __('My bought tickets') }}</h4>
            </div>
            <div class="main-info">
              <div class="table-responsive">
                <table class="table table-striped">
                  <thead>
                    <tr>
                      <th>{{ __('Booking ID') }}</th>
                      <th>{{ __('Event') }}</th>
                      <th>{{ __('Source') }}</th>
                      <th>{{ __('Price') }} <small class="text-muted">({{ __('each') }})</small></th>
                      <th>{{ __('Qty') }}</th>
                      <th>{{ __('Total') }}</th>
                      <th>{{ __('Status') }}</th>
                      <th>{{ __('Hold until') }}</th>
                      <th class="text-center">{{ __('View') }}</th>
                    </tr>
                  </thead>
                  <tbody>
                    @forelse ($purchases as $row)
                      @if ($row['kind'] === 'platform')
                        @php
                          $booking = $row['booking'];
                          $qty = max(1, (int) $booking->quantity);
                          $unitPrice =
                              $qty > 0 ? round((float) $booking->price / $qty, 2) : (float) $booking->price;
                          $totalAmount = (float) $booking->price + (float) ($booking->tax ?? 0);
                          $event = $booking
                              ->event()
                              ->where('language_id', $currentLanguageInfo->id)
                              ->select('title', 'slug', 'event_id')
                              ->first();
                          if (empty($event)) {
                              $language = App\Models\Language::where('is_default', 1)->first();
                              $event = $booking
                                  ->event()
                                  ->where('language_id', $language->id)
                                  ->select('title', 'slug', 'event_id')
                                  ->first();
                          }
                          $platformStatus = boughtTicketDisplayStatus('platform', $booking);
                        @endphp
                        <tr>
                          <td><strong>#{{ $booking->booking_id }}</strong></td>
                          <td>
                            @if (!empty($event))
                              <a href="{{ route('event.details', [$event->slug, $event->event_id]) }}">
                                {{ strlen($event->title) > 45 ? mb_substr($event->title, 0, 45) . '…' : $event->title }}
                              </a>
                            @else
                              —
                            @endif
                          </td>
                          <td>{{ __('Platform') }}</td>
                          <td>{{ number_format($unitPrice, 2) }}</td>
                          <td>{{ $booking->quantity }}</td>
                          <td><strong>{{ number_format($totalAmount, 2) }}</strong></td>
                          <td>
                            <span class="badge {{ $platformStatus['badge'] }}">{{ $platformStatus['label'] }}</span>
                          </td>
                          <td>{{ boughtTicketReservedUntilCell('platform', $booking) }}</td>
                          <td class="text-center">
                            @include('frontend.customer.partials.btn-action-modern', [
                              'href' => route('customer.booking_details', ['id' => $booking->id]) . '?from=bought',
                              'title' => __('View'),
                              'compact' => true,
                            ])
                            @include('frontend.customer.partials.btn-action-incident-modern', [
                              'href' => route('customer.support_tickert.create', ['source_type' => 'platform_booking', 'source_id' => $booking->id]),
                              'title' => __('Open incident'),
                              'compact' => true,
                            ])
                          </td>
                        </tr>
                      @else
                        @php
                          $ticket = $row['resale'];
                          $unitPrice = (float) $ticket->price;
                          $totalAmount = $unitPrice * (int) $ticket->quantity;
                          $resaleEvent = App\Models\Event\EventContent::where('event_id', $ticket->event_id)
                              ->where('language_id', $currentLanguageInfo->id)
                              ->select('title', 'slug', 'event_id')
                              ->first();
                          if (empty($resaleEvent)) {
                              $resaleEvent = App\Models\Event\EventContent::where('event_id', $ticket->event_id)
                                  ->select('title', 'slug', 'event_id')
                                  ->first();
                          }
                          $resaleStatus = boughtTicketDisplayStatus('resale', $ticket);
                        @endphp
                        <tr>
                          <td><strong>{{ $ticket->booking_id }}</strong></td>
                          <td>
                            @if (!empty($resaleEvent?->slug))
                              <a href="{{ route('event.details', [$resaleEvent->slug, $ticket->event_id]) }}">
                                {{ strlen($resaleEvent->title ?? '') > 45 ? mb_substr($resaleEvent->title, 0, 45) . '…' : $resaleEvent->title ?? '—' }}
                              </a>
                            @else
                              {{ $resaleEvent->title ?? '—' }}
                            @endif
                          </td>
                          <td>{{ __('Resale') }}</td>
                          <td>{{ number_format($unitPrice, 2) }}</td>
                          <td>{{ $ticket->quantity }}</td>
                          <td><strong>{{ number_format($totalAmount, 2) }}</strong></td>
                          <td>
                            <span class="badge {{ $resaleStatus['badge'] }}">{{ $resaleStatus['label'] }}</span>
                          </td>
                          <td>{{ boughtTicketReservedUntilCell('resale', $ticket) }}</td>
                          <td class="text-center">
                            <span class="d-inline-flex flex-wrap align-items-center justify-content-center" style="gap: 6px;">
                            @if ($ticket->status === 'reserved' && $ticket->payment_deadline && \Carbon\Carbon::parse($ticket->payment_deadline)->isFuture())
                              <a href="{{ route('customer.tickets.pay', $ticket->id) }}"
                                 class="btn btn-success btn-sm"
                                 title="{{ __('Pay now') }}">
                                <i class="fas fa-credit-card mr-1"></i> {{ __('Pay') }}
                              </a>
                            @endif
                            @if ($ticket->status === 'paid' && in_array($ticket->transfer_status, ['missed_deadline', 'failed']))
                              <form action="{{ route('customer.tickets.reassign', $ticket->id) }}" method="POST"
                                    onsubmit="return confirm('{{ __('Request reassignment to another seller?') }}');"
                                    style="display:inline;">
                                @csrf
                                <button type="submit" class="btn btn-warning btn-sm" title="{{ __('Reassign') }}">
                                  <i class="fas fa-sync-alt mr-1"></i> {{ __('Reassign') }}
                                </button>
                              </form>
                            @endif
                            @include('frontend.customer.partials.btn-action-modern', [
                              'href' => route('customer.tickets.bought.resale', ['id' => $ticket->id]),
                              'title' => __('View'),
                              'compact' => true,
                            ])
                            @if (boughtTicketResaleBuyerCanCancel($ticket))
                              <form action="{{ route('customer.tickets.reservation.cancel', $ticket->id) }}"
                                    method="POST"
                                    class="d-inline mb-0"
                                    onsubmit="return confirm(@json(__('Cancel this reservation? The tickets will be released back to the seller.')));">
                                @csrf
                                <button type="submit" class="btn btn-outline-secondary btn-sm" title="{{ __('Cancel reservation') }}">
                                  {{ __('Cancel reservation') }}
                                </button>
                              </form>
                            @endif
                            @include('frontend.customer.partials.btn-action-incident-modern', [
                              'href' => route('customer.support_tickert.create', ['source_type' => 'resale_ticket', 'source_id' => $ticket->id]),
                              'title' => __('Open incident'),
                              'compact' => true,
                            ])
                            </span>
                          </td>
                        </tr>
                      @endif
                    @empty
                      <tr>
                        <td colspan="9" class="text-center text-muted">{{ __('No purchases found.') }}</td>
                      </tr>
                    @endforelse
                  </tbody>
                </table>
              </div>
              @if ($purchases->hasPages())
                {{ $purchases->withQueryString()->links() }}
              @endif
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
@endsection

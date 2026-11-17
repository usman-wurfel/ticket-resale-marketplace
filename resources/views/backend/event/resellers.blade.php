@extends('backend.layout')

@section('content')
  <div class="page-header">
    <h4 class="page-title">Resellers</h4>
    <ul class="breadcrumbs">
      <li class="nav-home">
        <a href="{{ route('admin.dashboard') }}">
          <i class="flaticon-home"></i>
        </a>
      </li>
      <li class="separator"><i class="flaticon-right-arrow"></i></li>
      <li class="nav-item"><a href="#">Ticket management</a></li>
      <li class="separator"><i class="flaticon-right-arrow"></i></li>
      <li class="nav-item">
        <a href="{{ route('admin.event_management.event', ['language' => optional($language)->code]) }}">All tickets</a>
      </li>
      <li class="separator"><i class="flaticon-right-arrow"></i></li>
      <li class="nav-item"><a href="#">Resellers</a></li>
    </ul>
  </div>

  <div class="row">
    <div class="col-lg-12">
      <div class="card">
        <div class="card-header">
          <div class="row align-items-center">
            <div class="col-lg-5 mb-2 mb-lg-0">
              <h5 class="mb-0">Event: {{ $eventTitle }}</h5>
              <small class="text-muted">Ticket type: {{ ucfirst($event->event_ticket_transfer_type ?? 'external') }}</small>
            </div>
            <div class="col-lg-7">
              <form method="GET" action="{{ route('admin.event_management.event.resellers', ['id' => $event->id]) }}" class="form-inline justify-content-lg-end flex-wrap" style="gap:8px;">
                <input type="hidden" name="language" value="{{ optional($language)->code }}">
                <input type="text" name="username" class="form-control form-control-sm"
                  placeholder="{{ __('Username') }}"
                  value="{{ $usernameFilter ?? '' }}" style="min-width:160px;">
                <select name="status" class="form-control form-control-sm" style="min-width:140px;">
                  <option value="">{{ __('All statuses') }}</option>
                  @foreach ($statusOptions ?? [] as $status)
                    <option value="{{ $status }}" {{ ($statusFilter ?? '') === $status ? 'selected' : '' }}>
                      {{ ucfirst($status) }}
                    </option>
                  @endforeach
                </select>
                <button type="submit" class="btn btn-primary btn-sm">{{ __('Filter') }}</button>
                @if (!empty($usernameFilter) || !empty($statusFilter))
                  <a href="{{ route('admin.event_management.event.resellers', ['id' => $event->id, 'language' => optional($language)->code]) }}"
                    class="btn btn-outline-secondary btn-sm">{{ __('Reset') }}</a>
                @endif
              </form>
            </div>
          </div>
        </div>
        <div class="card-body">
          @php
            $summaryBySeller = collect($resellerSummary)->keyBy(function ($row) {
                return optional($row['seller'])->id;
            });
          @endphp
          <div class="table-responsive">
            <table class="table table-striped">
              <thead>
                <tr>
                  <th>{{ __('Reseller') }}</th>
                  <th>{{ __('Buyer') }}</th>
                  <th>{{ __('Price') }}</th>
                  <th>{{ __('Qty') }}</th>
                  <th>{{ __('Status') }}</th>
                  <th>{{ __('Sold') }}</th>
                  <th>{{ __('In hand') }}</th>
                  <th>{{ __('Actions') }}</th>
                </tr>
              </thead>
              <tbody>
                @forelse($resellerTickets as $ticket)
                  @php
                    $sellerSummary = $summaryBySeller->get(optional($ticket->seller)->id);
                    $soldCount = (int) ($sellerSummary['sold_qty'] ?? 0);
                    $inHandCount = (int) ($sellerSummary['pending_qty'] ?? 0);
                    $totalQty = $soldCount + $inHandCount;
                    $buyer = $ticket->buyer;
                    if (!$buyer && $ticket->relationLoaded('children')) {
                      $childWithBuyer = $ticket->children
                        ->filter(function ($child) {
                          return !empty($child->buyer_customer_id);
                        })
                        ->sortByDesc('id')
                        ->first();
                      $buyer = $childWithBuyer?->buyer;
                    }
                  @endphp
                  <tr>
                    <td>
                      @if (!empty($ticket->seller))
                        <a href="{{ route('admin.customer_management.customer_details', ['id' => $ticket->seller->id, 'language' => optional($defaultLang)->code]) }}">
                          {{ $ticket->seller->username }}
                        </a>
                      @else
                        -
                      @endif
                    </td>
                    <td>
                      @if (!empty($buyer))
                        <a href="{{ route('admin.customer_management.customer_details', ['id' => $buyer->id, 'language' => optional($defaultLang)->code]) }}">
                          {{ $buyer->username ?: trim(($buyer->fname ?? '') . ' ' . ($buyer->lname ?? '')) ?: '-' }}
                        </a>
                      @else
                        -
                      @endif
                    </td>
                    <td>{{ $ticket->price }}</td>
                    <td>{{ $totalQty }}</td>
                    <td><span class="badge badge-secondary">{{ ucfirst($ticket->status) }}</span></td>
                    <td>{{ $soldCount }}</td>
                    <td>{{ $inHandCount }}</td>
                    <td>
                      @if (($event->event_ticket_transfer_type ?? 'external') === 'internal')
                        <a href="{{ route('admin.event_management.event.reseller_ticket_pdfs', ['id' => $event->id, 'ticketId' => $ticket->id]) }}"
                          class="btn btn-sm btn-info mb-1" title="View PDFs">
                          <i class="fas fa-file-pdf"></i>
                        </a>
                      @endif
                      <form action="{{ route('admin.event_management.event.delete_reseller_ticket', ['id' => $event->id, 'ticketId' => $ticket->id]) }}"
                        method="POST" class="d-inline-block" onsubmit="return confirm('Are you sure you want to remove this reseller ticket?');">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-danger mb-1" title="Delete">
                          <i class="fas fa-trash-alt"></i>
                        </button>
                      </form>
                    </td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="8" class="text-center">No reseller listings found for this event.</td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
@endsection

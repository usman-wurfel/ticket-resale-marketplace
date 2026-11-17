@extends('backend.layout')

@section('content')
  <div class="page-header">
    <h4 class="page-title">Seller details</h4>
    <ul class="breadcrumbs">
      <li class="nav-home">
        <a href="{{ route('admin.dashboard') }}"><i class="flaticon-home"></i></a>
      </li>
      <li class="separator"><i class="flaticon-right-arrow"></i></li>
      <li class="nav-item"><a href="#">Seller management</a></li>
      <li class="separator"><i class="flaticon-right-arrow"></i></li>
      <li class="nav-item"><a href="#">Seller details</a></li>
    </ul>
  </div>

  <div class="row">
    <div class="col-md-4">
      <div class="card">
        <div class="card-header">
          <div class="card-title">Seller information</div>
        </div>
        <div class="card-body">
          <p><strong>Name:</strong> {{ $customer->fname }} {{ $customer->lname }}</p>
          <p><strong>Username:</strong> {{ $customer->username }}</p>
          <p><strong>Email:</strong> {{ $customer->email }}</p>
          <p><strong>Phone:</strong> {{ $customer->phone ?: '-' }}</p>
          <hr>
          <p><strong>Total sold:</strong> {{ $soldQty }}</p>
          <p><strong>On hold:</strong> {{ $onHoldQty }}</p>
          <p><strong>In hand:</strong> {{ $inHandQty }}</p>
          <p><strong>Total earnings:</strong> {{ number_format($earnings, 2) }}</p>
          <a href="{{ route('admin.seller_management.registered_seller', ['language' => $defaultLang->code]) }}" class="btn btn-sm btn-info">
            <i class="fas fa-backward mr-1"></i>Back
          </a>
        </div>
      </div>
    </div>
    <div class="col-md-8">
      <div class="card mb-4">
        <div class="card-header">
          <div class="card-title">Active listings</div>
        </div>
        <div class="card-body">
          @if (count($listings) === 0)
            <p class="mb-0">No listings found.</p>
          @else
            <div class="table-responsive">
              <table class="table table-striped">
                <thead>
                  <tr>
                    <th>Event</th>
                    <th>Price</th>
                    <th>Qty</th>
                    <th>Status</th>
                    <th>Sold</th>
                    <th>In hand</th>
                    <th>PDFs</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  @foreach ($listings as $ticket)
                    @php
                      $st = $listingStats[$ticket->id] ?? ['sold_qty' => 0, 'in_hand_qty' => 0, 'show_pdf' => false, 'pdf_count' => 0];
                      $eventTitle = optional($ticket->eventContent)->title ?: ('Event #' . $ticket->event_id);
                    @endphp
                    <tr>
                      <td>{{ $eventTitle }}</td>
                      <td>{{ $ticket->price }}</td>
                      <td>{{ $ticket->quantity }}</td>
                      <td><span class="badge badge-secondary">{{ ucfirst($ticket->status) }}</span></td>
                      <td>{{ (int) $st['sold_qty'] }}</td>
                      <td>{{ (int) $st['in_hand_qty'] }}</td>
                      <td>{{ (int) $st['pdf_count'] }}</td>
                      <td>
                        @if (!empty($st['show_pdf']))
                          <a class="btn btn-sm btn-info" title="View PDFs"
                            href="{{ route('admin.event_management.event.reseller_ticket_pdfs', ['id' => $ticket->event_id, 'ticketId' => $ticket->id]) }}">
                            <i class="fas fa-file-pdf"></i>
                          </a>
                        @endif
                        <form class="d-inline-block" method="POST"
                          action="{{ route('admin.event_management.event.delete_reseller_ticket', ['id' => $ticket->event_id, 'ticketId' => $ticket->id]) }}"
                          onsubmit="return confirm('Are you sure you want to remove this reseller ticket?');">
                          @csrf
                          <button class="btn btn-sm btn-danger" type="submit" title="Delete">
                            <i class="fas fa-trash-alt"></i>
                          </button>
                        </form>
                      </td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
          @endif
        </div>
      </div>

      <div class="card">
        <div class="card-header">
          <div class="card-title">Sold tickets history</div>
        </div>
        <div class="card-body">
          @if (count($soldTickets) === 0)
            <p class="mb-0">No sold resale tickets found.</p>
          @else
            <div class="table-responsive">
              <table class="table table-striped">
                <thead>
                  <tr>
                    <th>Booking ID</th>
                    <th>Event</th>
                    <th>Buyer</th>
                    <th>Qty</th>
                    <th>Amount</th>
                    <th>Date</th>
                  </tr>
                </thead>
                <tbody>
                  @foreach ($soldTickets as $sold)
                    @php
                      $buyerName = trim(($sold->buyer->fname ?? '') . ' ' . ($sold->buyer->lname ?? ''));
                      if ($buyerName === '') {
                          $buyerName = $sold->buyer->username ?? '-';
                      }
                      $eventTitle = optional($sold->eventContent)->title ?: ('Event #' . $sold->event_id);
                    @endphp
                    <tr>
                      <td>{{ $sold->booking_id ? '#' . $sold->booking_id : '-' }}</td>
                      <td>{{ $eventTitle }}</td>
                      <td>{{ $buyerName }}</td>
                      <td>{{ (int) $sold->quantity }}</td>
                      @php
                        $netAmount = (float) ($soldTicketNetAmounts[$sold->id] ?? ((float) $sold->price * (int) $sold->quantity));
                      @endphp
                      <td>{{ number_format($netAmount, 2) }}</td>
                      <td>{{ \Carbon\Carbon::parse($sold->created_at)->format('d M Y h:i A') }}</td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
          @endif
        </div>
      </div>
    </div>
  </div>
@endsection


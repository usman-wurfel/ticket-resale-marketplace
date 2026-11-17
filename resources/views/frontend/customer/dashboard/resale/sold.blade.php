@extends('frontend.layout')

@section('pageHeading')
  Resell tickets
@endsection

@section('hero-section')
  <section class="page-banner overlay pt-120 pb-125 rpt-90 rpb-95 lazy"
    data-bg="{{ asset('assets/admin/img/' . $basicInfo->breadcrumb) }}">
    <div class="container">
      <div class="banner-inner">
        <h2 class="page-title">Resell tickets</h2>
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
              <h4>My Resell tickets</h4>
            </div>
            <div class="main-info">
              @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
              @endif
              @if (session('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
              @endif
              <div class="table-responsive">
                <table class="table table-striped">
                  <thead>
                    <tr>
                      <th>Ticket</th>
                      <th>Price</th>
                      <th>Listed Qty</th>
                      <th>Sold / Reserved</th>
                      <th>Remaining</th>
                      <th>Status</th>
                      <th>Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    @forelse($tickets as $ticket)
                      @php
                        $origQty = $ticket->original_quantity ?? $ticket->quantity;

                        // A listing is "sold" from the seller's POV once the buyer has paid.
                        // The full lifecycle is: listed -> reserved -> paid -> transferred -> sold
                        // ('sold' only fires after payment release, which is 48h after the event),
                        // so we count paid / transferred / sold all as sold for the dashboard totals.
                        $soldStatuses = ['paid', 'transferred', 'sold'];

                        $childSold = $ticket->children
                            ? $ticket->children->whereIn('status', $soldStatuses)->sum('quantity')
                            : 0;
                        $childReserved = $ticket->children
                            ? $ticket->children->where('status', 'reserved')->sum('quantity')
                            : 0;

                        $selfSold = in_array($ticket->status, $soldStatuses) ? $ticket->quantity : 0;
                        $selfReserved = ($ticket->status == 'reserved') ? $ticket->quantity : 0;

                        $totalSold = $childSold + $selfSold;
                        $totalReserved = $childReserved + $selfReserved;

                        $remaining = 0;
                        if ($ticket->status == 'listed') {
                            $remaining = $ticket->quantity;
                        }
                      @endphp
                      <tr>
                        <td>{{ optional($ticket->eventContent)->title ?? '-' }}</td>
                        <td>{{ $ticket->price }}</td>
                        <td>{{ $origQty }}</td>
                        <td>
                          {{ $totalSold }} Sold <br>
                          <small class="text-muted">{{ $totalReserved }} Reserved</small>
                        </td>
                        <td>{{ $remaining }}</td>
                        <td>
                          @if($ticket->status == 'listed')
                            <span class="badge badge-info">Listed</span>
                          @elseif($ticket->status == 'reserved')
                            <span class="badge badge-warning">Reserved</span>
                          @elseif($ticket->status == 'paid')
                            @if($ticket->transfer_status === 'transferred')
                              <span class="badge badge-success">{{ __('Transferred') }}</span>
                            @else
                              <span class="badge badge-info">{{ __('Paid · transfer pending') }}</span>
                            @endif
                          @elseif($ticket->status == 'transferred')
                            <span class="badge badge-success">{{ __('Transferred') }}</span>
                          @elseif($ticket->status == 'sold')
                            <span class="badge badge-success">Sold</span>
                          @else
                            <span class="badge badge-secondary">{{ ucfirst($ticket->status) }}</span>
                          @endif
                        </td>
                        <td>
                          <div class="d-flex align-items-center flex-wrap" style="gap: 8px;">
                          @if($ticket->status == 'listed')
                            <div class="d-flex align-items-center flex-wrap" style="gap: 8px;">
                              @include('frontend.customer.partials.btn-action-edit-modern', [
                                'href' => route('customer.tickets.sell.edit', $ticket->id),
                                'title' => __('Edit'),
                                'compact' => true,
                              ])
                              @include('frontend.customer.partials.btn-action-delete-modern', [
                                'action' => route('customer.tickets.sell.destroy', $ticket->id),
                                'title' => __('Delete'),
                                'compact' => true,
                                'confirm' => __('Are you sure you want to delete this listing?'),
                              ])
                            </div>
                          @endif
                          @if($ticket->status === 'paid' && in_array($ticket->transfer_status, ['pending', 'pending_transfer', null]))
                            <a href="{{ route('customer.tickets.transfer', $ticket->id) }}"
                               class="btn btn-warning btn-sm"
                               title="{{ __('Transfer ticket to buyer') }}">
                              <i class="fas fa-paper-plane mr-1"></i>{{ __('Transfer') }}
                            </a>
                          @elseif(($ticket->status === 'paid' || $ticket->status === 'transferred') && $ticket->transfer_status === 'transferred')
                            <a href="{{ route('customer.tickets.transfer', $ticket->id) }}"
                               class="btn btn-outline-success btn-sm"
                               title="{{ __('View transfer details') }}">
                              <i class="fas fa-check-circle mr-1"></i>{{ __('Transferred') }}
                            </a>
                          @endif
                          <button type="button" class="btn-action-modern btn-action-modern--sm"
                            data-toggle="modal" data-target="#buyingHistoryModal{{ $ticket->id }}"
                            title="Buying history" aria-label="Buying history">
                            <span class="btn-action-modern__icon" aria-hidden="true">
                              <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M3 3v5h5" />
                                <path d="M3.05 13A9 9 0 1 0 6 5.3L3 8" />
                                <path d="M12 7v5l3 3" />
                              </svg>
                            </span>
                          </button>
                          </div>
                        </td>
                      </tr>
                      
                      {{-- Show child reservations to confirm --}}
                      @if($ticket->children && $ticket->children->where('status', 'reserved')->count() > 0)
                          @foreach($ticket->children->where('status', 'reserved') as $child)
                          <tr style="background-color: rgba(255,193,7,0.1);">
                              <td colspan="5" class="text-right">
                                      <i class="fas fa-level-up-alt fa-rotate-90 text-muted mr-2"></i>
                                  {{ __('Reserved') }} ({{ __('Booking ID') }}: <strong>{{ $child->booking_id ?: '-' }}</strong>,
                                  {{ __('Qty') }}: <strong>{{ $child->quantity }}</strong>) {{ __('until') }} {{ reservedUntilRemainingMinutes($child->reserved_until) }}
                                  <span class="text-muted d-block small">{{ __('Buyer identity is hidden.') }}</span>
                              </td>
                              <td colspan="2">
                                  @include('frontend.customer.partials.btn-confirm-sold-modern', [
                                    'action' => route('customer.tickets.complete', $child->id),
                                    'confirm' => __('Confirm payment received and mark as sold?'),
                                  ])
                              </td>
                          </tr>
                          @endforeach
                      @endif

                      {{-- Also show if the parent itself is reserved (happens when exact quantity was reserved) --}}
                      @if($ticket->status === 'reserved')
                          <tr style="background-color: rgba(255,193,7,0.1);">
                              <td colspan="5" class="text-right">
                                      <i class="fas fa-level-up-alt fa-rotate-90 text-muted mr-2"></i>
                                  {{ __('Reserved') }} ({{ __('Booking ID') }}: <strong>{{ $ticket->booking_id ?: '-' }}</strong>,
                                  {{ __('Qty') }}: <strong>{{ $ticket->quantity }}</strong>) {{ __('until') }} {{ reservedUntilRemainingMinutes($ticket->reserved_until) }}
                                  <span class="text-muted d-block small">{{ __('Buyer identity is hidden.') }}</span>
                              </td>
                              <td colspan="2">
                                  @include('frontend.customer.partials.btn-confirm-sold-modern', [
                                    'action' => route('customer.tickets.complete', $ticket->id),
                                    'confirm' => __('Confirm payment received and mark as sold?'),
                                  ])
                              </td>
                          </tr>
                      @endif

                      {{-- Ticket is paid: seller must transfer before transfer_deadline --}}
                      @if($ticket->status === 'paid' && in_array($ticket->transfer_status, ['pending', 'pending_transfer', null]))
                          <tr style="background-color: rgba(23,162,184,0.08);">
                              <td colspan="5" class="text-right">
                                  <i class="fas fa-arrow-up text-info mr-2"></i>
                                  <strong>{{ __('Action required: transfer the ticket to the buyer.') }}</strong>
                                  @if($ticket->transfer_deadline)
                                      <br><small class="text-danger">
                                          {{ __('Transfer deadline:') }}
                                          {{ \Carbon\Carbon::parse($ticket->transfer_deadline)->format('d M Y H:i') }}
                                          ({{ \Carbon\Carbon::parse($ticket->transfer_deadline)->diffForHumans() }})
                                      </small>
                                  @endif
                              </td>
                              <td colspan="2">
                                  <a href="{{ route('customer.tickets.transfer', $ticket->id) }}"
                                     class="btn btn-info btn-sm">
                                      <i class="fas fa-paper-plane mr-1"></i> {{ __('Transfer now') }}
                                  </a>
                              </td>
                          </tr>
                      @endif

                      {{-- Transferred, waiting for buyer confirmation / payment release --}}
                      @if($ticket->status === 'paid' && $ticket->transfer_status === 'transferred')
                          <tr style="background-color: rgba(40,167,69,0.08);">
                              <td colspan="5" class="text-right">
                                  <i class="fas fa-check text-success mr-2"></i>
                                  {{ __('Ticket transferred. Awaiting buyer confirmation and payment release.') }}
                                  @if($ticket->payment_release_due_at)
                                      <br><small class="text-muted">
                                          {{ __('Payment releases around:') }}
                                          {{ \Carbon\Carbon::parse($ticket->payment_release_due_at)->format('d M Y H:i') }}
                                      </small>
                                  @endif
                              </td>
                              <td colspan="2">
                                  <span class="badge badge-success">{{ __('Transferred') }}</span>
                              </td>
                          </tr>
                      @endif

                      {{-- Failed / missed deadline: reassigned away --}}
                      @if(in_array($ticket->transfer_status, ['missed_deadline', 'failed']))
                          <tr style="background-color: rgba(220,53,69,0.08);">
                              <td colspan="7" class="text-danger">
                                  <i class="fas fa-exclamation-triangle mr-2"></i>
                                  {{ __('Transfer deadline missed. Buyer has been reassigned to another seller.') }}
                                  {{ __('This will affect your confidence level.') }}
                              </td>
                          </tr>
                      @endif

                    @empty
                      <tr>
                        <td colspan="7" class="text-center">No tickets found.</td>
                      </tr>
                    @endforelse
                  </tbody>
                </table>
              </div>

              @foreach($tickets as $ticket)
                @php
                  $historyStatuses = ['sold', 'paid', 'transferred', 'reserved', 'cancelled'];
                  $historyRows = collect();
                  if ($ticket->children) {
                      foreach ($ticket->children as $h) {
                          if (in_array(strtolower((string) $h->status), $historyStatuses)) {
                              $historyRows->push($h);
                          }
                      }
                  }
                  if (!empty($ticket->buyer_customer_id) && in_array(strtolower((string) $ticket->status), $historyStatuses)) {
                      $historyRows->push($ticket);
                  }
                  $historyRows = $historyRows->sortByDesc(function ($row) {
                      return optional($row->created_at)->timestamp ?? 0;
                  })->values();
                @endphp
                <div class="modal fade" id="buyingHistoryModal{{ $ticket->id }}" tabindex="-1" role="dialog"
                  aria-labelledby="buyingHistoryModalLabel{{ $ticket->id }}" aria-hidden="true">
                  <div class="modal-dialog modal-lg" role="document">
                    <div class="modal-content">
                      <div class="modal-header">
                        <h5 class="modal-title" id="buyingHistoryModalLabel{{ $ticket->id }}">
                          Buying history - {{ optional($ticket->eventContent)->title ?? 'Ticket' }}
                        </h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                          <span aria-hidden="true">&times;</span>
                        </button>
                      </div>
                      <div class="modal-body">
                        @if($historyRows->count() > 0)
                          <div class="table-responsive">
                            <table class="table table-striped table-sm">
                              <thead>
                                <tr>
                                  <th>Booking ID</th>
                                  <th>{{ __('Participant') }}</th>
                                  <th>Qty</th>
                                  <th>Status</th>
                                  <th>Time</th>
                                </tr>
                              </thead>
                              <tbody>
                                @foreach($historyRows as $h)
                                  @php
                                    $hStatus = strtolower((string) $h->status);
                                  @endphp
                                  <tr>
                                    <td>{{ $h->booking_id ?: '-' }}</td>
                                    <td><span class="text-muted">{{ __('Anonymous') }}</span></td>
                                    <td>{{ (int) $h->quantity }}</td>
                                    <td>
                                      @if($hStatus === 'sold')
                                        <span class="badge badge-success">Sold</span>
                                      @elseif($hStatus === 'transferred')
                                        <span class="badge badge-success">Transferred</span>
                                      @elseif($hStatus === 'paid')
                                        <span class="badge badge-info">Paid</span>
                                      @elseif($hStatus === 'reserved')
                                        <span class="badge badge-warning">On hold</span>
                                      @elseif($hStatus === 'cancelled')
                                        <span class="badge badge-danger">Cancelled</span>
                                      @else
                                        <span class="badge badge-secondary">{{ ucfirst($h->status) }}</span>
                                      @endif
                                    </td>
                                    <td>{{ optional($h->created_at)->format('d M Y h:i A') }}</td>
                                  </tr>
                                @endforeach
                              </tbody>
                            </table>
                          </div>
                        @else
                          <p class="mb-0 text-muted">No buying history found for this ticket yet.</p>
                        @endif
                      </div>
                    </div>
                  </div>
                </div>
              @endforeach
              {{ $tickets->links() }}
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
@endsection

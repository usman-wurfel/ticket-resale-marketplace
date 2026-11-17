@extends('frontend.layout')

@section('pageHeading')
  {{ __('Notifications') }}
@endsection

@section('hero-section')
  <section class="page-banner overlay pt-120 pb-125 rpt-90 rpb-95 lazy"
    data-bg="{{ asset('assets/admin/img/' . $basicInfo->breadcrumb) }}">
    <div class="container">
      <div class="banner-inner">
        <h2 class="page-title">{{ __('Notifications') }}</h2>
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
            <div class="title d-flex justify-content-between align-items-center">
              <h4>{{ __('Notifications') }}</h4>
              <form action="{{ route('customer.notifications.mark_all_read') }}" method="POST" class="m-0">
                @csrf
                <button type="submit" class="btn btn-sm btn-outline-secondary">
                  {{ __('Mark all as read') }}
                </button>
              </form>
            </div>
            <div class="main-info">
              @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
              @endif

              <div class="list-group">
                @forelse ($notifications as $n)
                  <div class="list-group-item {{ $n->is_read ? '' : 'border-primary bg-light' }}">
                    <div class="d-flex justify-content-between align-items-start">
                      <div>
                        <strong>{{ $n->title }}</strong>
                        @if (!$n->is_read)
                          <span class="badge badge-primary ml-1">{{ __('new') }}</span>
                        @endif
                        <small class="text-muted d-block">{{ $n->type }} · {{ $n->created_at->diffForHumans() }}</small>
                        @if (!empty($n->body))
                          <p class="mb-0 mt-1">{{ $n->body }}</p>
                        @endif

                        @if ($n->type === \App\Models\ResaleNotification::TYPE_REASSIGNMENT_OFFERED && !empty($n->resale_ticket_id) && !$n->is_read)
                          <div class="mt-2 d-flex flex-wrap" style="gap:6px;">
                            <form action="{{ route('customer.tickets.reassign', $n->resale_ticket_id) }}"
                                  method="POST" class="m-0"
                                  onsubmit="return confirm('{{ __('Accept the equivalent ticket and reserve it now?') }}');">
                              @csrf
                              <button type="submit" class="btn btn-success btn-sm">
                                <i class="fas fa-check mr-1"></i> {{ __('Accept reassignment') }}
                              </button>
                            </form>
                            <form action="{{ route('customer.tickets.reassign.decline', $n->resale_ticket_id) }}"
                                  method="POST" class="m-0">
                              @csrf
                              <button type="submit" class="btn btn-outline-secondary btn-sm">
                                <i class="fas fa-times mr-1"></i> {{ __('Decline') }}
                              </button>
                            </form>
                          </div>
                        @endif
                      </div>
                      <div class="text-right">
                        @if (!empty($n->resale_ticket_id) && in_array($n->type, [
                            \App\Models\ResaleNotification::TYPE_REASSIGNMENT_OFFERED,
                            \App\Models\ResaleNotification::TYPE_REASSIGNMENT_UNAVAILABLE,
                        ]))
                          {{-- Reassignment notifications reference a row the buyer no longer owns; no "Open" link. --}}
                        @elseif (!empty($n->resale_ticket_id))
                          <a href="{{ route('customer.tickets.bought.resale', $n->resale_ticket_id) }}"
                             class="btn btn-sm btn-link">
                            {{ __('Open') }}
                          </a>
                        @elseif (!empty($n->support_ticket_id))
                          <a href="{{ route('customer.support_ticket.message', $n->support_ticket_id) }}"
                             class="btn btn-sm btn-link">
                            {{ __('Open') }}
                          </a>
                        @endif
                      </div>
                    </div>
                  </div>
                @empty
                  <p class="text-muted">{{ __('No notifications yet.') }}</p>
                @endforelse
              </div>
              <div class="mt-3">
                {{ $notifications->links() }}
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
@endsection

<div class="col-lg-3">
  <div class="user-sidebar">
    <ul class="links">
      <li><a href="{{ route('customer.dashboard') }}"
          class="@if (request()->routeIs('customer.dashboard')) active @endif">{{ __('Dashboard') }}</a></li>
      <li><a href="{{ route('customer.tickets.bought') }}"
          class="@if (request()->routeIs('customer.tickets.bought') || request()->routeIs('customer.booking.my_booking') || request()->routeIs('customer.booking_details')) active @endif">
          Bought tickets</a></li>
      <li><a href="{{ route('customer.tickets.sold') }}"
          class="@if (request()->routeIs('customer.tickets.sold')) active @endif">
          Sold tickets</a></li>
      @php
        $pendingTransfers = 0;
        if (Auth::guard('customer')->check()) {
            try {
                $pendingTransfers = \App\Models\Event\ResaleTicket::where('seller_customer_id', Auth::guard('customer')->id())
                    ->where('status', \App\Models\Event\ResaleTicket::STATUS_PAID)
                    ->where('transfer_status', \App\Models\Event\ResaleTicket::TRANSFER_PENDING)
                    ->count();
            } catch (\Throwable $e) { $pendingTransfers = 0; }
        }
      @endphp
      <li><a href="{{ route('customer.tickets.transfers') }}"
          class="@if (request()->routeIs('customer.tickets.transfers')) active @endif">
          {{ __('My Transfers') }}
          @if ($pendingTransfers > 0)
            <span class="badge badge-warning ml-1">{{ $pendingTransfers }}</span>
          @endif
        </a></li>
      {{-- 
      <li><a href="{{ route('customer.my_orders') }}"
          class="
            @if (request()->routeIs('customer.my_orders')) active
            @elseif (request()->routeIs('customer.order_details')) active @endif
            ">
          {{ __('Product Orders') }} </a></li>
      --}}

      <li><a href="{{ route('customer.wishlist') }}"
          class="@if (request()->routeIs('customer.wishlist')) active @endif">{{ __('Wishlist') }}</a></li>

      <li><a href="{{ route('customer.support_tickert') }}"
          class="@if (request()->routeIs('customer.support_tickert')) active
            @elseif(request()->routeIs('customer.support_tickert.create')) active
            @elseif(request()->routeIs('customer.support_ticket.message')) active @endif">
          Incident tickets</a></li>
      <li><a href="{{ route('customer.wallet') }}"
          class="@if (request()->routeIs('customer.wallet')) active
            @elseif(request()->routeIs('customer.wallet.withdraw')) active @endif">
          {{ __('Wallet & Withdrawals') }}</a></li>
      @php
        $unreadNotif = 0;
        if (Auth::guard('customer')->check()) {
            try {
                $unreadNotif = \App\Models\ResaleNotification::where('customer_id', Auth::guard('customer')->id())
                    ->where('is_read', false)
                    ->count();
            } catch (\Throwable $e) { $unreadNotif = 0; }
        }
      @endphp
      <li><a href="{{ route('customer.notifications') }}"
          class="@if (request()->routeIs('customer.notifications')) active @endif">
          {{ __('Notifications') }}
          @if ($unreadNotif > 0)
            <span class="badge badge-danger ml-1">{{ $unreadNotif }}</span>
          @endif
        </a></li>
      <li><a href="{{ route('customer.edit.profile') }}"
          class="@if (request()->routeIs('customer.edit.profile')) active @endif">{{ __('Edit Profile') }} </a></li>
      <li><a href="{{ route('customer.change.password') }}"
          class="@if (request()->routeIs('customer.change.password')) active @endif">{{ __('Change Password') }}</a></li>

      <li><a href="{{ route('customer.logout') }}" class="">{{ __('Logout') }} </a></li>
    </ul>
  </div>
</div>

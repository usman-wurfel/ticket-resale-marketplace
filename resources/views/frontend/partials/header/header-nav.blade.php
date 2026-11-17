<header class="main-header">

  <!--Header-Upper-->
  <div class="header-upper py-25">
    <div class="container clearfix">

      <div class="header-inner">
        <div class="logo-outer">
          <div class="logo"><a href="{{ route('index') }}"><img style="width: 175px; height: auto;"
                src="{{ asset('assets/admin/img/' . $websiteInfo->logo) }}" alt="Logo"></a></div>
        </div>

        <div class="nav-outer clearfix ml-lg-auto">
          <!-- Main Menu -->
          <nav class="main-menu navbar-expand-xl">
            <div class="navbar-header">
              <div class="logo-mobile"><a href="{{ route('index') }}"><img style="width: 175px; height: auto;"
                    src="{{ asset('assets/admin/img/' . $websiteInfo->logo) }}" alt="Logo"></a></div>
              <!-- Toggle Button -->
              <button type="button" class="navbar-toggle" data-toggle="collapse" data-target=".navbar-collapse"
                aria-controls="main-menu">
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
              </button>
            </div>

            <div class="navbar-collapse collapse clearfix" id="main-menu">
              <ul class="navigation clearfix">
                <li><a href="{{ route('index') }}">{{ __('Home') }}</a></li>
                <li><a href="{{ route('events') }}">{{ __('Tickets') }}</a></li>
                <li><a
                    href="{{ Auth::guard('customer')->check() ? route('customer.tickets.sold') : route('customer.login') }}">Sell
                    tickets</a></li>
                <li><a href="{{ route('blogs') }}">{{ __('Blog') }}</a></li>
                <li><a href="{{ route('contact') }}">{{ __('Contact') }}</a></li>
              </ul>

              <div class="menu-right">
                @if (!Auth::guard('customer')->check())
                  <div class="d-flex align-items-center">
                    <a href="{{ route('customer.login') }}" class="menu-btn menu-btn-outline mr-1">{{ __('Login') }}</a>
                    <a href="{{ route('customer.signup') }}" class="menu-btn menu-btn-outline">{{ __('Register') }}</a>
                  </div>
                @else
                  <div class="dropdown">
                    <button type="button" class="menu-btn dropdown-toggle mr-1"
                      data-toggle="dropdown">{{ Auth::guard('customer')->user()->username }}</button>
                    <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                      <a class="dropdown-item" href="{{ route('customer.dashboard') }}">{{ __('Dashboard') }}</a>
                      <a class="dropdown-item" href="{{ route('customer.logout') }}">{{ __('Logout') }}</a>
                    </div>
                  </div>
                @endif
                {{-- Organizer auth removed: buyer and seller use same customer account --}}
              </div>
            </div>
          </nav>
          <!-- Main Menu End-->
        </div>
      </div>
    </div>
  </div>
  <!--End Header Upper-->
</header>

<section class="page-banner overlay pt-120 pb-125 rpt-90 rpb-95 lazy"
  data-bg="{{ asset('assets/admin/img/' . $basicInfo->breadcrumb) }}">
  <div class="container">
    <div class="banner-inner">
      <h2 class="page-title">{{ __('Reservation Success') }}</h2>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="{{ route('index') }}">{{ __('Home') }}</a></li>
          <li class="breadcrumb-item active">{{ __('Reservation Success') }}</li>
        </ol>
      </nav>
    </div>
  </div>
</section>

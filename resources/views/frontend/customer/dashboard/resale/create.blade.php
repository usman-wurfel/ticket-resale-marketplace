@extends('frontend.layout')

@section('pageHeading')
  {{ isset($ticket) ? 'Edit ticket listing' : 'Sell ticket' }}
@endsection

@section('hero-section')
  <section class="page-banner overlay pt-120 pb-125 rpt-90 rpb-95 lazy"
    data-bg="{{ asset('assets/admin/img/' . $basicInfo->breadcrumb) }}">
    <div class="container">
      <div class="banner-inner">
        <h2 class="page-title">{{ isset($ticket) ? 'Edit ticket listing' : 'Sell ticket' }}</h2>
      </div>
    </div>
  </section>
@endsection

@section('content')
  <style>
    .modern-upload-box {
      border: 1px dashed #c9d3e1;
      border-radius: 12px;
      padding: 14px;
      background: #f8fbff;
    }

    .modern-pdf-input {
      width: 100%;
      border: 1px solid #dce5f0;
      border-radius: 10px;
      padding: 10px;
      background: #fff;
    }

    .modern-pdf-input::file-selector-button {
      border: 0;
      border-radius: 8px;
      background: #2b6cb0;
      color: #fff;
      padding: 8px 14px;
      margin-right: 10px;
      cursor: pointer;
      font-weight: 600;
    }
  </style>
  <section class="user-dashbord">
    <div class="container">
      <div class="row">
        @includeIf('frontend.customer.partials.sidebar')
        <div class="col-lg-9">
          <div class="account-info">
            <div class="title">
              <h4>{{ isset($ticket) ? 'Edit listing for' : 'Sell ticket for' }}: {{ $event->title }}</h4>
            </div>
            <div class="main-info">
              @if (session('info'))
                <div class="alert alert-info">{{ session('info') }}</div>
              @endif
              @if (session('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
              @endif
              @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
              @endif

              @if (isset($ticket))
                <form action="{{ route('customer.tickets.sell.update', $ticket->id) }}" method="POST" enctype="multipart/form-data">
                  @csrf
                  @method('PUT')
              @else
                <form action="{{ route('customer.tickets.sell.store') }}" method="POST" enctype="multipart/form-data">
                  @csrf
                  <input type="hidden" name="event_id" value="{{ $event->event_id }}">
              @endif

                @php
                  $zoneOptions = $zoneOptions ?? [];
                  $allowCustomZone = $allowCustomZone ?? false;
                  $entranceDates = $entranceDates ?? [];
                  $isMultiDay = $isMultiDay ?? (count($entranceDates) > 1);
                  $currentZone = old('zone_select', old('zone', isset($ticket) ? $ticket->zone : ''));
                  $isCustomSelected = old('zone_select') === '__custom__'
                    || ($currentZone && !empty($zoneOptions) && !in_array($currentZone, $zoneOptions, true) && $allowCustomZone);
                  $currentEntrance = old(
                    'entrance_date',
                    isset($ticket) && $ticket->entrance_date
                      ? \Carbon\Carbon::parse($ticket->entrance_date)->format('Y-m-d')
                      : (count($entranceDates) === 1 ? $entranceDates[0] : '')
                  );
                @endphp

              @if (!empty($externalPlatformName))
                <div class="alert alert-info py-2">
                  <i class="fas fa-exchange-alt mr-1"></i>
                  <strong>{{ __('External transfer platform') }}:</strong> {{ $externalPlatformName }}
                  <div class="small mb-0">{{ __('After sale, you will transfer the ticket on this platform to the buyer.') }}</div>
                </div>
              @endif

                @if ($isMultiDay)
                  <div class="form-group">
                    <label>{{ __('Entrance day') }} <span class="text-danger">*</span></label>
                    <select name="entrance_date" class="form-control" required>
                      <option value="">{{ __('Select the day this ticket is valid for') }}</option>
                      @foreach ($entranceDates as $day)
                        <option value="{{ $day }}" {{ (string) $currentEntrance === (string) $day ? 'selected' : '' }}>
                          {{ \Carbon\Carbon::parse($day)->translatedFormat('D, d M Y') }}
                        </option>
                      @endforeach
                    </select>
                    <small class="text-muted">{{ __('This event lasts several days. Pick the day your ticket allows entrance.') }}</small>
                    @error('entrance_date')
                      <small class="text-danger d-block">{{ $message }}</small>
                    @enderror
                  </div>
                @elseif (count($entranceDates) === 1)
                  <input type="hidden" name="entrance_date" value="{{ $entranceDates[0] }}">
                @endif

                <div class="form-group">
                  <label>{{ __('Zone / Category') }}
                    <small class="text-muted">({{ __('required for FIFO matching') }})</small>
                  </label>
                  @if (!empty($zoneOptions))
                    <select name="zone_select" id="zone_select" class="form-control" required>
                      <option value="">{{ __('Select a zone') }}</option>
                      @foreach ($zoneOptions as $z)
                        <option value="{{ $z }}" {{ !$isCustomSelected && (string) $currentZone === (string) $z ? 'selected' : '' }}>
                          {{ $z }}
                        </option>
                      @endforeach
                      @if ($allowCustomZone)
                        <option value="__custom__" {{ $isCustomSelected ? 'selected' : '' }}>{{ __('Other (custom zone)') }}</option>
                      @endif
                    </select>
                    <div id="custom_zone_wrap" class="mt-2 {{ $isCustomSelected ? '' : 'd-none' }}">
                      <input type="text" class="form-control" name="custom_zone" id="custom_zone"
                        value="{{ old('custom_zone', $isCustomSelected ? $currentZone : '') }}"
                        placeholder="{{ __('Type your zone') }}" maxlength="100">
                    </div>
                    <small class="text-muted">
                      {{ __('Choose a zone of this event location.') }}
                      @if ($allowCustomZone)
                        {{ __('You may also enter a custom zone.') }}
                      @endif
                    </small>
                  @elseif ($allowCustomZone)
                    <input type="hidden" name="zone_select" value="__custom__">
                    <input type="text" class="form-control" name="custom_zone"
                      value="{{ old('custom_zone', $currentZone) }}"
                      placeholder="{{ __('e.g. VIP, Stand A…') }}" maxlength="100" required>
                    <small class="text-muted">{{ __('Type the zone/category exactly as printed on your ticket.') }}</small>
                  @else
                    <input type="text" class="form-control" name="zone"
                      value="{{ $currentZone }}"
                      placeholder="{{ __('e.g. VIP, Stand A, Seated category 1…') }}"
                      maxlength="100" required>
                    <small class="text-muted">{{ __('No location zones are configured for this event yet. Type the zone as printed on your ticket.') }}</small>
                  @endif
                  @error('zone_select')
                    <small class="text-danger d-block">{{ $message }}</small>
                  @enderror
                  @error('zone')
                    <small class="text-danger d-block">{{ $message }}</small>
                  @enderror
                  @error('custom_zone')
                    <small class="text-danger d-block">{{ $message }}</small>
                  @enderror
                </div>
                <div class="form-group">
                  <label>Price</label>
                  <input type="number" step="0.01" min="0" class="form-control" name="price" id="resale_price"
                    value="{{ isset($ticket) ? $ticket->price : old('price') }}" required>
                  @error('price')
                    <small class="text-danger">{{ $message }}</small>
                  @enderror
                  @php
                    $commissionPercent = (float) ($commissionPercent ?? 0);
                    if ($commissionPercent < 0) { $commissionPercent = 0; }
                    if ($commissionPercent > 100) { $commissionPercent = 100; }
                    $sym = $basicInfo->base_currency_symbol ?? '';
                    $symPos = $basicInfo->base_currency_symbol_position ?? 'left';
                  @endphp
                  <div id="seller_payout_box" class="mt-2 p-2 border rounded" style="background:#f7fafc;">
                    <small class="text-muted d-block mb-1">
                      {{ __('Platform commission') }}: <strong id="commission_rate_label">{{ number_format($commissionPercent, 2) }}%</strong>
                      <span class="text-muted">({{ __('taxes & costs included') }})</span>
                    </small>
                    <div>
                      <strong>{{ __('You will receive') }}:</strong>
                      <span id="seller_net_amount" class="text-success font-weight-bold">—</span>
                    </div>
                    <small class="text-muted d-block mt-1">
                      {{ __('Final amount') }} = {{ __('Price') }} × (1 − {{ __('Commission') }})
                    </small>
                  </div>
                </div>
                <div class="form-group">
                  <label>Notes (optional)</label>
                  <textarea class="form-control" name="notes" rows="4">{{ isset($ticket) ? $ticket->notes : old('notes') }}</textarea>
                </div>
                @php $mustUploadPdf = $mustUploadPdf ?? (($eventTransferType ?? 'external') === 'internal'); @endphp
                @if ($mustUploadPdf)
                  <div class="form-group">
                    <label>Ticket PDF *</label>
                    <div class="modern-upload-box">
                      <input type="file" class="modern-pdf-input" name="ticket_pdfs[]" accept="application/pdf" {{ isset($ticket) && !empty($ticket->ticket_pdfs) ? '' : 'required' }}>
                      <small class="text-muted d-block mt-2">The tickets for this event are already available, so you must upload your ticket PDF now.</small>
                      <small class="text-muted d-block">Upload exactly 1 PDF (one ticket per listing).</small>
                      @if (isset($ticket) && !empty($ticket->ticket_pdfs))
                        @php $existingPdfCount = count(json_decode($ticket->ticket_pdfs, true) ?? []); @endphp
                        <small class="text-muted d-block">Current uploaded PDFs: {{ $existingPdfCount }}. Uploading a new file will replace it.</small>
                      @endif
                      @error('ticket_pdfs')
                        <small class="text-danger d-block mt-1">{{ $message }}</small>
                      @enderror
                      @error('ticket_pdfs.*')
                        <small class="text-danger d-block mt-1">{{ $message }}</small>
                      @enderror
                    </div>
                  </div>
                @elseif (($eventTransferType ?? 'external') === 'internal')
                  <div class="form-group">
                    <div class="alert alert-info mb-0">
                      {{ __('The tickets for this event are not yet available to you, so no PDF upload is required at listing. Once a buyer purchases, you will be asked to transfer the ticket within the applicable deadline.') }}
                    </div>
                  </div>
                @endif
                <input type="hidden" name="quantity" value="1">
                <p class="small text-muted mb-3">
                  {{ __('Each listing is for 1 ticket only. To sell several tickets, publish them one by one (you may have multiple listings active at the same time, subject to your confidence level).') }}
                </p>
                <button type="submit" class="theme-btn">{{ isset($ticket) ? 'Update listing' : 'List ticket' }}</button>
              </form>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
@endsection

@section('script')
<script>
  (function () {
    var select = document.getElementById('zone_select');
    var wrap = document.getElementById('custom_zone_wrap');
    if (select && wrap) {
      function sync() {
        if (select.value === '__custom__') {
          wrap.classList.remove('d-none');
        } else {
          wrap.classList.add('d-none');
        }
      }
      select.addEventListener('change', sync);
      sync();
    }

    var priceInput = document.getElementById('resale_price');
    var netEl = document.getElementById('seller_net_amount');
    var commissionPercent = {{ (float) ($commissionPercent ?? 0) }};
    if (commissionPercent < 0) commissionPercent = 0;
    if (commissionPercent > 100) commissionPercent = 100;
    var sym = @json($basicInfo->base_currency_symbol ?? '');
    var symPos = @json($basicInfo->base_currency_symbol_position ?? 'left');

    function formatMoney(n) {
      var v = (Math.round(n * 100) / 100).toFixed(2);
      return symPos === 'left' ? (sym + v) : (v + sym);
    }

    function updatePayout() {
      if (!priceInput || !netEl) return;
      var price = parseFloat(priceInput.value);
      if (isNaN(price) || price < 0) {
        netEl.textContent = '—';
        return;
      }
      var net = price * (1 - (commissionPercent / 100));
      netEl.textContent = formatMoney(net);
    }

    if (priceInput) {
      priceInput.addEventListener('input', updatePayout);
      priceInput.addEventListener('change', updatePayout);
      updatePayout();
    }
  })();
</script>
@endsection

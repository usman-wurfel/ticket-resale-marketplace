@extends('frontend.layout')

@section('pageHeading')
  {{ __('Wallet') }}
@endsection

@section('hero-section')
  <section class="page-banner overlay pt-120 pb-125 rpt-90 rpb-95 lazy"
    data-bg="{{ asset('assets/admin/img/' . $basicInfo->breadcrumb) }}">
    <div class="container">
      <div class="banner-inner">
        <h2 class="page-title">{{ __('Wallet & Withdrawals') }}</h2>
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
              <h4>{{ __('Wallet') }}</h4>
            </div>
            <div class="main-info">
              @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
              @endif
              @if (session('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
              @endif

              <div class="row mb-4">
                <div class="col-md-4">
                  <div class="card p-3 border-primary" style="background: #f0f6ff;">
                    <small class="text-muted">{{ __('Platform balance') }}</small>
                    <h3 class="mb-0">
                      {{ $basicInfo->base_currency_symbol_position == 'left' ? $basicInfo->base_currency_symbol : '' }}
                      {{ number_format((float) $customer->platform_balance, 2) }}
                      {{ $basicInfo->base_currency_symbol_position == 'right' ? $basicInfo->base_currency_symbol : '' }}
                    </h3>
                    <small class="text-muted d-block mt-2">
                      {{ __('Spendable wallet: resale discounts, refunds from the platform. You may withdraw this balance. Not used for purchase escrow.') }}
                    </small>
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="card p-3 border-secondary" style="background: #f8f9fa;">
                    <small class="text-muted">{{ __('Escrow balance') }}</small>
                    <h3 class="mb-0">
                      {{ $basicInfo->base_currency_symbol_position == 'left' ? $basicInfo->base_currency_symbol : '' }}
                      {{ number_format((float) ($customer->escrow_balance ?? 0), 2) }}
                      {{ $basicInfo->base_currency_symbol_position == 'right' ? $basicInfo->base_currency_symbol : '' }}
                    </h3>
                    <small class="text-muted d-block mt-2">
                      {{ __('Funds held for your active resale purchases until the sale completes or is refunded. Cannot be used as a discount and is not withdrawable.') }}
                    </small>
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="card p-3 border-success" style="background: #f1fff4;">
                    <small class="text-muted">{{ __('Sales proceeds (withdrawable)') }}</small>
                    <h3 class="mb-0">
                      {{ $basicInfo->base_currency_symbol_position == 'left' ? $basicInfo->base_currency_symbol : '' }}
                      {{ number_format((float) $customer->withdrawable_balance, 2) }}
                      {{ $basicInfo->base_currency_symbol_position == 'right' ? $basicInfo->base_currency_symbol : '' }}
                    </h3>
                    <small class="text-muted d-block mt-2">
                      {{ __('Released to you after T+48h when you sold tickets, if there is no pending litigation.') }}
                    </small>
                  </div>
                </div>
              </div>

              <div class="mb-4">
                <h5>{{ __('Resale risk level') }}</h5>
                @php
                  $lvl = $customer->resaleRiskLabelForDisplay();
                  $badge = [
                      'LOW RISK' => 'badge-success',
                      'MEDIUM RISK' => 'badge-primary',
                      'HIGH RISK' => 'badge-warning',
                      'BANNED' => 'badge-danger',
                  ][$lvl] ?? 'badge-secondary';
                @endphp
                <span class="badge {{ $badge }}" style="font-size:14px; padding:6px 12px;">
                  {{ $lvl }}
                </span>
                <small class="text-muted ml-2">
                  {{ __('Score') }}:
                  {{ number_format((float) ($customer->confidence_score ?? 0), 2) }}
                </small>
                @if ($customer->isBanned())
                  <div class="alert alert-danger mt-2">
                    {{ __('Your account is banned from selling and buying on the resale marketplace.') }}
                  </div>
                @endif
              </div>

              <div class="mb-4">
                <h5>{{ __('Request a withdrawal') }}</h5>
                @php
                  $pb = (float) $customer->platform_balance;
                  $wb = (float) $customer->withdrawable_balance;
                @endphp
                @if ($pb <= 0 && $wb <= 0)
                  <div class="alert alert-secondary">
                    {{ __('You have no platform balance or sales proceeds available to withdraw at the moment.') }}
                  </div>
                @elseif ($methods->count() === 0)
                  <div class="alert alert-warning">
                    {{ __('No withdrawal methods are configured. Please contact support.') }}
                  </div>
                @else
                  <form action="{{ route('customer.wallet.withdraw') }}" method="POST">
                    @csrf
                    <div class="form-group">
                      <label>{{ __('Withdraw from') }}</label>
                      <select name="balance_source" id="balance_source" class="form-control" required
                              data-platform-max="{{ $pb }}"
                              data-withdraw-max="{{ $wb }}">
                        @if ($pb > 0)
                          <option value="platform">{{ __('Platform balance') }} ({{ number_format($pb, 2) }})</option>
                        @endif
                        @if ($wb > 0)
                          <option value="withdrawable">{{ __('Sales proceeds') }} ({{ number_format($wb, 2) }})</option>
                        @endif
                      </select>
                    </div>
                    <div class="row">
                      <div class="col-md-6">
                        <div class="form-group">
                          <label>{{ __('Method') }}</label>
                          <select name="withdraw_method" id="withdraw_method" class="form-control" required>
                            <option value="">{{ __('-- Select method --') }}</option>
                            @foreach ($methods as $m)
                              <option value="{{ $m->id }}"
                                      data-min="{{ $m->minimum_amount ?? 0 }}"
                                      data-max="{{ $m->maximum_amount ?? 0 }}"
                                      data-fixed="{{ $m->fixed_charge ?? 0 }}"
                                      data-percent="{{ $m->percentage_charge ?? 0 }}">
                                {{ $m->name }}
                              </option>
                            @endforeach
                          </select>
                        </div>
                      </div>
                      <div class="col-md-6">
                        <div class="form-group">
                          <label>{{ __('Amount') }}</label>
                          <input type="number" step="0.01" min="0.01" name="withdraw_amount" id="withdraw_amount"
                                 max="{{ max($pb, $wb) > 0 ? max($pb, $wb) : 1 }}"
                                 class="form-control" required>
                          <small class="text-muted" id="method_hint"></small>
                        </div>
                      </div>
                    </div>
                    <div id="dynamic_fields"></div>
                    <div class="form-group">
                      <label>{{ __('Additional reference (optional)') }}</label>
                      <input type="text" name="additional_reference" class="form-control">
                    </div>
                    <button type="submit" class="btn btn-primary">
                      {{ __('Submit withdrawal request') }}
                    </button>
                    <small class="text-muted d-block mt-2">
                      {{ __('Requests are executed once a day by the platform.') }}
                    </small>
                  </form>
                @endif
              </div>

              <div class="mb-4">
                <h5>{{ __('Withdrawal history') }}</h5>
                <div class="table-responsive">
                  <table class="table table-sm table-striped">
                    <thead>
                      <tr>
                        <th>{{ __('Ref') }}</th>
                        <th>{{ __('Amount') }}</th>
                        <th>{{ __('Fee') }}</th>
                        <th>{{ __('Payable') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Scheduled') }}</th>
                      </tr>
                    </thead>
                    <tbody>
                      @forelse ($withdraws as $w)
                        <tr>
                          <td>{{ $w->withdraw_id }}</td>
                          <td>{{ number_format((float) $w->amount, 2) }}</td>
                          <td>{{ number_format((float) $w->total_charge, 2) }}</td>
                          <td>{{ number_format((float) $w->payable_amount, 2) }}</td>
                          <td>
                            @if ((int) $w->status === 0)
                              <span class="badge badge-warning">{{ __('Pending') }}</span>
                            @elseif((int) $w->status === 1)
                              <span class="badge badge-success">{{ __('Paid') }}</span>
                            @else
                              <span class="badge badge-danger">{{ __('Failed') }}</span>
                            @endif
                          </td>
                          <td>
                            {{ $w->scheduled_execution_at ? \Carbon\Carbon::parse($w->scheduled_execution_at)->format('d M Y H:i') : '-' }}
                          </td>
                        </tr>
                      @empty
                        <tr><td colspan="6" class="text-center text-muted">{{ __('No withdrawals yet.') }}</td></tr>
                      @endforelse
                    </tbody>
                  </table>
                </div>
              </div>

              <div>
                <h5>{{ __('Recent transactions') }}</h5>
                <div class="table-responsive">
                  <table class="table table-sm table-striped">
                    <thead>
                      <tr>
                        <th>{{ __('Date') }}</th>
                        <th>{{ __('Type') }}</th>
                        <th>{{ __('Amount') }}</th>
                        <th>{{ __('Status') }}</th>
                      </tr>
                    </thead>
                    <tbody>
                      @forelse ($transactions as $t)
                        @php
                          $typeMap = [
                            10 => __('Resale payment (escrow hold)'),
                            11 => __('Release from sale'),
                            12 => __('Refund to platform balance'),
                            13 => __('Platform balance used as discount'),
                            14 => __('Withdrawal'),
                            15 => __('Escrow settled (sale completed)'),
                          ];
                        @endphp
                        <tr>
                          <td>{{ $t->created_at ? $t->created_at->format('d M Y H:i') : '-' }}</td>
                          <td>{{ $typeMap[(int) $t->transcation_type] ?? (string) $t->transcation_type }}</td>
                          <td>{{ number_format((float) $t->grand_total, 2) }}</td>
                          <td><span class="badge badge-light border">{{ $t->status ?? '-' }}</span></td>
                        </tr>
                      @empty
                        <tr><td colspan="4" class="text-center text-muted">{{ __('No transactions yet.') }}</td></tr>
                      @endforelse
                    </tbody>
                  </table>
                </div>
              </div>
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
      const methodSel = document.getElementById('withdraw_method');
      const dynFields = document.getElementById('dynamic_fields');
      const hint = document.getElementById('method_hint');
      const sourceSel = document.getElementById('balance_source');
      const amtInput = document.getElementById('withdraw_amount');
      if (!methodSel) return;

      function syncWithdrawMax() {
        if (!sourceSel || !amtInput) return;
        const p = parseFloat(sourceSel.getAttribute('data-platform-max') || '0');
        const w = parseFloat(sourceSel.getAttribute('data-withdraw-max') || '0');
        const v = sourceSel.value;
        const cap = v === 'platform' ? p : w;
        amtInput.setAttribute('max', cap > 0 ? String(cap) : '1');
      }
      if (sourceSel) {
        sourceSel.addEventListener('change', syncWithdrawMax);
        syncWithdrawMax();
      }

      methodSel.addEventListener('change', function () {
        const opt = this.options[this.selectedIndex];
        if (!opt || !opt.value) { dynFields.innerHTML = ''; if (hint) hint.innerText = ''; return; }
        const min = parseFloat(opt.getAttribute('data-min') || 0);
        const max = parseFloat(opt.getAttribute('data-max') || 0);
        const fixed = parseFloat(opt.getAttribute('data-fixed') || 0);
        const pct = parseFloat(opt.getAttribute('data-percent') || 0);
        if (hint) {
          hint.innerText =
            'Min: ' + (min || 0) +
            ' · Max: ' + (max || 0) +
            ' · Fee: ' + fixed + ' + ' + pct + '%';
        }

        fetch("{{ route('customer.wallet.method_fields') }}?method_id=" + opt.value, {
          headers: { 'Accept': 'application/json' }
        }).then(r => r.json()).then(inputs => {
          let html = '';
          (inputs || []).forEach(function (i) {
            const label = (i.label || i.name) + (i.required == 1 ? ' *' : '');
            if (i.type === 'textarea') {
              html += '<div class="form-group"><label>' + label + '</label>'
                   + '<textarea class="form-control" name="' + i.name + '" ' + (i.required == 1 ? 'required' : '') + '></textarea></div>';
            } else if (i.type === 'select' && i.options && i.options.length) {
              html += '<div class="form-group"><label>' + label + '</label>'
                   + '<select class="form-control" name="' + i.name + '" ' + (i.required == 1 ? 'required' : '') + '>';
              html += '<option value="">-- select --</option>';
              i.options.forEach(function (o) {
                html += '<option value="' + o.value + '">' + o.label + '</option>';
              });
              html += '</select></div>';
            } else {
              html += '<div class="form-group"><label>' + label + '</label>'
                   + '<input type="text" class="form-control" name="' + i.name + '" ' + (i.required == 1 ? 'required' : '') + '></div>';
            }
          });
          dynFields.innerHTML = html;
        }).catch(function () { dynFields.innerHTML = ''; });
      });
    })();
  </script>
@endsection

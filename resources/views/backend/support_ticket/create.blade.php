@extends('backend.layout')

@section('content')
  <div class="page-header">
    <h4 class="page-title">{{ __('Open incident') }}</h4>
    <ul class="breadcrumbs">
      <li class="nav-home">
        <a href="{{ route('admin.dashboard') }}">
          <i class="flaticon-home"></i>
        </a>
      </li>
      <li class="separator">
        <i class="flaticon-right-arrow"></i>
      </li>
      <li class="nav-item">
        <a href="{{ route('admin.support_tickets') }}">{{ __('Incident tickets') }}</a>
      </li>
      <li class="separator">
        <i class="flaticon-right-arrow"></i>
      </li>
      <li class="nav-item">
        <a href="#">{{ __('Open incident') }}</a>
      </li>
    </ul>
  </div>

  <div class="row">
    <div class="col-md-12">
      <div class="card">
        <div class="card-header">
          <div class="row">
            <div class="col-lg-6">
              <div class="card-title">{{ __('Open a new incident ticket') }}</div>
            </div>
            <div class="col-lg-6 text-lg-right">
              <a href="{{ route('admin.support_tickets') }}" class="btn btn-primary btn-sm">{{ __('Back to list') }}</a>
            </div>
          </div>
        </div>
        <div class="card-body">
          <form id="admin_create_incident_form" action="{{ route('admin.support_tickets.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="row">
              <div class="col-lg-6">
                <div class="form-group">
                  <label>{{ __('Subject') }} *</label>
                  <input type="text" name="subject" class="form-control" value="{{ old('subject') }}" required>
                  @error('subject')
                    <p class="text-danger mb-0">{{ $message }}</p>
                  @enderror
                </div>
              </div>
              <div class="col-lg-6">
                <div class="form-group">
                  <label>{{ __('Email (optional contact)') }}</label>
                  <input type="email" name="email" class="form-control" value="{{ old('email') }}"
                    placeholder="{{ __('Defaults to linked customer or admin email') }}">
                  @error('email')
                    <p class="text-danger mb-0">{{ $message }}</p>
                  @enderror
                </div>
              </div>
              <div class="col-lg-6">
                <div class="form-group">
                  <label>{{ __('Link to customer (optional)') }}</label>
                  <select name="customer_id" class="form-control">
                    <option value="">{{ __('-- No specific customer --') }}</option>
                    @foreach ($customers as $c)
                      <option value="{{ $c->id }}" {{ (string) old('customer_id') === (string) $c->id ? 'selected' : '' }}>
                        #{{ $c->id }}
                        {{ $c->username ?: trim(($c->fname ?? '') . ' ' . ($c->lname ?? '')) }}
                        — {{ $c->email }}
                      </option>
                    @endforeach
                  </select>
                  @error('customer_id')
                    <p class="text-danger mb-0">{{ $message }}</p>
                  @enderror
                </div>
              </div>
              <div class="col-lg-6">
                <div class="form-group">
                  <label>{{ __('Link to resale ticket (optional)') }}</label>
                  <select name="resale_ticket_id" class="form-control">
                    <option value="">{{ __('-- No specific resale ticket --') }}</option>
                    @foreach ($resaleTickets as $rt)
                      @php
                        $eventTitle = optional($rt->eventContent)->title ?? ('Event #' . $rt->event_id);
                      @endphp
                      <option value="{{ $rt->id }}" {{ (string) old('resale_ticket_id') === (string) $rt->id ? 'selected' : '' }}>
                        #{{ $rt->id }} — {{ $eventTitle }} — {{ $rt->status }}
                        @if (!empty($rt->booking_id))
                          ({{ $rt->booking_id }})
                        @endif
                      </option>
                    @endforeach
                  </select>
                  @error('resale_ticket_id')
                    <p class="text-danger mb-0">{{ $message }}</p>
                  @enderror
                </div>
              </div>
              <div class="col-lg-12">
                <div class="form-group">
                  <div class="custom-control custom-checkbox">
                    <input type="checkbox" class="custom-control-input" id="is_litigation" name="is_litigation"
                      value="1" {{ old('is_litigation') ? 'checked' : '' }}>
                    <label class="custom-control-label" for="is_litigation">
                      {{ __('Mark as litigation') }}
                    </label>
                  </div>
                </div>
              </div>
              <div class="col-lg-12">
                <div class="form-group">
                  <label>{{ __('Description') }} *</label>
                  {{-- no HTML required: TinyMCE hides the textarea and blocks submit silently --}}
                  <textarea id="incident_description" name="description" class="form-control summernote" data-height="200">{{ old('description') }}</textarea>
                  <p id="incident_description_error" class="text-danger mb-0 d-none">{{ __('Description is required.') }}</p>
                  @error('description')
                    <p class="text-danger mb-0">{{ $message }}</p>
                  @enderror
                </div>
              </div>
              <div class="col-lg-6">
                <div class="form-group">
                  <label>{{ __('Attachment (optional)') }}</label>
                  <input type="file" name="attachment" class="form-control"
                    accept=".zip,.rar,.pdf,.jpg,.jpeg,.png,.doc,.docx,.txt">
                  <small class="text-muted">{{ __('Allowed: zip, rar, pdf, jpg, jpeg, png, doc, docx, txt. Max 5 MB') }}</small>
                  @error('attachment')
                    <p class="text-danger mb-0">{{ $message }}</p>
                  @enderror
                </div>
              </div>
              <div class="col-lg-12">
                <button type="submit" class="btn btn-success" id="create_incident_btn">{{ __('Create incident') }}</button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
@endsection

@section('script')
<script>
  (function () {
    var form = document.getElementById('admin_create_incident_form');
    if (!form) return;

    form.addEventListener('submit', function (e) {
      if (typeof tinymce !== 'undefined') {
        tinymce.triggerSave();
      }

      var ta = document.getElementById('incident_description');
      var err = document.getElementById('incident_description_error');
      var raw = ta ? (ta.value || '') : '';
      var text = raw.replace(/<[^>]*>/g, ' ').replace(/&nbsp;/gi, ' ').trim();

      if (!text) {
        e.preventDefault();
        if (err) err.classList.remove('d-none');
        if (typeof tinymce !== 'undefined' && tinymce.get('incident_description')) {
          tinymce.get('incident_description').focus();
        } else if (ta) {
          ta.focus();
        }
        return false;
      }

      if (err) err.classList.add('d-none');
      var btn = document.getElementById('create_incident_btn');
      if (btn) {
        btn.disabled = true;
        btn.textContent = '{{ __("Creating…") }}';
      }
    });
  })();
</script>
@endsection

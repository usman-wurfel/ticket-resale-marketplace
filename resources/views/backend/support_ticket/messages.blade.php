@extends('backend.layout')
@section('content')
  <div class="page-header">
    <h4 class="page-title">{{ __('Conversations') }}</h4>
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
        <a href="#">Incident tickets</a>
      </li>
      <li class="separator">
        <i class="flaticon-right-arrow"></i>
      </li>
      <li class="nav-item">
        <a href="{{ route('admin.support_tickets') }}">All incidents</a>
      </li>
      <li class="separator">
        <i class="flaticon-right-arrow"></i>
      </li>
      <li class="nav-item">
        <a href="#">{{ __('Conversations') }}</a>
      </li>
    </ul>
  </div>
  <div class="row">
    <div class="col-md-12">
      <div class="card">
        <div class="card-header">
          <div class="row">
            <div class="col-lg-4">
              <div class="card-title d-inline-block">Incident details - #{{ $ticket->id }}</div>
            </div>
            <div class="col-lg-3 offset-lg-5 mt-2 mt-lg-0 text-left text-lg-right">
              <a href="{{ route('admin.support_tickets') }}" class="btn btn-primary btn-md">Back to list</a>
            </div>
          </div>
        </div>
        <div class="card-body">
          <div class="row text-center">
            <div class="col-lg-12">
              <div class="row">
                <div class="col-lg-12">
                  <h3 class="text-white">{{ $ticket->subject }}</h3>

                  @if ($ticket->status != 3)
                    <form class="closeForm d-block" action="{{ route('admin.support_ticket.close', $ticket->id) }}"
                      method="post">

                      @csrf
                      <button class="close-ticket btn btn-success btn-md TicketCloseBtn" data-href=""><i
                          class="fas fa-check mr-1"></i> Close incident</button>
                    </form>
                    @if (!empty($ticket->is_litigation) && $ticket->is_litigation && ($ticket->litigation_status ?? 'open') !== 'resolved')
                      <p class="text-muted small mt-2 mb-0">
                        {{ __('Close without blaming anyone (e.g. opened by mistake / not a real incident).') }}
                      </p>
                    @endif
                  @else
                    <form class="d-inline-block" action="{{ route('admin.support_ticket.reopen', $ticket->id) }}"
                      method="post">
                      @csrf
                      <button type="submit" class="btn btn-warning btn-md">
                        <i class="fas fa-redo mr-1"></i> {{ __('Reopen incident') }}
                      </button>
                    </form>
                  @endif
                </div>
                <div class="col-lg-12 my-3">
                  @if ($ticket->status == 1)
                    <span class="badge badge-warning">{{ __('Pending') }}</span>
                  @elseif($ticket->status == 2)
                    <span class="badge badge-primary">{{ __('Open') }}</span>
                  @else
                    <span class="badge badge-danger">{{ __('Closed') }}</span>
                  @endif
                  <span class="badge badge-secondary">{{ $ticket->created_at->format('d-m-Y') }}
                    {{ date('h.i A', strtotime($ticket->created_at)) }}</span>
                </div>
              </div>
              <div class="row">
                <div class="col-lg-8 offset-lg-2">
                  <p class="font-16">{{ $ticket->description }}</p>
                  @if (!empty($ticket->source_type) || !empty($ticket->booking_ref) || !empty($ticket->counterparty_name))
                    <div class="alert alert-info text-left mt-3">
                      <strong>{{ __('Transaction Context') }}</strong><br>
                      {{ __('Type') }}: {{ $ticket->source_type ?: '-' }}<br>
                      {{ __('Ticket / Booking ID') }}: {{ $ticket->booking_ref ?: '-' }}<br>
                      {{ __('Event ID') }}: {{ $ticket->event_id ?: '-' }}<br>
                      {{ __('Counterparty') }}: {{ $ticket->counterparty_name ?: '-' }}
                    </div>
                  @endif
                  @if (!empty($ticket->is_litigation) && $ticket->is_litigation)
                    <div class="card text-left mt-3" style="border:1px solid #e0a800;">
                      <div class="card-header bg-warning text-dark">
                        <i class="fas fa-balance-scale mr-1"></i>
                        <strong>{{ __('Litigation') }}</strong>
                        <span class="badge badge-dark ml-2">
                          {{ strtoupper($ticket->litigation_status ?? 'open') }}
                        </span>
                      </div>
                      <div class="card-body">
                        <p class="mb-1">
                          <strong>{{ __('Resale ticket') }}:</strong>
                          {{ $ticket->resale_ticket_id ?: '-' }}
                        </p>
                        <p class="mb-1">
                          <strong>{{ __('Current outcome') }}:</strong>
                          {{ $ticket->outcome ?: __('Not set') }}
                        </p>
                        @if (!empty($ticket->blame_user_type))
                          <p class="mb-3">
                            <strong>{{ __('Blame assigned to') }}:</strong>
                            {{ ucfirst($ticket->blame_user_type) }} #{{ $ticket->blame_user_id }}
                          </p>
                        @endif

                        @if (($ticket->litigation_status ?? 'open') !== 'resolved')
                          <form action="{{ route('admin.support_tickets.resolve_litigation', $ticket->id) }}"
                                method="POST">
                            @csrf
                            <div class="form-group">
                              <label for="litigation_outcome">{{ __('Resolution outcome (blame)') }}</label>
                              <select name="outcome" id="litigation_outcome" class="form-control" required>
                                <option value="">{{ __('-- Choose outcome --') }}</option>
                                <option value="seller">{{ __('Seller at fault') }}</option>
                                <option value="buyer">{{ __('Buyer at fault') }}</option>
                                <option value="unclear">{{ __('Unclear (no blame assigned)') }}</option>
                                <option value="none">{{ __('No incident (close without blame)') }}</option>
                              </select>
                              <small class="text-muted">{{ __('Used for the user confidence level. Choose “No incident” if this was opened by mistake — no user is blamed. The money decision is set below.') }}</small>
                            </div>

                            <hr>
                            <div class="form-group">
                              <label>{{ __('How should the money be settled?') }}</label>
                              <div class="custom-control custom-radio">
                                <input type="radio" id="settlement_timeline" name="settlement_mode" value="timeline"
                                       class="custom-control-input" checked onchange="toggleManualSettlement(false)">
                                <label class="custom-control-label" for="settlement_timeline">
                                  {{ __('Follow the standard timeline (automatic at T+48h)') }}
                                </label>
                              </div>
                              <div class="custom-control custom-radio">
                                <input type="radio" id="settlement_manual" name="settlement_mode" value="manual"
                                       class="custom-control-input" onchange="toggleManualSettlement(true)">
                                <label class="custom-control-label" for="settlement_manual">
                                  {{ __('Decide manually now') }}
                                </label>
                              </div>
                            </div>

                            <div id="manual_settlement_box" style="display:none; border:1px solid #f0e0a8; border-radius:8px; padding:12px; background:#fffdf5;">
                              <div class="form-group">
                                <label for="reimbursement_type">{{ __('Reimbursement to buyer') }}</label>
                                <select name="reimbursement_type" id="reimbursement_type" class="form-control"
                                        onchange="toggleReimbAmount()">
                                  <option value="none">{{ __('No reimbursement') }}</option>
                                  <option value="full">{{ __('Full reimbursement') }}</option>
                                  <option value="partial">{{ __('Partial reimbursement') }}</option>
                                </select>
                              </div>
                              <div class="form-group" id="reimb_amount_group" style="display:none;">
                                <label for="reimbursement_amount">{{ __('Partial amount') }}</label>
                                <input type="number" step="0.01" min="0" name="reimbursement_amount"
                                       id="reimbursement_amount" class="form-control" value="0">
                                <small class="text-muted">{{ __('Capped at the ticket total if higher.') }}</small>
                              </div>
                              <div class="form-group">
                                <div class="custom-control custom-checkbox">
                                  <input type="checkbox" id="release_to_seller" name="release_to_seller" value="1"
                                         class="custom-control-input">
                                  <label class="custom-control-label" for="release_to_seller">
                                    {{ __('Release the payment to the seller') }}
                                  </label>
                                </div>
                                <small class="text-muted">{{ __('You can choose a reimbursement and a release at the same time.') }}</small>
                              </div>
                            </div>

                            <div class="form-group mt-2">
                              <label for="resolver_notes">{{ __('Admin notes (optional)') }}</label>
                              <textarea name="notes" id="resolver_notes" class="form-control" rows="2"></textarea>
                            </div>
                            <button type="submit" class="btn btn-warning">
                              <i class="fas fa-gavel mr-1"></i> {{ __('Resolve litigation') }}
                            </button>
                          </form>
                          <script>
                            function toggleManualSettlement(show) {
                              document.getElementById('manual_settlement_box').style.display = show ? 'block' : 'none';
                            }
                            function toggleReimbAmount() {
                              var t = document.getElementById('reimbursement_type').value;
                              document.getElementById('reimb_amount_group').style.display = (t === 'partial') ? 'block' : 'none';
                            }
                          </script>
                        @else
                          <div class="alert alert-success mb-0">
                            <i class="fas fa-check mr-1"></i>
                            {{ __('Litigation resolved') }}
                            @if (!empty($ticket->resolved_at))
                              · {{ \Carbon\Carbon::parse($ticket->resolved_at)->format('d M Y H:i') }}
                            @endif
                            <hr class="my-2">
                            <div class="text-dark">
                              <strong>{{ __('Settlement') }}:</strong>
                              @if (($ticket->settlement_mode ?? 'timeline') === 'manual')
                                {{ __('Manual decision') }} —
                                @if (($ticket->reimbursement_type ?? 'none') === 'none')
                                  {{ __('no reimbursement') }}
                                @elseif (($ticket->reimbursement_type ?? 'none') === 'full')
                                  {{ __('full reimbursement') }}
                                @else
                                  {{ __('partial reimbursement') }} ({{ number_format((float) $ticket->reimbursement_amount, 2) }})
                                @endif
                                @if (!empty($ticket->release_to_seller))
                                  · {{ __('payment released to seller') }}
                                @endif
                              @else
                                {{ __('Standard timeline (automatic release)') }}
                              @endif
                            </div>
                          </div>
                        @endif
                      </div>
                    </div>
                  @endif
                  @if ($ticket->attachment)
                    @if ($ticket->user_type == 'customer')
                      <a href="{{ asset('assets/admin/img/support-ticket/' . $ticket->attachment) }}"
                        download="{{ __('support_file') }}" class="btn btn-primary"><i class="fas fa-download"></i>
                        {{ __('Download Attachment') }}</a>
                    @else
                      <a href="{{ asset('assets/admin/img/support-ticket/attachment/' . $ticket->attachment) }}"
                        download="{{ __('support_file') }}" class="btn btn-primary"><i class="fas fa-download"></i>
                        {{ __('Download Attachment') }}</a>
                    @endif
                  @endif
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-6">
      <div class="card card-round">
        <div class="card-body">
          <div class="card-title fw-mediumbold">{{ __('Replies') }}</div>
          <div class="card-list">
            <div class="messages-container">
              @php
                $isResaleTicket = strtolower((string) $ticket->source_type) === 'resale_ticket';
                $buyerCustomerId = null;
                $sellerCustomerId = null;
                if (
                    $isResaleTicket &&
                    strtolower((string) $ticket->user_type) === 'customer' &&
                    strtolower((string) $ticket->counterparty_user_type) === 'customer'
                ) {
                    $buyerCustomerId = (int) $ticket->user_id;
                    $sellerCustomerId = (int) $ticket->counterparty_user_id;
                }
              @endphp
              @if (count($ticket->messages) > 0)
                @foreach ($ticket->messages as $reply)
                  @if ($reply->type == 2)
                    @php
                      $admin = App\Models\Admin::where('id', $reply->user_id)->first();
                    @endphp
                    <div class="item-list">
                      <div class="avatar">
                        <img
                          src="{{ $admin && !empty($admin->image) ? asset('assets/admin/img/admins/' . $admin->image) : asset('assets/admin/img/propics/blank_user.jpg') }}"
                          alt="..." class="avatar-img rounded-circle">
                      </div>
                      <div class="info-user ml-3">
                        <div class="username">{{ $admin->username ?? 'Admin' }}</div>
                        <div class="status">{{ $admin ? ($admin->id == 1 ? __('Super Admin') : $admin->role->name) : __('Admin') }}</div>
                        <p>{!! $reply->reply !!}</p>
                        @if ($reply->file)
                          <a href="{{ asset('assets/admin/img/support-ticket/' . $reply->file) }}"
                            download="support_file" class="btn btn-rounded btn-info btn-sm">{{ __('Download') }}</a>
                        @endif
                      </div>
                    </div>
                  @else
                    @if ($reply->type == 1)
                      @php
                        $user = App\Models\Customer::where('id', $reply->user_id)->first();
                        $roleLabel = __('Customer');
                        if ($isResaleTicket) {
                            if (!is_null($buyerCustomerId) && (int) $reply->user_id === $buyerCustomerId) {
                                $roleLabel = __('Buyer');
                            } elseif (!is_null($sellerCustomerId) && (int) $reply->user_id === $sellerCustomerId) {
                                $roleLabel = __('Seller');
                            }
                        }
                      @endphp
                      <div class="item-list">
                        <div class="avatar">
                          @if ($user && $user->photo)
                            <img class="avatar-img rounded-circle"
                              src="{{ asset('assets/admin/img/customer-profile/' . $user->photo) }}" alt="user-photo">
                          @else
                            <img class="avatar-img rounded-circle" src="{{ asset('assets/front/images/profile.jpg') }}"
                              alt="user-photo">
                          @endif
                        </div>
                        <div class="info-user ml-3">
                          <div class="username">
                            {{ $user ? (trim(($user->fname ?? '') . ' ' . ($user->lname ?? '')) !== '' ? trim(($user->fname ?? '') . ' ' . ($user->lname ?? '')) : ($user->username ?? '-')) : '-' }}
                          </div>
                          <div class="status">{{ $roleLabel }}</div>
                          <p>{!! $reply->reply !!}</p>
                          @if ($reply->file)
                            <a href="{{ asset('assets/admin/img/support-ticket/' . $reply->file) }}"
                              download="support_file" class="btn btn-rounded btn-info btn-sm">{{ __('Download') }}</a>
                          @endif
                        </div>
                      </div>
                    @elseif ($reply->type == 3)
                      @php
                        $organizer = App\Models\Organizer::where('id', $ticket->user_id)->first();
                      @endphp
                      <div class="item-list">
                        <div class="avatar">
                          @if ($organizer)
                            @if ($organizer->photo)
                              <img class="avatar-img rounded-circle"
                                src="{{ asset('assets/admin/img/organizer-photo/' . $organizer->photo) }}"
                                alt="user-photo">
                            @else
                              <img class="avatar-img rounded-circle" src="{{ asset('assets/front/images/profile.jpg') }}"
                                alt="user-photo">
                            @endif
                          @else
                            <img class="avatar-img rounded-circle" src="{{ asset('assets/front/images/profile.jpg') }}"
                              alt="user-photo">
                          @endif


                        </div>
                        <div class="info-user ml-3">
                          <div class="username">{{ @$organizer->username }}</div>
                          <div class="status">{{ __('Organizer') }}</div>
                          <p>{!! $reply->reply !!}</p>
                          @if ($reply->file)
                            <a href="{{ asset('assets/admin/img/support-ticket/' . $reply->file) }}"
                              download="support_file" class="btn btn-rounded btn-info btn-sm">{{ __('Download') }}</a>
                          @endif
                        </div>
                      </div>
                    @endif
                  @endif
                @endforeach
              @endif
            </div>
          </div>
        </div>
      </div>
    </div>


    <div class="col-lg-6 message-type">
      <div class="card card-round">
        <div class="card-body">
          @if ($ticket->status != 3)
            <div class="card-title fw-mediumbold mb-2">{{ __('Reply to Ticket') }}</div>
            <form action="{{ route('admin.support_ticket.reply', $ticket->id) }}" id="ajaxform" method="POST"
              enctype="multipart/form-data">@csrf
              <div class="form-group">
                <label for="">{{ __('Message') . ' **' }} </label>
                <textarea name="reply" class="summernote" data-height="200"></textarea>
                <p class="em text-danger mb-0" id="errreply"></p>
              </div>
              <div class="form-group">
                <label for="">{{ __('Attachment') }}</label>
                <div class="input-group">
                  <div class="custom-file">
                      <input type="file" name="file" class="custom-file-input"
                      data-href="{{ route('admin.support_ticket.zip_file.upload') }}" name="file" id="zip_file"
                        accept=".zip,.rar,.pdf,.jpg,.jpeg,.png,.doc,.docx,.txt">
                    <label class="custom-file-label" for="zip_file">{{ __('Choose file') }}</label>
                  </div>
                </div>
                <p class="em text-danger mb-0" id="errfile"></p>
                <p class="mb-0 show-name"><small></small></p>
                <div class="progress progress-sm d-none">
                  <div class="progress-bar bg-success " role="progressbar" aria-valuenow="" aria-valuemin="0"
                    aria-valuemax=""></div>
                </div>
                  <p class="text-warning">{{ __('Allowed: zip, rar, pdf, jpg, jpeg, png, doc, docx, txt. Max 5 MB') }}</p>
              </div>
              <div class="form-group">
                <button type="submit" class="btn btn-success">{{ __('Send') }}</button>
              </div>
            </form>
          @else
            <div class="alert alert-secondary mb-0">
              {{ __('This incident is closed. Reopen it to reply or continue handling.') }}
            </div>
          @endif
        </div>
      </div>
    </div>
  </div>
@endsection
@section('script')
  <script src="{{ asset('assets/admin/js/support.js') }}"></script>
@endsection

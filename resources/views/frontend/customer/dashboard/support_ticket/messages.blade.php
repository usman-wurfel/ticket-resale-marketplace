@extends('frontend.layout')
@section('pageHeading')
  Incident details
@endsection
@section('custom-style')
  <link rel="stylesheet" href="{{ asset('assets/admin/css/summernote-content.css') }}">
  <style>
    .message-lists .single-message {
      padding: 18px 20px;
      border-radius: 12px;
    }

    .message-lists .single-message.single-message--you {
      margin-left: 70px;
      background: #eef6ff;
      border-left: 3px solid #0d6efd;
    }

    .message-lists .single-message.single-message--other {
      margin-right: 70px;
    }

    .message-lists .single-message .message {
      margin-top: 10px;
      padding: 12px 14px;
      border-radius: 10px;
      background: #ffffff;
    }

    .message-lists .single-message .message-head {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      gap: 10px;
    }

    .message-lists .single-message .message-head .name {
      margin-bottom: 2px;
      padding-right: 10px;
    }

    .message-lists .single-message .message-head .badge {
      margin-left: auto;
      white-space: nowrap;
    }

    .reply-section textarea.form-control {
      min-height: 120px;
      padding: 14px 16px;
      border-radius: 10px;
    }
  </style>
@endsection
@section('hero-section')
  <!-- Page Banner Start -->
  <section class="page-banner overlay pt-120 pb-125 rpt-90 rpb-95 lazy"
    data-bg="{{ asset('assets/admin/img/' . $basicInfo->breadcrumb) }}">
    <div class="banner-inner">
      <h2 class="page-title">
        @if (!empty($pageHeading))
          {{ $pageHeading->support_ticket_details_page_title ?? 'Incident details' }}
        @else
          Incident details
        @endif
      </h2>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="{{ route('customer.dashboard') }}">
              @if (!empty($pageHeading))
                {{ $pageHeading->customer_dashboard_page_title ?? __('Dashboard') }}
              @else
                {{ __('Dashboard') }}
              @endif
            </a></li>
          <li class="breadcrumb-item active">
            @if (!empty($pageHeading))
              {{ $pageHeading->support_ticket_details_page_title ?? 'Incident details' }}
            @else
              Incident details
            @endif
          </li>
        </ol>
      </nav>
    </div>
    </div>
  </section>
  <!-- Page Banner End -->
@endsection
@section('content')
  @php
    $toAgo = function ($dateTime) {
        if (empty($dateTime)) {
            return '-';
        }
        $date = $dateTime instanceof \Carbon\CarbonInterface ? $dateTime : \Carbon\Carbon::parse($dateTime);
        $seconds = $date->diffInSeconds(now());
        if ($seconds < 1) {
            return '0 sec ago';
        }
        $units = [
            ['seconds' => 31536000, 'label' => 'y'],
            ['seconds' => 2592000, 'label' => 'm'],
            ['seconds' => 86400, 'label' => 'd'],
            ['seconds' => 3600, 'label' => 'hour'],
            ['seconds' => 60, 'label' => 'min'],
            ['seconds' => 1, 'label' => 'sec'],
        ];
        foreach ($units as $unit) {
            if ($seconds >= $unit['seconds']) {
                $value = (int) floor($seconds / $unit['seconds']);
                $label = $unit['label'];
                if ($label === 'hour' && $value > 1) {
                    $label = 'hours';
                }
                return $value . ' ' . $label . ' ago';
            }
        }
        return '0 sec ago';
    };
    $isResaleIncident = strtolower((string) ($ticket->source_type ?? '')) === 'resale_ticket';
  @endphp
  <!--====== Start Dashboard Section ======-->
  <section class="user-dashbord">
    <div class="container">
      <div class="row">
        @includeIf('frontend.customer.partials.sidebar')
        <div class="col-lg-9">
          <div class="row">
            <div class="col-lg-12">
              <div class="user-profile-details">
                <div class="account-info">
                  <div class="title">
                    <h4>
                      @if (!empty($pageHeading))
                        {{ $pageHeading->support_ticket_details_page_title ?? 'Incident details' }}
                      @else
                        Incident details
                      @endif - #{{ $ticket->id }}
                    </h4>
                  </div>
                  <div class="main-info">
                    <div class="subject mb-1">
                      <h5>{{ $ticket->subject }}</h5>
                      <div class=" d-flex align-items-center">
                        @if ($ticket->status == 1)
                          <h6 class="badge badge-warning">{{ __('Pending') }}</h6>
                        @elseif($ticket->status == 2)
                          <h6 class="badge badge-primary">{{ __('Open') }}</h6>
                        @else
                          <h6 class="badge badge-success">{{ __('Closed') }}</h6>
                        @endif
                        <h6><span class="badge badge-light">{{ $toAgo($ticket->created_at) }}</span></h6>
                      </div>
                    </div>
                    <div class="mb-3 d-flex align-items-center" style="gap: 8px;">
                      @if ((int) $ticket->status === 3)
                        <form action="{{ route('customer.support_ticket.change_status', $ticket->id) }}" method="POST"
                          class="d-inline-block">
                          @csrf
                          <input type="hidden" name="status" value="2">
                          <button type="submit" class="btn btn-sm btn-outline-primary">
                            Reopen
                          </button>
                        </form>
                      @else
                        <form action="{{ route('customer.support_ticket.change_status', $ticket->id) }}" method="POST"
                          class="d-inline-block">
                          @csrf
                          <input type="hidden" name="status" value="3">
                          <button type="submit" class="btn btn-sm btn-outline-danger">
                            Mark as Closed
                          </button>
                        </form>
                      @endif
                    </div>
                    <div class="description">
                      <p>{{ $ticket->description }}</p>
                    </div>
                    @if ($ticket->attachment != null)
                      <a href="{{ asset('assets/admin/img/support-ticket/' . $ticket->attachment) }}"
                        download="support.zip" class="btn btn-primary"><i class="fas fa-download"></i>
                        {{ __('Download') }}</a>
                    @endif
                    <hr>
                    <div class="message-section">
                      <h5>{{ __('Replies') }}</h5>
                      <div class="message-lists">
                        <div class="messages">
                          @if (count($ticket->messages) > 0)
                            @foreach ($ticket->messages as $reply)
                                @if ($reply->type == 2)
                                  @php
                                    $admin = App\Models\Admin::where('id', $reply->user_id)->first();
                                  @endphp
                                <div class="single-message single-message--other">
                                    <div class="user-details">
                                      <div class="user-img">
                                        <img class="mh-60"
                                          src="{{ $admin->image ? asset('assets/admin/img/admins/' . $admin->image) : asset('assets/admin/img/propics/blank_user.jpg') }}"
                                          alt="">
                                      </div>
                                      <div class="user-infos">
                                        <div class="message-head">
                                          <h6 class="name">{{ __('Admin') }}</h6>
                                          <span class="badge badge-secondary">{{ $toAgo($reply->created_at) }}</span>
                                        </div>
                                        <span class="type"><i class="fas fa-user"></i>
                                          {{ $admin ? ($admin->id == 1 ? 'Super Admin' : ($admin->role->name ?? 'Admin')) : 'Admin' }}</span>
                                        @if ($reply->file != null)
                                          <a href="{{ asset('assets/admin/img/support-ticket/' . $reply->file) }}"
                                            download="support.zip" class="reply-download-btn"><i
                                              class="fas fa-download"></i>
                                            {{ __('Download') }}</a>
                                        @endif
                                      </div>
                                    </div>
                                    <div class="message">
                                      <div class="summernote-content">
                                        <p>{!! $reply->reply !!}</p>
                                      </div>
                                    </div>
                                  </div>
                                @else
                                  @php
                                    $user = App\Models\Customer::where('id', $reply->user_id)->first();
                                    $isCurrentUserReply = (int) $reply->user_id === (int) Auth::guard('customer')->id();
                                    $roleLabel = $isCurrentUserReply ? 'You' : 'Counterparty';
                                    $hideOtherIdentity = $isResaleIncident && !$isCurrentUserReply;
                                  @endphp
                                  <div class="single-message {{ $isCurrentUserReply ? 'single-message--you' : 'single-message--other' }}">
                                    <div class="user-details">
                                      <div class="user-img">
                                        @if (!$hideOtherIdentity && $user && $user->photo != null)
                                          <img class="mh-60"
                                            src="{{ asset('assets/admin/img/customer-profile/' . $user->photo) }}"
                                            alt="">
                                        @else
                                          <img class="mh-60" src="{{ asset('assets/front/images/profile.jpg') }}"
                                            alt="">
                                        @endif
                                      </div>
                                      <div class="user-infos">
                                        <div class="message-head">
                                          <h6 class="name">
                                            @if ($isCurrentUserReply)
                                              You
                                            @elseif ($hideOtherIdentity)
                                              {{ __('Anonymous participant') }}
                                            @elseif ($user)
                                              {{ trim(($user->fname ?? '') . ' ' . ($user->lname ?? '')) !== '' ? trim(($user->fname ?? '') . ' ' . ($user->lname ?? '')) : ($user->username ?? '-') }}
                                            @else
                                              -
                                            @endif
                                          </h6>
                                          <span class="badge badge-secondary">{{ $toAgo($reply->created_at) }}</span>
                                        </div>
                                        @if (!$isCurrentUserReply)
                                          <span class="type"><i class="fas fa-user"></i>{{ $roleLabel }}</span>
                                        @endif
                                        @if ($reply->file != null)
                                          <a href="{{ asset('assets/admin/img/support-ticket/' . $reply->file) }}"
                                            download="support.zip" class="reply-download-btn"><i
                                              class="fas fa-download"></i>
                                            {{ __('Download') }}</a>
                                        @endif

                                      </div>
                                    </div>
                                    <div class="message">
                                      <div class="summernote-content">
                                        <p>{!! $reply->reply !!}</p>
                                      </div>
                                    </div>
                                  </div>
                                @endif
                            @endforeach
                          @else
                            <h4>{{ __('No Message Found') }}</h4>
                          @endif
                        </div>
                        @if ($ticket->status != 3)
                          <hr>
                          <div class="reply-section">
                            <h5>{{ __('Reply') }}</h5>
                            <form action="{{ route('customer-reply', $ticket->id) }}" method="POST"
                              enctype="multipart/form-data">
                              @csrf
                              <div class="form-group">
                                <label for="">{{ __('Reply') }} *</label>
                                <textarea name="reply" class="form-control" placeholder="{{ __('Enter Reply') }}"></textarea>
                                @error('reply')
                                  <p class="text-danger">{{ $message }}</p>
                                @enderror
                              </div>
                              <div class="form-group">
                                <input type="file" name="file" class="form-control"
                                  accept=".zip,.rar,.pdf,.jpg,.jpeg,.png,.doc,.docx,.txt">
                                <p class="text-warning">{{ __('Allowed: zip, rar, pdf, jpg, jpeg, png, doc, docx, txt. Max upload size 5 MB') }}</p>
                                @error('file')
                                  <p class="text-danger">{{ $message }}</p>
                                @enderror
                              </div>
                              <div class="form-group">
                                <button type="submit" class="btn btn-success"><i class="fas fa-retweet"></i>
                                  {{ __('Reply') }}</button>
                              </div>
                            </form>
                          </div>
                        @endif
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
  <!--====== End Dashboard Section ======-->
@endsection

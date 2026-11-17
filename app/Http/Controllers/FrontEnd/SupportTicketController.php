<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Event\Booking;
use App\Models\Event\EventContent;
use App\Models\Event\ResaleTicket;
use App\Models\SupportTicket;
use App\Models\SupportTicketStatus;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Mews\Purifier\Facades\Purifier;

class SupportTicketController extends Controller
{
  public function index()
  {
    $customerId = (int) Auth::guard('customer')->user()->id;
    $collection = SupportTicket::where(function ($query) use ($customerId) {
      $query->where(function ($q) use ($customerId) {
        $q->where('user_type', 'customer')
          ->where('user_id', $customerId);
      })->orWhere(function ($q) use ($customerId) {
        $q->where('counterparty_user_type', 'customer')
          ->where('counterparty_user_id', $customerId);
      });
    })->orderBy('id', 'desc')->get();
    return view('frontend.customer.dashboard.support_ticket.index', compact('collection'));
  }
  //create
  public function create()
  {
    $context = $this->resolveTicketContext(request());
    return view('frontend.customer.dashboard.support_ticket.create', compact('context'));
  }
  //store
  public function store(Request $request)
  {
    $request->validate([
      'subject' => 'required',
      'email' => 'required',
      'description' => 'required',
      'attachment' => 'nullable|file|mimes:zip,rar,pdf,jpg,jpeg,png,doc,docx,txt|max:5000',
      'source_type' => 'nullable|string|max:50',
      'source_id' => 'nullable|integer'
    ]);

    $in = $request->all();
    $in['user_id'] = Auth::guard('customer')->user()->id;
    $in['user_type'] = 'customer';
    $file = $request->file('attachment');
    if ($file) {
      $extension = $file->getClientOriginalExtension();
      $directory = public_path('assets/admin/img/support-ticket/');
      $fileName = uniqid() . '.' . $extension;
      @mkdir($directory, 0775, true);
      $file->move($directory, $fileName);
      $in['attachment'] = $fileName;
    }
    $context = $this->resolveTicketContext($request);
    if (!empty($context)) {
      $in['source_type'] = $context['source_type'] ?? null;
      $in['source_id'] = $context['source_id'] ?? null;
      $in['event_id'] = $context['event_id'] ?? null;
      $in['booking_ref'] = $context['booking_ref'] ?? null;
      $in['counterparty_user_type'] = $context['counterparty_user_type'] ?? null;
      $in['counterparty_user_id'] = $context['counterparty_user_id'] ?? null;
      $in['counterparty_name'] = $context['counterparty_name'] ?? null;
    }

    // Auto-flag as a litigation when the ticket targets an already
    // transferred resale ticket within the [transfer, eventEnd + 48h] window.
    if (($in['source_type'] ?? null) === 'resale_ticket' && !empty($in['source_id'])) {
      $resale = ResaleTicket::with('event')->find($in['source_id']);
      if ($resale) {
        $in['resale_ticket_id'] = (int) $resale->id;
        if ($resale->transfer_status === ResaleTicket::TRANSFER_DONE) {
          $windowClose = null;
          if ($resale->event && $resale->event->end_date_time) {
            $windowClose = Carbon::parse($resale->event->end_date_time)
              ->addHours((int) config('resale.release_hold_hours', 48));
          }
          if (!$windowClose || Carbon::now()->lessThanOrEqualTo($windowClose)) {
            $in['is_litigation'] = true;
            $in['litigation_status'] = SupportTicket::LITIGATION_OPEN;
          }
        }
      }
    }

    $save = SupportTicket::create($in);
    Session::flash('success', 'Ticket has been submitted successfully..!');
    return back();
  }
  //message
  public function message($id)
  {
    $bex = SupportTicketStatus::first();

    if ($bex->support_ticket_status == 0) {
      return back();
    }
    $customerId = (int) Auth::guard('customer')->id();
    $ticket = SupportTicket::where('id', $id)->firstOrFail();
    $isOwner = strtolower((string) $ticket->user_type) === 'customer' && (int) $ticket->user_id === $customerId;
    $isCounterparty = strtolower((string) $ticket->counterparty_user_type) === 'customer' && (int) $ticket->counterparty_user_id === $customerId;
    if (!$isOwner && !$isCounterparty) {
      abort(403);
    }

    return view('frontend.customer.dashboard.support_ticket.messages', compact('ticket'));
  }
  //reply
  public function reply(Request $request, $id)
  {
    $customerId = (int) Auth::guard('customer')->id();
    $ticket = SupportTicket::where('id', $id)->firstOrFail();
    $isOwner = strtolower((string) $ticket->user_type) === 'customer' && (int) $ticket->user_id === $customerId;
    $isCounterparty = strtolower((string) $ticket->counterparty_user_type) === 'customer' && (int) $ticket->counterparty_user_id === $customerId;
    if (!$isOwner && !$isCounterparty) {
      abort(403);
    }

    $rules = [
      'reply' => 'required',
      'file' => 'nullable|file|mimes:zip,rar,pdf,jpg,jpeg,png,doc,docx,txt|max:5000',
    ];

    $messages = [
      'file.mimes' => 'Allowed formats: zip, rar, pdf, jpg, jpeg, png, doc, docx, txt.',
      'file.max' => 'File may not be greater than 5 MB.',
    ];

    $request->validate($rules, $messages);
    $input = $request->all();

    $input['reply'] = Purifier::clean($request->reply, 'youtube');
    $input['type'] = 1;
    $input['user_id'] = Auth::guard('customer')->user()->id;
    $input['support_ticket_id'] = $id;
    if ($request->hasFile('file')) {
      $file = $request->file('file');
      $filename = uniqid() . '.' . $file->getClientOriginalExtension();
      @mkdir(public_path('assets/admin/img/support-ticket/'), 0775, true);
      $file->move(public_path('assets/admin/img/support-ticket/'), $filename);
      $input['file'] = $filename;
    }

    $data = new Conversation();
    $data->create($input);

    SupportTicket::where('id', $id)->update([
      'last_message' => Carbon::now()
    ]);

    Session::flash('success', 'Message Sent Successfully');
    return back();
  }

  public function changeStatus(Request $request, $id)
  {
    $customerId = (int) Auth::guard('customer')->id();
    $ticket = SupportTicket::where('id', $id)->firstOrFail();
    $isOwner = strtolower((string) $ticket->user_type) === 'customer' && (int) $ticket->user_id === $customerId;
    $isCounterparty = strtolower((string) $ticket->counterparty_user_type) === 'customer' && (int) $ticket->counterparty_user_id === $customerId;
    if (!$isOwner && !$isCounterparty) {
      abort(403);
    }

    $request->validate([
      'status' => 'required|in:2,3'
    ]);

    $ticket->status = (int) $request->status;
    $ticket->save();

    Session::flash('success', 'Incident status updated successfully.');
    return back();
  }

  private function resolveTicketContext(Request $request): array
  {
    $sourceType = (string) $request->input('source_type', '');
    $sourceId = (int) $request->input('source_id', 0);
    $customer = Auth::guard('customer')->user();
    $customerId = $customer ? (int) $customer->id : 0;

    if (empty($sourceType) || $sourceId <= 0 || $customerId <= 0) {
      return [];
    }

    if ($sourceType === 'platform_booking') {
      $booking = Booking::find($sourceId);
      if (!$booking || (int) $booking->customer_id !== $customerId) {
        return [];
      }

      $eventTitle = EventContent::where('event_id', $booking->event_id)->value('title') ?: ('Event #' . $booking->event_id);
      $counterpartyName = $booking->organizer ? ($booking->organizer->username ?? 'Organizer') : 'Admin';
      $counterpartyType = $booking->organizer ? 'organizer' : 'admin';
      $counterpartyId = $booking->organizer ? (int) $booking->organizer_id : null;

      return [
        'source_type' => 'platform_booking',
        'source_id' => (int) $booking->id,
        'event_id' => (int) $booking->event_id,
        'event_title' => $eventTitle,
        'booking_ref' => (string) $booking->booking_id,
        'counterparty_user_type' => $counterpartyType,
        'counterparty_user_id' => $counterpartyId,
        'counterparty_name' => $counterpartyName,
      ];
    }

    if ($sourceType === 'resale_ticket') {
      $ticket = ResaleTicket::with(['seller', 'buyer'])->find($sourceId);
      if (!$ticket) {
        return [];
      }

      $isBuyer = (int) $ticket->buyer_customer_id === $customerId;
      $isSeller = (int) $ticket->seller_customer_id === $customerId;
      if (!$isBuyer && !$isSeller) {
        return [];
      }

      $eventTitle = EventContent::where('event_id', $ticket->event_id)->value('title') ?: ('Event #' . $ticket->event_id);

      $counterpartyName = '-';
      $counterpartyId = null;
      if ($isBuyer && $ticket->seller) {
        $counterpartyName = trim(($ticket->seller->fname ?? '') . ' ' . ($ticket->seller->lname ?? ''));
        if ($counterpartyName === '') {
          $counterpartyName = $ticket->seller->username ?? '-';
        }
        $counterpartyId = (int) $ticket->seller_customer_id;
      } elseif ($isSeller && $ticket->buyer) {
        $counterpartyName = trim(($ticket->buyer->fname ?? '') . ' ' . ($ticket->buyer->lname ?? ''));
        if ($counterpartyName === '') {
          $counterpartyName = $ticket->buyer->username ?? '-';
        }
        $counterpartyId = (int) $ticket->buyer_customer_id;
      }

      return [
        'source_type' => 'resale_ticket',
        'source_id' => (int) $ticket->id,
        'event_id' => (int) $ticket->event_id,
        'event_title' => $eventTitle,
        'booking_ref' => !empty($ticket->booking_id) ? (string) $ticket->booking_id : null,
        'counterparty_user_type' => 'customer',
        'counterparty_user_id' => $counterpartyId,
        'counterparty_name' => $counterpartyName,
      ];
    }

    return [];
  }
}

<?php

namespace App\Http\Controllers\BackEnd;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\SupportTicket;
use App\Models\SupportTicketStatus;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use Mews\Purifier\Facades\Purifier;

class SupportTicketController extends Controller
{
  //index
  public function index(Request $request)
  {
    $role_id = Auth::guard('admin')->user()->role_id;

    $status = $ticket_id = null;
    $fromDate = $request->filled('from_date') ? $request->from_date : null;
    $toDate = $request->filled('to_date') ? $request->to_date : null;
    if ($request->filled('status')) {
      $status = $request['status'];
    }
    if ($request->filled('ticket_id')) {
      $ticket_id = $request['ticket_id'];
    }
    if (empty($role_id)) {
      $collection = SupportTicket::with(['customer', 'organizer', 'counterpartyCustomer'])->when($status, function ($query, $status) {
        return $query->where('status',  $status);
      })
        ->when($ticket_id, function ($query, $ticket_id) {
          return $query->where('id', 'like', '%' . $ticket_id . '%');
        })
        ->when($fromDate, function ($query) use ($fromDate) {
          return $query->whereDate('created_at', '>=', $fromDate);
        })
        ->when($toDate, function ($query) use ($toDate) {
          return $query->whereDate('created_at', '<=', $toDate);
        })
        ->orderByDesc('id')
        ->paginate(10);
    } else {
      $collection = SupportTicket::with(['customer', 'organizer', 'counterpartyCustomer'])->when($status, function ($query, $status) {
        return $query->where('status',  $status);
      })
        ->when($ticket_id, function ($query, $ticket_id) {
          return $query->where('id', 'like', '%' . $ticket_id . '%');
        })
        ->when($fromDate, function ($query) use ($fromDate) {
          return $query->whereDate('created_at', '>=', $fromDate);
        })
        ->when($toDate, function ($query) use ($toDate) {
          return $query->whereDate('created_at', '<=', $toDate);
        })
        ->where('admin_id', Auth::guard('admin')->user()->id)
        ->orderByDesc('id')
        ->paginate(10);
    }

    return view('backend.support_ticket.index', compact('collection'));
  }

  public function export(Request $request)
  {
    $roleId = Auth::guard('admin')->user()->role_id;
    $status = $request->filled('status') ? $request->status : null;
    $ticketId = $request->filled('ticket_id') ? $request->ticket_id : null;
    $fromDate = $request->filled('from_date') ? $request->from_date : null;
    $toDate = $request->filled('to_date') ? $request->to_date : null;

    $query = SupportTicket::with(['customer', 'organizer'])
      ->when($status, function ($q, $status) {
        return $q->where('status', $status);
      })
      ->when($ticketId, function ($q, $ticketId) {
        return $q->where('id', 'like', '%' . $ticketId . '%');
      })
      ->when($fromDate, function ($q) use ($fromDate) {
        return $q->whereDate('created_at', '>=', $fromDate);
      })
      ->when($toDate, function ($q) use ($toDate) {
        return $q->whereDate('created_at', '<=', $toDate);
      });

    if (!empty($roleId)) {
      $query->where('admin_id', Auth::guard('admin')->user()->id);
    }

    $tickets = $query->orderByDesc('id')->get();

    $rows = [];
    foreach ($tickets as $item) {
      $username = '-';
      if ($item->user_type === 'organizer') {
        $username = $item->organizer->username ?? '-';
      } else {
        $username = $item->customer->username ?? '-';
      }
      $statusText = $item->status == 1 ? 'Pending' : ($item->status == 2 ? 'Open' : ($item->status == 3 ? 'Closed' : 'Unknown'));
      $rows[] = [
        'id' => $item->id,
        'user_type' => ucfirst((string) $item->user_type),
        'username' => $username,
        'email' => $item->email ?: '-',
        'subject' => $item->subject,
        'status' => $statusText,
      ];
    }

    $fileName = 'all-incidents.csv';
    $headers = [
      'Content-Type' => 'text/csv; charset=UTF-8',
      'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
    ];

    return response()->streamDownload(function () use ($rows) {
      $out = fopen('php://output', 'w');
      fputcsv($out, ['Ticket ID', 'User Type', 'Username', 'Email', 'Subject', 'Status']);

      foreach ($rows as $row) {
        fputcsv($out, [
          (string) ($row['id'] ?? ''),
          (string) ($row['user_type'] ?? ''),
          (string) ($row['username'] ?? ''),
          (string) ($row['email'] ?? ''),
          (string) ($row['subject'] ?? ''),
          (string) ($row['status'] ?? ''),
        ]);
      }

      fclose($out);
    }, $fileName, $headers);
  }
  //message
  public function message($id)
  {
    $ticket = SupportTicket::where('id', $id)->firstOrFail();
    return view('backend.support_ticket.messages', compact('ticket'));
  }
  public function zip_file_upload(Request $request)
  {
    $rules = [
      'file' => 'nullable|file|mimes:zip,rar,pdf,jpg,jpeg,png,doc,docx,txt|max:5000',
    ];

    $messages = [
      'file.mimes' => 'Allowed formats: zip, rar, pdf, jpg, jpeg, png, doc, docx, txt.',
      'file.max' => 'File may not be greater than 5 MB.',
    ];

    $validator = Validator::make($request->all(), $rules, $messages);
    if ($validator->fails()) {
      return response()->json(array('errors' => $validator->getMessageBag()->toArray()));
    }

    if ($request->hasFile('file')) {
      $file = $request->file('file');
      $filename = uniqid() . '.' . $file->getClientOriginalExtension();
      $file->move(public_path('assets/front/temp/'), $filename);
      $input['file'] = $filename;
    }

    return response()->json(['data' => 1]);
  }
  public function ticketreply(Request $request, $id)
  {
    $ticket = SupportTicket::findOrFail($id);

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

    $reply = $request->reply;
    $input['reply'] = Purifier::clean($reply, 'youtube');
    $input['user_id'] = Auth::guard('admin')->user()->id;
    $input['support_ticket_id'] = $id;
    if ($request->hasFile('file')) {
      $file = $request->file('file');
      $filename = uniqid() . '.' . $file->getClientOriginalExtension();
      $file->move(public_path('assets/admin/img/support-ticket/'), $filename);
      $input['file'] = $filename;
    }

    $data = new Conversation();
    $input['type'] = 2;
    $data->create($input);

    $files = glob('assets/front/temp/*');
    foreach ($files as $file) {
      unlink($file);
    }

    SupportTicket::where('id', $id)->update([
      'last_message' => Carbon::now(),
      'status' => 2,
    ]);

    Session::flash('success', 'Message Sent Successfully');
    return back();
  }
  //ticket_closed
  public function ticket_closed($id)
  {
    $ticket = SupportTicket::findOrFail($id);

    // Point 14: closing a litigation without blame (accidental / not a real incident).
    if ($ticket->is_litigation && ($ticket->litigation_status ?? '') !== SupportTicket::LITIGATION_RESOLVED) {
      try {
        app(\App\Services\Resale\LitigationService::class)->resolve(
          $ticket,
          SupportTicket::OUTCOME_NONE,
          (int) Auth::guard('admin')->id(),
          [
            'mode' => SupportTicket::SETTLEMENT_TIMELINE,
            'reimbursement_type' => SupportTicket::REIMBURSE_NONE,
            'reimbursement_amount' => 0,
            'release_to_seller' => false,
          ]
        );
      } catch (\Throwable $e) {
        Session::flash('error', 'Unable to close litigation: ' . $e->getMessage());
        return back();
      }
    } else {
      $ticket->update(['status' => SupportTicket::STATUS_CLOSED]);
    }

    Session::flash('success', 'Support Ticket closed successfully!');
    return back();
  }

  public function ticket_reopen($id)
  {
    $ticket = SupportTicket::findOrFail($id);
    if ((int) $ticket->status !== SupportTicket::STATUS_CLOSED) {
      Session::flash('error', 'Only closed incidents can be reopened.');
      return back();
    }

    $data = ['status' => SupportTicket::STATUS_OPEN];

    // If closed as "not a real incident" (outcome none), undo that resolution
    // so admins can continue handling the litigation.
    if (
      $ticket->is_litigation
      && ($ticket->outcome ?? '') === SupportTicket::OUTCOME_NONE
      && ($ticket->litigation_status ?? '') === SupportTicket::LITIGATION_RESOLVED
    ) {
      $data['litigation_status'] = SupportTicket::LITIGATION_OPEN;
      $data['outcome'] = null;
      $data['blame_user_type'] = null;
      $data['blame_user_id'] = null;
      $data['resolved_at'] = null;
      $data['resolver_admin_id'] = null;
      $data['settlement_mode'] = SupportTicket::SETTLEMENT_TIMELINE;
      $data['reimbursement_type'] = SupportTicket::REIMBURSE_NONE;
      $data['reimbursement_amount'] = 0;
      $data['release_to_seller'] = false;
    }

    $ticket->update($data);
    Session::flash('success', 'Incident reopened successfully.');
    return back();
  }

  public function create()
  {
    $customers = \App\Models\Customer::orderBy('username')->orderBy('email')
      ->get(['id', 'username', 'email', 'fname', 'lname']);
    $resaleTickets = \App\Models\Event\ResaleTicket::with('eventContent')
      ->orderByDesc('id')
      ->limit(200)
      ->get(['id', 'event_id', 'seller_customer_id', 'buyer_customer_id', 'status', 'booking_id']);

    return view('backend.support_ticket.create', compact('customers', 'resaleTickets'));
  }

  public function store(Request $request)
  {
    $request->validate([
      'subject' => 'required|string|max:255',
      'description' => 'required|string',
      'email' => 'nullable|email|max:255',
      'customer_id' => 'nullable|integer|exists:customers,id',
      'resale_ticket_id' => 'nullable|integer|exists:resale_tickets,id',
      'is_litigation' => 'nullable|boolean',
      'attachment' => 'nullable|file|mimes:zip,rar,pdf,jpg,jpeg,png,doc,docx,txt|max:5000',
    ]);

    $in = [
      'subject' => $request->subject,
      'description' => Purifier::clean($request->description, 'youtube'),
      'status' => SupportTicket::STATUS_OPEN,
      'admin_id' => Auth::guard('admin')->id(),
      'last_message' => Carbon::now(),
    ];

    if ($request->filled('customer_id')) {
      $customer = \App\Models\Customer::find($request->customer_id);
      $in['user_id'] = $customer->id;
      $in['user_type'] = 'customer';
      $in['email'] = $request->filled('email') ? $request->email : $customer->email;
    } else {
      // Admin-opened incidents may have no linked customer (user_id nullable).
      $in['user_id'] = null;
      $in['user_type'] = 'admin';
      $in['email'] = $request->email ?: Auth::guard('admin')->user()->email;
    }

    if ($request->filled('resale_ticket_id')) {
      $resale = \App\Models\Event\ResaleTicket::with('event')->find($request->resale_ticket_id);
      if ($resale) {
        $in['resale_ticket_id'] = $resale->id;
        $in['source_type'] = 'resale_ticket';
        $in['source_id'] = $resale->id;
        $in['event_id'] = $resale->event_id;
        $in['booking_ref'] = $resale->booking_id ?: ('#' . $resale->id);
        if ($request->boolean('is_litigation')) {
          $in['is_litigation'] = true;
          $in['litigation_status'] = SupportTicket::LITIGATION_OPEN;
        }
      }
    } elseif ($request->boolean('is_litigation')) {
      $in['is_litigation'] = true;
      $in['litigation_status'] = SupportTicket::LITIGATION_OPEN;
    }

    if ($request->hasFile('attachment')) {
      $file = $request->file('attachment');
      $filename = uniqid() . '.' . $file->getClientOriginalExtension();
      $directory = public_path('assets/admin/img/support-ticket/attachment/');
      @mkdir($directory, 0775, true);
      $file->move($directory, $filename);
      $in['attachment'] = $filename;
    }

    $ticket = SupportTicket::create($in);

    Session::flash('success', 'Incident ticket created successfully.');
    return redirect()->route('admin.support_tickets.message', $ticket->id);
  }

  public function unassign_stuff($id)
  {
    SupportTicket::where('id', $id)->update([
      'admin_id' => null,
    ]);

    Session::flash('success', 'Unassign stuff successfully!');
    return back();
  }

  //setting
  public function setting()
  {
    $content = SupportTicketStatus::where('id', 1)->first();
    return view('backend.support_ticket.setting', compact('content'));
  }
  //update_setting
  public function update_setting(Request $request)
  {
    $status = SupportTicketStatus::where('id', 1)->first();
    $status->support_ticket_status = $request->support_ticket_status;
    $status->save();
    Session::flash('success', 'Updated Successfully');
    return response()->json(['status' => 'success'], 200);
  }
  //assign_stuff.supoort.ticket
  public function assign_stuff(Request $request, $id)
  {
    $support_ticket = SupportTicket::where('id', $id)->first();
    $support_ticket->admin_id = $request->admin_id;
    $support_ticket->save();
    Session::flash('success', 'Add Stuff to this support ticket successfully..!');
    return back();
  }


  //delete
  public function delete($id)
  {
    //delete all support ticket
    $support_ticket = SupportTicket::find($id);
    if ($support_ticket) {
      //delete conversation 
      $messages = $support_ticket->messages()->get();
      foreach ($messages as $message) {
        @unlink(public_path('assets/admin/img/support-ticket/' . $message->file));
        $message->delete();
      }
      @unlink(public_path('assets/admin/img/support-ticket/attachment/') . $support_ticket->attachment);
      $support_ticket->delete();
    }
    Session::flash('success', 'Deleted Successfully');
    return back();
  }

  /**
   * Resolve a litigation by recording the operator's outcome decision
   * (seller / buyer / unclear). Recalculates the blamed user's confidence
   * level and, once the last open litigation is resolved and the T+48h
   * window has passed, also releases the payment.
   */
  public function resolveLitigation(Request $request, $id)
  {
    $request->validate([
      'outcome' => 'required|in:seller,buyer,unclear,none',
      'settlement_mode' => 'nullable|in:timeline,manual',
      'reimbursement_type' => 'nullable|in:none,partial,full',
      'reimbursement_amount' => 'nullable|numeric|min:0',
      'release_to_seller' => 'nullable|boolean',
    ]);

    $ticket = SupportTicket::findOrFail($id);
    if (!$ticket->is_litigation) {
      Session::flash('error', 'This incident is not a litigation.');
      return back();
    }

    $mode = $request->input('settlement_mode', 'timeline');
    $settlement = [
      'mode' => $mode,
      'reimbursement_type' => $request->input('reimbursement_type', 'none'),
      'reimbursement_amount' => (float) $request->input('reimbursement_amount', 0),
      'release_to_seller' => $request->boolean('release_to_seller'),
    ];

    try {
      app(\App\Services\Resale\LitigationService::class)
        ->resolve($ticket, $request->outcome, (int) Auth::guard('admin')->id(), $settlement);

      // For the timeline mode only, try a release now in case the T+48h due
      // date has already passed and this was the last unresolved litigation.
      // (Manual settlements are already finalized inside the service.)
      if ($mode !== 'manual' && $ticket->resale_ticket_id) {
        $resale = \App\Models\Event\ResaleTicket::find($ticket->resale_ticket_id);
        if ($resale) {
          app(\App\Services\Resale\PaymentReleaseService::class)->releaseForTicket($resale);
        }
      }

      Session::flash('success', 'Litigation resolved successfully.');
    } catch (\Throwable $e) {
      Session::flash('error', 'Unable to resolve litigation: ' . $e->getMessage());
    }

    return back();
  }

  public function bulk_delete(Request $request)
  {
    $ids = $request->ids;
    foreach ($ids as $id) {
      $support_ticket = SupportTicket::find($id);
      if ($support_ticket) {
        //delete conversation 
        $messages = $support_ticket->messages()->get();
        foreach ($messages as $message) {
          @unlink(public_path('assets/admin/img/support-ticket/' . $message->file));
          $message->delete();
        }
        @unlink(public_path('assets/admin/img/support-ticket/attachment/') . $support_ticket->attachment);
        $support_ticket->delete();
      }
    }
    Session::flash('success', 'Deleted Successfully');
    return Response::json(['status' => 'success'], 200);
  }
}

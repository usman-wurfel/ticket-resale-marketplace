<?php

namespace App\Http\Controllers\BackEnd;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Customer;
use App\Models\Event\Booking;
use App\Models\Event\ResaleTicket;
use App\Models\Event\Wishlist;
use App\Models\Event as PlatformEvent;
use App\Models\Language;
use App\Models\Transaction;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CustomerManagementController extends Controller
{
  private $admin_user_name;
  public function __construct()
  {
    $admin = Admin::select('username')->first();
    $this->admin_user_name = $admin->username;
  }

  public function index(Request $request)
  {
    $searchKey = null;

    if ($request->filled('info')) {
      $searchKey = $request['info'];
    }

    $customers = Customer::when($searchKey, function ($query, $searchKey) {
      return $query->where(function ($q) use ($searchKey) {
        $q->where('fname', 'like', '%' . $searchKey . '%')
          ->orWhere('lname', 'like', '%' . $searchKey . '%')
          ->orWhere('username', 'like', '%' . $searchKey . '%')
          ->orWhere('email', 'like', '%' . $searchKey . '%');
      });
    })
      ->orderBy('id', 'desc')
      ->paginate(10);

    $stats = [];
    $pageCustomerIds = $customers->pluck('id')->toArray();
    if (!empty($pageCustomerIds)) {
      foreach ($pageCustomerIds as $customerId) {
        $soldQty = (int) ResaleTicket::where('seller_customer_id', $customerId)->where('status', 'sold')->sum('quantity');
        $onHoldQty = (int) ResaleTicket::where('seller_customer_id', $customerId)->where('status', 'reserved')->sum('quantity');
        $inHandQty = (int) ResaleTicket::where('seller_customer_id', $customerId)
          ->whereNull('parent_id')
          ->where('status', 'listed')
          ->sum('quantity');
        $earnings = $this->getSellerNetEarnings($customerId);

        $pdfFiles = 0;
        $sellerListings = ResaleTicket::where('seller_customer_id', $customerId)
          ->whereNull('parent_id')
          ->get(['ticket_pdfs', 'ticket_pdf']);
        foreach ($sellerListings as $listing) {
          if (!empty($listing->ticket_pdfs)) {
            $decoded = json_decode((string) $listing->ticket_pdfs, true);
            if (is_array($decoded)) {
              $pdfFiles += count(array_filter($decoded));
            }
          } elseif (!empty($listing->ticket_pdf)) {
            $pdfFiles += 1;
          }
        }

        $stats[$customerId] = [
          'sold_qty' => $soldQty,
          'on_hold_qty' => $onHoldQty,
          'in_hand_qty' => $inHandQty,
          'earnings' => $earnings,
          'pdf_files' => $pdfFiles,
        ];
      }
    }

    return view('backend.end-user.customer.index', compact('customers', 'stats'));
  }

  public function exportCustomers(Request $request)
  {
    $searchKey = $request->filled('info') ? $request['info'] : null;

    $customers = Customer::when($searchKey, function ($query, $searchKey) {
      return $query->where(function ($q) use ($searchKey) {
        $q->where('fname', 'like', '%' . $searchKey . '%')
          ->orWhere('lname', 'like', '%' . $searchKey . '%')
          ->orWhere('username', 'like', '%' . $searchKey . '%')
          ->orWhere('email', 'like', '%' . $searchKey . '%');
      });
    })
      ->orderBy('id', 'desc')
      ->get();

    $fileName = 'all-users.csv';
    $headers = [
      'Content-Type' => 'text/csv; charset=UTF-8',
      'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
    ];

    return response()->streamDownload(function () use ($customers) {
      $out = fopen('php://output', 'w');
      fputcsv($out, ['Name', 'Email', 'Username', 'Phone', 'Sold', 'On Hold', 'In Hand', 'Earnings', 'PDF Files', 'Account Status', 'Email Status']);

      foreach ($customers as $customer) {
        $soldQty = (int) ResaleTicket::where('seller_customer_id', $customer->id)->where('status', 'sold')->sum('quantity');
        $onHoldQty = (int) ResaleTicket::where('seller_customer_id', $customer->id)->where('status', 'reserved')->sum('quantity');
        $inHandQty = (int) ResaleTicket::where('seller_customer_id', $customer->id)
          ->whereNull('parent_id')
          ->where('status', 'listed')
          ->sum('quantity');
        $earnings = $this->getSellerNetEarnings((int) $customer->id);

        $pdfFiles = 0;
        $sellerListings = ResaleTicket::where('seller_customer_id', $customer->id)
          ->whereNull('parent_id')
          ->get(['ticket_pdfs', 'ticket_pdf']);
        foreach ($sellerListings as $listing) {
          if (!empty($listing->ticket_pdfs)) {
            $decoded = json_decode((string) $listing->ticket_pdfs, true);
            if (is_array($decoded)) {
              $pdfFiles += count(array_filter($decoded));
            }
          } elseif (!empty($listing->ticket_pdf)) {
            $pdfFiles += 1;
          }
        }

        fputcsv($out, [
          trim(($customer->fname ?? '') . ' ' . ($customer->lname ?? '')),
          (string) $customer->email,
          (string) ($customer->username ?: '-'),
          (string) ($customer->phone ?: '-'),
          $soldQty,
          $onHoldQty,
          $inHandQty,
          number_format((float) $earnings, 2, '.', ''),
          $pdfFiles,
          ((int) $customer->status === 1) ? 'Active' : 'Deactive',
          !is_null($customer->email_verified_at) ? 'Verified' : 'Not Verified',
        ]);
      }

      fclose($out);
    }, $fileName, $headers);
  }

  public function sellerIndex(Request $request)
  {
    $searchKey = null;
    if ($request->filled('info')) {
      $searchKey = $request['info'];
    }

    $sellerIds = ResaleTicket::query()
      ->whereNotNull('seller_customer_id')
      ->distinct()
      ->pluck('seller_customer_id')
      ->toArray();

    $sellers = Customer::query()
      ->whereIn('id', $sellerIds)
      ->when($searchKey, function ($query, $searchKey) {
        return $query->where('fname', 'like', '%' . $searchKey . '%')
          ->orWhere('lname', 'like', '%' . $searchKey . '%')
          ->orWhere('username', 'like', '%' . $searchKey . '%')
          ->orWhere('email', 'like', '%' . $searchKey . '%');
      })
      ->orderByDesc('id')
      ->paginate(10);

    $stats = [];
    $pageSellerIds = $sellers->pluck('id')->toArray();
    if (!empty($pageSellerIds)) {
      foreach ($pageSellerIds as $sellerId) {
        $soldQty = (int) ResaleTicket::where('seller_customer_id', $sellerId)->where('status', 'sold')->sum('quantity');
        $onHoldQty = (int) ResaleTicket::where('seller_customer_id', $sellerId)->where('status', 'reserved')->sum('quantity');
        $earnings = $this->getSellerNetEarnings($sellerId);

        $inHandQty = (int) ResaleTicket::where('seller_customer_id', $sellerId)
          ->whereNull('parent_id')
          ->where('status', 'listed')
          ->sum('quantity');

        $pdfFiles = 0;
        $sellerListings = ResaleTicket::where('seller_customer_id', $sellerId)->whereNull('parent_id')->get(['ticket_pdfs', 'ticket_pdf']);
        foreach ($sellerListings as $listing) {
          if (!empty($listing->ticket_pdfs)) {
            $decoded = json_decode((string) $listing->ticket_pdfs, true);
            if (is_array($decoded)) {
              $pdfFiles += count(array_filter($decoded));
            }
          } elseif (!empty($listing->ticket_pdf)) {
            $pdfFiles += 1;
          }
        }

        $stats[$sellerId] = [
          'sold_qty' => $soldQty,
          'on_hold_qty' => $onHoldQty,
          'in_hand_qty' => $inHandQty,
          'earnings' => $earnings,
          'pdf_files' => $pdfFiles,
        ];
      }
    }

    return view('backend.end-user.seller.index', compact('sellers', 'stats'));
  }

  public function sellerExport(Request $request)
  {
    $searchKey = null;
    if ($request->filled('info')) {
      $searchKey = $request['info'];
    }

    $sellerIds = ResaleTicket::query()
      ->whereNotNull('seller_customer_id')
      ->distinct()
      ->pluck('seller_customer_id')
      ->toArray();

    $sellers = Customer::query()
      ->whereIn('id', $sellerIds)
      ->when($searchKey, function ($query, $searchKey) {
        return $query->where('fname', 'like', '%' . $searchKey . '%')
          ->orWhere('lname', 'like', '%' . $searchKey . '%')
          ->orWhere('username', 'like', '%' . $searchKey . '%')
          ->orWhere('email', 'like', '%' . $searchKey . '%');
      })
      ->orderByDesc('id')
      ->get();

    $rows = [];
    foreach ($sellers as $seller) {
      $soldQty = (int) ResaleTicket::where('seller_customer_id', $seller->id)->where('status', 'sold')->sum('quantity');
      $onHoldQty = (int) ResaleTicket::where('seller_customer_id', $seller->id)->where('status', 'reserved')->sum('quantity');
      $inHandQty = (int) ResaleTicket::where('seller_customer_id', $seller->id)->whereNull('parent_id')->where('status', 'listed')->sum('quantity');
      $earnings = $this->getSellerNetEarnings($seller->id);

      $rows[] = [
        'name' => trim(($seller->fname ?? '') . ' ' . ($seller->lname ?? '')),
        'username' => (string) $seller->username,
        'email' => (string) $seller->email,
        'phone' => $seller->phone ?: '-',
        'sold' => $soldQty,
        'on_hold' => $onHoldQty,
        'in_hand' => $inHandQty,
        'earnings' => number_format($earnings, 2, '.', ''),
      ];
    }

    $pdf = Pdf::loadView('backend.exports.sellers-pdf', ['rows' => $rows]);
    return $pdf->download('registered-sellers.pdf');
  }

  public function sellerDetails(Request $request, $id)
  {
    $customer = Customer::findOrFail($id);
    $defaultLang = Language::where('is_default', 1)->first();

    $listings = ResaleTicket::where('seller_customer_id', $id)
      ->whereNull('parent_id')
      ->with(['children', 'eventContent'])
      ->orderByDesc('id')
      ->get();

    $eventIds = $listings->pluck('event_id')->filter()->unique()->values()->toArray();
    $eventTransferTypes = [];
    if (!empty($eventIds)) {
      $eventTransferTypes = PlatformEvent::whereIn('id', $eventIds)
        ->pluck('event_ticket_transfer_type', 'id')
        ->toArray();
    }

    $listingStats = [];
    foreach ($listings as $listing) {
      $soldListingQty = (int) $listing->children->where('status', 'sold')->sum('quantity');
      $inHandListingQty = (int) $listing->quantity;
      $transferType = strtolower((string) ($eventTransferTypes[$listing->event_id] ?? 'external'));
      $isInternal = $transferType === 'internal';

      $pdfCount = 0;
      if ($isInternal) {
        if (!empty($listing->ticket_pdfs)) {
          $pdfCount = count(json_decode((string) $listing->ticket_pdfs, true) ?? []);
        } elseif (!empty($listing->ticket_pdf)) {
          $pdfCount = 1;
        }
      }

      $listingStats[$listing->id] = [
        'sold_qty' => $soldListingQty,
        'in_hand_qty' => $inHandListingQty,
        'transfer_type' => $transferType,
        'show_pdf' => $isInternal,
        'pdf_count' => $pdfCount,
      ];
    }

    $soldTickets = ResaleTicket::where('seller_customer_id', $id)
      ->where('status', 'sold')
      ->with(['buyer', 'eventContent'])
      ->orderByDesc('id')
      ->get();
    $soldTicketNetAmounts = $this->getResaleNetAmountsByTicketIds($soldTickets->pluck('id')->toArray());

    $soldQty = (int) ResaleTicket::where('seller_customer_id', $id)->where('status', 'sold')->sum('quantity');
    $onHoldQty = (int) ResaleTicket::where('seller_customer_id', $id)->where('status', 'reserved')->sum('quantity');
    $inHandQty = (int) ResaleTicket::where('seller_customer_id', $id)->whereNull('parent_id')->where('status', 'listed')->sum('quantity');
    $earnings = $this->getSellerNetEarnings($id);

    return view('backend.end-user.seller.details', compact(
      'customer',
      'defaultLang',
      'listings',
      'soldTickets',
      'soldTicketNetAmounts',
      'listingStats',
      'soldQty',
      'onHoldQty',
      'inHandQty',
      'earnings'
    ));
  }



  public function show(Request $request, $id)
  {

    $bookingId = $paymentStatus = null;
    if ($request->filled('booking_id')) {
      $bookingId = $request['booking_id'];
    }

    if ($request->filled('status')) {
      $paymentStatus = $request['status'];
    }

    $bookings = Booking::when($bookingId, function ($query, $bookingId) {
      return $query->where('booking_id', 'like', '%' . $bookingId . '%');
    })
      ->when($paymentStatus, function ($query, $paymentStatus) {
        return $query->where('paymentStatus', '=', $paymentStatus);
      })
      ->where('customer_id', $id)
      ->orderByDesc('id')
      ->paginate(10);

    $customer = Customer::findOrFail($id);
    $resaleBookings = ResaleTicket::where('buyer_customer_id', $id)
      ->when($bookingId, function ($query, $bookingId) {
        return $query->where('booking_id', 'like', '%' . $bookingId . '%');
      })
      ->when($paymentStatus, function ($query, $paymentStatus) {
        if ($paymentStatus === 'pending') {
          return $query->where('status', 'reserved');
        }
        if ($paymentStatus === 'completed') {
          return $query->where('status', 'sold');
        }
        if ($paymentStatus === 'rejected') {
          return $query->where('status', 'cancelled');
        }
        return $query;
      })
      ->with(['seller', 'eventContent'])
      ->orderByDesc('id')
      ->get();

    $resaleSoldTickets = ResaleTicket::where('seller_customer_id', $id)
      ->where('status', 'sold')
      ->with(['buyer', 'eventContent'])
      ->orderByDesc('id')
      ->get();
    $resaleSoldTicketNetAmounts = $this->getResaleNetAmountsByTicketIds($resaleSoldTickets->pluck('id')->toArray());

    $listings = ResaleTicket::where('seller_customer_id', $id)
      ->whereNull('parent_id')
      ->with(['children', 'eventContent'])
      ->orderByDesc('id')
      ->get();

    $eventIds = $listings->pluck('event_id')->filter()->unique()->values()->toArray();
    $eventTransferTypes = [];
    if (!empty($eventIds)) {
      $eventTransferTypes = PlatformEvent::whereIn('id', $eventIds)
        ->pluck('event_ticket_transfer_type', 'id')
        ->toArray();
    }

    $listingStats = [];
    foreach ($listings as $listing) {
      $soldListingQty = (int) $listing->children->where('status', 'sold')->sum('quantity');
      $inHandListingQty = (int) $listing->quantity;
      $transferType = strtolower((string) ($eventTransferTypes[$listing->event_id] ?? 'external'));
      $isInternal = $transferType === 'internal';

      $pdfCount = 0;
      if ($isInternal) {
        if (!empty($listing->ticket_pdfs)) {
          $pdfCount = count(json_decode((string) $listing->ticket_pdfs, true) ?? []);
        } elseif (!empty($listing->ticket_pdf)) {
          $pdfCount = 1;
        }
      }

      $listingStats[$listing->id] = [
        'sold_qty' => $soldListingQty,
        'in_hand_qty' => $inHandListingQty,
        'transfer_type' => $transferType,
        'show_pdf' => $isInternal,
        'pdf_count' => $pdfCount,
      ];
    }

    $soldQty = (int) ResaleTicket::where('seller_customer_id', $id)->where('status', 'sold')->sum('quantity');
    $onHoldQty = (int) ResaleTicket::where('seller_customer_id', $id)->where('status', 'reserved')->sum('quantity');
    $inHandQty = (int) ResaleTicket::where('seller_customer_id', $id)->whereNull('parent_id')->where('status', 'listed')->sum('quantity');
    $earnings = $this->getSellerNetEarnings($id);

    $information['bookings'] = $bookings;
    $information['resaleBookings'] = $resaleBookings;
    $information['resaleSoldTickets'] = $resaleSoldTickets;
    $information['resaleSoldTicketNetAmounts'] = $resaleSoldTicketNetAmounts;
    $information['listings'] = $listings;
    $information['listingStats'] = $listingStats;
    $information['soldQty'] = $soldQty;
    $information['onHoldQty'] = $onHoldQty;
    $information['inHandQty'] = $inHandQty;
    $information['earnings'] = $earnings;
    $information['customer'] = $customer;

    return view('backend.end-user.customer.details', $information);
  }

  public function create()
  {
    return view('backend.end-user.customer.create');
  }

  private function getSellerNetEarnings(int $sellerId): float
  {
    return (float) Transaction::query()
      ->where('transcation_type', 6)
      ->where('customer_id', $sellerId)
      ->where('payment_status', 1)
      ->selectRaw('COALESCE(SUM(grand_total - commission), 0) as total')
      ->value('total');
  }

  private function getResaleNetAmountsByTicketIds(array $ticketIds): array
  {
    if (empty($ticketIds)) {
      return [];
    }

    return Transaction::query()
      ->where('transcation_type', 6)
      ->whereIn('booking_id', $ticketIds)
      ->where('payment_status', 1)
      ->selectRaw('booking_id, (grand_total - commission) as net_amount')
      ->pluck('net_amount', 'booking_id')
      ->map(function ($value) {
        return (float) $value;
      })
      ->toArray();
  }

  public function store(Request $request)
  {
    $rules = [
      'fname' => 'required',
      'lname' => 'required',
      'email' => [
        'required',
        'email',
        Rule::unique('customers', 'username')
      ],
      'username' => [
        'required',
        'alpha_dash',
        "not_in:$this->admin_user_name",
        Rule::unique('customers', 'username')
      ],
      'password' => 'required|confirmed|min:6'
    ];

    $validator = Validator::make($request->all(), $rules);

    if ($validator->fails()) {
      return Response::json([
        'errors' => $validator->getMessageBag()
      ], 400);
    }


    $in = $request->all();

    $file = $request->file('photo');
    if ($file) {
      $extension = $file->getClientOriginalExtension();
      $directory = public_path('assets/admin/img/customer-profile/');
      $fileName = uniqid() . '.' . $extension;
      @mkdir($directory, 0775, true);
      $file->move($directory, $fileName);
      $in['photo'] = $fileName;
    }
    $in['status'] = 1;
    $in['email_verified_at'] = now();
    $in['password'] = Hash::make($request->password);

    Customer::create($in);
    Session::flash('success', 'Added Successfully');

    return Response::json(['status' => 'success'], 200);
  }

  public function updateAccountStatus(Request $request, $id)
  {

    $user = Customer::find($id);
    if ($request->account_status == 1) {
      $user->update(['status' => 1]);
    } else {
      $user->update(['status' => 0]);
    }
    Session::flash('success', 'Updated Successfully');

    return redirect()->back();
  }

  /**
   * Point 15: manually reinstate a BANNED user.
   * Account is reactivated and confidence is set to HIGH RISK (internal LOW).
   * Does not auto-recalculate (would re-ban from sticky/score); count is incremented.
   */
  public function reinstateBanned($id)
  {
    $user = Customer::findOrFail($id);
    if ($user->confidence_level !== Customer::CONFIDENCE_BANNED) {
      Session::flash('error', 'This user is not in the BANNED confidence category.');
      return redirect()->back();
    }

    $user->status = 1;
    $user->confidence_level = Customer::CONFIDENCE_LOW; // HIGH RISK in UI
    $user->confidence_updated_at = now();
    $user->manual_reinstatement_count = (int) $user->manual_reinstatement_count + 1;
    // Snapshot blame count so cron does not re-BAN from the same pre-reinstate
    // score, but a NEW litigation fault after reinstate can still ban again.
    $user->litigations_at_reinstatement = (int) $user->litigations_responsible_count;
    $user->save();

    // Apply LOW-tier listing rules without re-running score→BAN sticky.
    app(\App\Services\Resale\ConfidenceLevelService::class)->applyLevelActions($user->fresh());

    Session::flash('success', 'User reinstated. Confidence set to HIGH RISK. Manual reinstatements: ' . $user->manual_reinstatement_count);
    return redirect()->back();
  }

  public function updateEmailStatus(Request $request, $id)
  {
    $user = Customer::find($id);
    if ($request->email_status == 1) {
      $user->update(['email_verified_at' => now()]);
    } else {
      $user->update(['email_verified_at' => null]);
    }
    Session::flash('success', 'Updated Successfully');

    return redirect()->back();
  }
  public function changePassword($id)
  {
    $userInfo = Customer::findOrFail($id);

    return view('backend.end-user.customer.change-password', compact('userInfo'));
  }
  public function updatePassword(Request $request, $id)
  {
    $rules = [
      'new_password' => 'required|confirmed',
      'new_password_confirmation' => 'required'
    ];

    $messages = [
      'new_password.confirmed' => 'Password confirmation does not match.',
      'new_password_confirmation.required' => 'The confirm new password field is required.'
    ];

    $validator = Validator::make($request->all(), $rules, $messages);

    if ($validator->fails()) {
      return Response::json([
        'errors' => $validator->getMessageBag()->toArray()
      ], 400);
    }

    $user = Customer::find($id);

    $user->update([
      'password' => Hash::make($request->new_password)
    ]);

    Session::flash('success', 'Updated Successfully');

    return Response::json(['status' => 'success'], 200);
  }

  public function edit($id)
  {
    $customer = Customer::findOrFail($id);
    return view('backend.end-user.customer.edit', compact('customer'));
  }

  //update
  public function update(Request $request, $id, Customer $customer)
  {
    $rules = [
      'fname' => 'required',
      'lname' => 'required',
      'email' => [
        'required',
        'email',
        Rule::unique('customers', 'email')->ignore($id)
      ],
      'username' => [
        'required',
        'alpha_dash',
        "not_in:$this->admin_user_name",
        Rule::unique('customers', 'username')->ignore($id)
      ]

    ];

    $validator = Validator::make($request->all(), $rules);

    if ($validator->fails()) {
      return Response::json([
        'errors' => $validator->getMessageBag()
      ], 400);
    }


    $in = $request->all();
    $customer  = Customer::where('id', $id)->first();

    $file = $request->file('photo');
    if ($file) {
      $extension = $file->getClientOriginalExtension();
      $directory = public_path('assets/admin/img/customer-profile/');
      $fileName = uniqid() . '.' . $extension;
      @mkdir($directory, 0775, true);
      $file->move($directory, $fileName);
      $in['photo'] = $fileName;
    }
    @unlink(public_path('assets/admin/img/customer-profile/') . $customer->photo);

    $customer->update($in);
    Session::flash('success', 'Updated Successfully');

    return Response::json(['status' => 'success'], 200);
  }

  public function destroy($id)
  {
    $customer = Customer::find($id);
    $bookings = $customer->bookings()->get();
    foreach ($bookings as $booking) {
      $booking->delete();
    }
    $order_items = $customer->order_items()->get();
    foreach ($order_items as $order_item) {
      $order_item->delete();
    }
    $wishlists = $customer->wishlists()->get();
    foreach ($wishlists as $wishlists) {
      $wishlists->delete();
    }
    $product_reviews = $customer->product_reviews()->get();
    foreach ($product_reviews as $product_review) {
      $product_review->delete();
    }
    $product_reviews = $customer->product_reviews()->get();
    foreach ($product_reviews as $product_review) {
      $product_review->delete();
    }
    $support_tickets = $customer->support_tickets()->get();
    foreach ($support_tickets as $support_ticket) {
      $support_ticket->delete();
    }
    $wishlists = Wishlist::where('customer_id', $customer->id)->get();
    foreach ($wishlists as $key => $wishlist) {
      $wishlist->delete();
    }
    @unlink(public_path('assets/admin/img/customer-profile/') . $customer->photo);
    $customer->delete();

    return redirect()->back()->with('success', 'Deleted Successfully');
  }

  public function bulkDestroy(Request $request)
  {
    $ids = $request->ids;

    foreach ($ids as $id) {
      $customer = Customer::find($id);
      $bookings = $customer->bookings()->get();
      foreach ($bookings as $booking) {
        $booking->delete();
      }
      $order_items = $customer->order_items()->get();
      foreach ($order_items as $order_item) {
        $order_item->delete();
      }
      $product_reviews = $customer->product_reviews()->get();
      foreach ($product_reviews as $product_review) {
        $product_review->delete();
      }

      $support_tickets = $customer->support_tickets()->get();
      foreach ($support_tickets as $support_ticket) {
        $support_ticket->delete();
      }
      $wishlists = Wishlist::where('customer_id', $customer->id)->get();
      foreach ($wishlists as $key => $wishlist) {
        $wishlist->delete();
      }
      @unlink(public_path('assets/admin/img/customer-profile/') . $customer->photo);
      $customer->delete();
    }

    Session::flash('success', 'Deleted Successfully');

    return Response::json(['status' => 'success'], 200);
  }

  //secrtet login
  public function secret_login($id)
  {
    Session::put('secret_login', true);
    $user = Customer::where('id', $id)->first();
    Auth::guard('customer')->login($user);
    return redirect()->route('customer.dashboard');
  }
}

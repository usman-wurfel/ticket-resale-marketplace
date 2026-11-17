<?php

namespace App\Http\Controllers\FrontEnd\Event;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Event;
use App\Models\Event\Booking;
use App\Models\Event\EventContent;
use App\Models\Event\ResaleTicket;
use App\Models\Event\Ticket;
use App\Models\Event\EventZone;
use App\Models\Transaction;
use App\Services\Resale\ConfidenceLevelService;
use App\Services\Resale\FifoMatcherService;
use App\Services\Resale\ResalePurchaseService;
use App\Services\Resale\TransferDeadlineService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TicketResaleController extends Controller
{
    public function create($eventId)
    {
        $event = EventContent::where('event_id', $eventId)->firstOrFail();
        if ($this->isEventFinalized((int) $eventId)) {
            return redirect()->route('customer.tickets.sold')
                ->with('error', 'This event has already finalized. You cannot publish resale tickets.');
        }

        // Point 2 clarification: only HIGH RISK (internal LOW) sellers are limited
        // to one active listing. Others may have several listings at once, but each
        // listing is always a single ticket (no batch quantity).
        $customer = Auth::guard('customer')->user();
        if ($customer && $customer->hasStrictResaleSellerRules()) {
            $activeCount = ResaleTicket::where('seller_customer_id', $customer->id)
                ->whereIn('status', [ResaleTicket::STATUS_LISTED, ResaleTicket::STATUS_RESERVED, ResaleTicket::STATUS_PAID])
                ->count();
            if ($activeCount >= 1) {
                return redirect()->route('customer.tickets.sold')
                    ->with('error', 'As a HIGH RISK seller you can only have one active listing at a time. Cancel or complete your current listing first.');
            }
        }

        $eventModel = Event::find((int) $eventId);
        $eventTransferType = $eventModel?->event_ticket_transfer_type ?? 'external';
        $mustUploadPdf = $eventModel
            ? app(\App\Services\Resale\TransferService::class)->sellerMustUploadPdfAtListing($eventModel)
            : false;

        $zoneOptions = $this->zoneOptionsForEvent($eventModel);
        $allowCustomZone = (bool) ($eventModel?->allow_custom_zone);
        $entranceDates = $eventModel ? $eventModel->entranceDates() : [];
        $isMultiDay = count($entranceDates) > 1;
        $commissionPercent = (float) (DB::table('basic_settings')->value('commission') ?? 0);
        $externalPlatformName = (($eventTransferType ?? 'external') === 'external')
            ? ($eventModel?->external_platform_name)
            : null;

        return view('frontend.customer.dashboard.resale.create', compact(
            'event',
            'eventTransferType',
            'mustUploadPdf',
            'zoneOptions',
            'allowCustomZone',
            'entranceDates',
            'isMultiDay',
            'commissionPercent',
            'externalPlatformName'
        ));
    }

    public function store(Request $request, ConfidenceLevelService $confidenceLevelService)
    {
        if ($this->isEventFinalized((int) $request->event_id)) {
            return redirect()->route('customer.tickets.sold')
                ->with('error', 'This event has already finalized. You cannot publish resale tickets.');
        }
        $event = Event::find((int) $request->event_id);
        $resolvedZone = $this->resolveZoneFromRequest($request, $event);
        $entranceDates = $event ? $event->entranceDates() : [];
        $isMultiDay = count($entranceDates) > 1;

        $validationRules = [
            'event_id' => 'required|integer',
            'price' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
            'entrance_date' => $isMultiDay
                ? 'required|date|in:' . implode(',', $entranceDates)
                : 'nullable|date',
        ];
        if ($event && app(\App\Services\Resale\TransferService::class)->sellerMustUploadPdfAtListing($event)) {
            $validationRules['ticket_pdfs'] = 'required|array|max:1|size:1';
            $validationRules['ticket_pdfs.*'] = 'required|file|mimes:pdf|max:20480';
        } else {
            $validationRules['ticket_pdfs'] = 'nullable|array|max:1';
            $validationRules['ticket_pdfs.*'] = 'nullable|file|mimes:pdf|max:20480';
        }
        $request->validate($validationRules);
        $this->assertZoneAllowed($resolvedZone, $event);

        $ticketPdfs = $this->storeResalePdfs($request);

        /** @var Customer|null $customer */
        $customer = Auth::guard('customer')->user();
        if ($customer && $customer->isBanned()) {
            return redirect()->route('customer.tickets.sold')
                ->with('error', 'Your account is suspended and cannot list tickets.');
        }

        // Point 2: one listing = one ticket (quantity always 1). Multiple listings
        // may be active at once, except HIGH RISK (LOW) sellers who stay on the
        // single-active-listing Confidence Level rule.
        if ($customer && $customer->hasStrictResaleSellerRules()) {
            $activeCount = ResaleTicket::where('seller_customer_id', $customer->id)
                ->whereIn('status', [ResaleTicket::STATUS_LISTED, ResaleTicket::STATUS_RESERVED, ResaleTicket::STATUS_PAID])
                ->count();
            if ($activeCount >= 1) {
                return redirect()->route('customer.tickets.sold')
                    ->with('error', 'As a HIGH RISK seller you can only have one active listing at a time. Cancel or complete your current listing first.');
            }
        }

        $entranceDate = $request->input('entrance_date');
        if (!$entranceDate && count($entranceDates) === 1) {
            $entranceDate = $entranceDates[0];
        }

        ResaleTicket::create([
            'event_id' => (int) $request->event_id,
            'zone' => $resolvedZone,
            'entrance_date' => $entranceDate,
            'seller_customer_id' => Auth::guard('customer')->id(),
            'price' => $request->price,
            'quantity' => 1,
            'original_quantity' => 1,
            'status' => ResaleTicket::STATUS_LISTED,
            'deprioritized' => $customer ? $customer->isDeprioritized() : false,
            'notes' => $request->notes,
            'ticket_pdfs' => !empty($ticketPdfs) ? json_encode($ticketPdfs) : null,
            'ticket_pdf' => null,
        ]);

        if ($customer) {
            $customer->refresh();
            $confidenceLevelService->applyLevelActions($customer);
        }

        return redirect()->route('customer.tickets.sold')->with('success', 'Ticket listed for sale successfully.');
    }

    public function edit($id)
    {
        $ticket = ResaleTicket::findOrFail($id);
        if ($this->isEventFinalized((int) $ticket->event_id)) {
            return redirect()->route('customer.tickets.sold')
                ->with('error', 'This event has already finalized. You cannot edit resale tickets.');
        }
        $customerId = Auth::guard('customer')->id();
        $eventModel = Event::find((int) $ticket->event_id);
        $eventTransferType = $eventModel?->event_ticket_transfer_type ?? 'external';
        $mustUploadPdf = $eventModel
            ? app(\App\Services\Resale\TransferService::class)->sellerMustUploadPdfAtListing($eventModel)
            : false;

        if ((int) $ticket->seller_customer_id !== (int) $customerId) {
            return redirect()->route('customer.tickets.sold')->with('error', 'You are not allowed to edit this ticket.');
        }

        if ($ticket->status !== 'listed') {
            return redirect()->route('customer.tickets.sold')->with('error', 'Only listed tickets can be edited.');
        }

        $event = EventContent::where('event_id', $ticket->event_id)->firstOrFail();

        $zoneOptions = $this->zoneOptionsForEvent($eventModel);
        $allowCustomZone = (bool) ($eventModel?->allow_custom_zone);
        $entranceDates = $eventModel ? $eventModel->entranceDates() : [];
        $isMultiDay = count($entranceDates) > 1;
        $commissionPercent = (float) (DB::table('basic_settings')->value('commission') ?? 0);
        $externalPlatformName = (($eventTransferType ?? 'external') === 'external')
            ? ($eventModel?->external_platform_name)
            : null;

        return view('frontend.customer.dashboard.resale.create', compact(
            'event',
            'ticket',
            'eventTransferType',
            'mustUploadPdf',
            'zoneOptions',
            'allowCustomZone',
            'entranceDates',
            'isMultiDay',
            'commissionPercent',
            'externalPlatformName'
        ));
    }

    public function update(Request $request, $id)
    {
        $ticket = ResaleTicket::findOrFail($id);
        if ($this->isEventFinalized((int) $ticket->event_id)) {
            return redirect()->route('customer.tickets.sold')
                ->with('error', 'This event has already finalized. You cannot edit resale tickets.');
        }
        $customerId = Auth::guard('customer')->id();
        $event = Event::find((int) $ticket->event_id);

        if ((int) $ticket->seller_customer_id !== (int) $customerId) {
            return redirect()->route('customer.tickets.sold')->with('error', 'You are not allowed to update this ticket.');
        }

        if ($ticket->status !== 'listed') {
            return redirect()->route('customer.tickets.sold')->with('error', 'Only listed tickets can be edited.');
        }

        $resolvedZone = $this->resolveZoneFromRequest($request, $event);
        $entranceDates = $event ? $event->entranceDates() : [];
        $isMultiDay = count($entranceDates) > 1;

        $validationRules = [
            'price' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
            'entrance_date' => $isMultiDay
                ? 'required|date|in:' . implode(',', $entranceDates)
                : 'nullable|date',
        ];
        $mustUpload = $event && app(\App\Services\Resale\TransferService::class)->sellerMustUploadPdfAtListing($event);
        $hasExistingPdfs = count($this->getStoredPdfList($ticket)) > 0;
        if ($mustUpload) {
            $rule = $hasExistingPdfs ? 'nullable' : 'required';
            $validationRules['ticket_pdfs'] = $rule . '|array|max:1|size:1';
            $validationRules['ticket_pdfs.*'] = $rule . '|file|mimes:pdf|max:20480';
        } else {
            $validationRules['ticket_pdfs'] = 'nullable|array|max:1';
            $validationRules['ticket_pdfs.*'] = 'nullable|file|mimes:pdf|max:20480';
        }
        $request->validate($validationRules);
        $this->assertZoneAllowed($resolvedZone, $event);

        $ticketPdfs = $this->getStoredPdfList($ticket);
        if ($request->hasFile('ticket_pdfs')) {
            $this->deletePdfFiles($ticketPdfs);
            $ticketPdfs = $this->storeResalePdfs($request);
        }

        $entranceDate = $request->input('entrance_date');
        if (!$entranceDate && count($entranceDates) === 1) {
            $entranceDate = $entranceDates[0];
        }

        $ticket->update([
            'price' => $request->price,
            'quantity' => 1,
            'original_quantity' => 1,
            'zone' => $resolvedZone,
            'entrance_date' => $entranceDate,
            'notes' => $request->notes,
            'ticket_pdfs' => !empty($ticketPdfs) ? json_encode($ticketPdfs) : null,
            'ticket_pdf' => null,
        ]);

        return redirect()->route('customer.tickets.sold')->with('success', 'Ticket listing updated successfully.');
    }

    public function sold()
    {
        $this->expireStaleResaleReservations();

        $tickets = ResaleTicket::where('seller_customer_id', Auth::guard('customer')->id())
            ->whereNull('parent_id')
            ->with(['eventContent', 'children'])
            ->latest()
            ->paginate(20);

        return view('frontend.customer.dashboard.resale.sold', compact('tickets'));
    }

    /**
     * Dedicated page listing the seller's tickets that need (or recently
     * went through) a transfer. This is where the seller actually delivers
     * the ticket to the buyer: upload a PDF for internal transfers, or
     * confirm the external transfer.
     *
     * The page is also used to review transferred sales (awaiting payment
     * release) and failed transfers (missed deadline).
     */
    public function transfers()
    {
        $this->expireStaleResaleReservations();

        $customerId = Auth::guard('customer')->id();

        $base = ResaleTicket::query()
            ->where('seller_customer_id', $customerId)
            ->with(['eventContent', 'event'])
            ->whereIn('status', [
                ResaleTicket::STATUS_PAID,
                ResaleTicket::STATUS_TRANSFERRED,
                ResaleTicket::STATUS_SOLD,
            ]);

        $pending = (clone $base)
            ->where('transfer_status', ResaleTicket::TRANSFER_PENDING)
            ->orderBy('transfer_deadline', 'asc')
            ->get();

        $done = (clone $base)
            ->where('transfer_status', ResaleTicket::TRANSFER_DONE)
            ->orderByDesc('transferred_at')
            ->get();

        $failed = ResaleTicket::where('seller_customer_id', $customerId)
            ->where('transfer_status', ResaleTicket::TRANSFER_MISSED)
            ->with(['eventContent', 'event'])
            ->orderByDesc('updated_at')
            ->limit(20)
            ->get();

        return view('frontend.customer.dashboard.resale.transfers', [
            'pending' => $pending,
            'done' => $done,
            'failed' => $failed,
        ]);
    }

    public function bought()
    {
        $customerId = Auth::guard('customer')->id();
        $this->expireStaleResaleReservations();
        $this->expireStalePlatformReservations($customerId);
        $this->ensureResaleBookingIds();

        $perPage = 20;
        $currentPage = LengthAwarePaginator::resolveCurrentPage();

        $bookings = Booking::where('customer_id', $customerId)->orderByDesc('created_at')->get();
        $resales = ResaleTicket::where('buyer_customer_id', $customerId)
            ->with('eventContent')
            ->orderByDesc('created_at')
            ->get();

        $merged = collect();
        foreach ($bookings as $b) {
            $merged->push([
                'kind' => 'platform',
                'sort_at' => $b->created_at,
                'booking' => $b,
                'resale' => null,
            ]);
        }
        foreach ($resales as $r) {
            $merged->push([
                'kind' => 'resale',
                'sort_at' => $r->created_at,
                'booking' => null,
                'resale' => $r,
            ]);
        }

        $merged = $merged->sortByDesc(function ($row) {
            return $row['sort_at']->timestamp;
        })->values();

        $total = $merged->count();
        $items = $merged->slice(($currentPage - 1) * $perPage, $perPage)->values();

        $purchases = new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $currentPage,
            ['path' => request()->url(), 'pageName' => 'page']
        );
        $purchases->withQueryString();

        return view('frontend.customer.dashboard.resale.bought', compact('purchases'));
    }

    public function boughtResaleDetail($id)
    {
        $ticket = ResaleTicket::with(['eventContent'])->findOrFail($id);
        if ((int) $ticket->buyer_customer_id !== (int) Auth::guard('customer')->id()) {
            abort(403);
        }

        return view('frontend.customer.dashboard.resale.bought-detail', compact('ticket'));
    }

    /**
     * FIFO reservation entry point.
     *
     * Accepts either:
     *   - a ResaleTicket id (legacy behaviour: tries that specific listing,
     *     but only reserves it if FIFO would have picked it)
     *   - event_id + zone + price in the request body (new FIFO flow).
     *
     * Creates a 10-minute reservation for the buyer and then redirects to
     * the pay-now page where the buyer confirms payment.
     */
    public function reserve(Request $request, $id = null, ResalePurchaseService $purchase = null)
    {
        $purchase = $purchase ?: app(ResalePurchaseService::class);
        $this->expireStaleResaleReservations();
        $this->ensureResaleBookingIds();

        /** @var Customer|null $buyer */
        $buyer = Auth::guard('customer')->user();
        if (!$buyer) {
            return redirect()->route('customer.login');
        }

        $eventId = (int) ($request->input('event_id') ?: optional(ResaleTicket::find($id))->event_id);
        $zone = $request->input('zone');
        $price = (float) $request->input('price', 0);
        $entranceDate = $request->input('entrance_date');
        // Always one ticket per purchase.
        $requestedQty = 1;

        if ($id) {
            $legacy = ResaleTicket::find($id);
            if ($legacy) {
                $eventId = $eventId ?: (int) $legacy->event_id;
                $zone = $zone ?: $legacy->zone;
                if (!$price) {
                    $price = (float) $legacy->price;
                }
                if (!$entranceDate && $legacy->entrance_date) {
                    $entranceDate = Carbon::parse($legacy->entrance_date)->format('Y-m-d');
                }
            }
        }

        $event = Event::find($eventId);
        if (!$event) {
            return back()->with('error', 'Event not found.');
        }

        if ($event->isMultiDay() && empty($entranceDate)) {
            return back()->with('error', __('Please select the entrance day for this ticket.'));
        }
        if ($event->isMultiDay() && !empty($entranceDate)) {
            $allowedDates = $event->entranceDates();
            if (!in_array($entranceDate, $allowedDates, true)) {
                return back()->with('error', __('Please select a valid entrance day for this event.'));
            }
        }

        try {
            $ticket = $purchase->reserveForBuyer(
                $buyer,
                $event,
                $zone ?: null,
                $price,
                [],
                $requestedQty,
                $entranceDate ?: null
            );
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('customer.tickets.pay', $ticket->id);
    }

    /**
     * Render the pay-now page: buyer has 10 minutes to confirm payment
     * (no external payment gateway is called at this stage).
     */
    public function payForm($id, ResalePurchaseService $purchase)
    {
        $ticket = ResaleTicket::with(['eventContent', 'event'])->findOrFail($id);
        /** @var Customer|null $buyer */
        $buyer = Auth::guard('customer')->user();
        if (!$buyer || (int) $ticket->buyer_customer_id !== (int) $buyer->id) {
            abort(403);
        }

        if ($ticket->status !== ResaleTicket::STATUS_RESERVED) {
            return redirect()->route('customer.tickets.bought')
                ->with('info', 'This reservation is no longer active.');
        }

        $quote = $purchase->quote($buyer, $ticket);
        $eventModel = $ticket->event;
        $eventTransferType = $eventModel?->event_ticket_transfer_type ?? 'external';
        $externalPlatformName = ($eventTransferType === 'external')
            ? ($eventModel?->external_platform_name)
            : null;

        return view('frontend.customer.dashboard.resale.pay', compact(
            'ticket',
            'quote',
            'eventTransferType',
            'externalPlatformName'
        ));
    }

    /**
     * Confirm payment within the 10-minute window.
     */
    public function payConfirm($id, Request $request, ResalePurchaseService $purchase)
    {
        $ticket = ResaleTicket::with('event')->findOrFail($id);
        /** @var Customer|null $buyer */
        $buyer = Auth::guard('customer')->user();
        if (!$buyer || (int) $ticket->buyer_customer_id !== (int) $buyer->id) {
            abort(403);
        }

        $eventModel = $ticket->event;
        $isExternal = ($eventModel?->event_ticket_transfer_type ?? 'external') === 'external';
        if ($isExternal) {
            $request->merge([
                'buyer_external_account' => trim((string) $request->input('buyer_external_account', '')),
            ]);
            $request->validate([
                'buyer_external_account' => 'required|string|max:255',
            ], [
                'buyer_external_account.required' => __('Please enter your account on the external platform so the seller can transfer the ticket to you.'),
            ]);
            $ticket->buyer_external_account = (string) $request->input('buyer_external_account');
            $ticket->save();
        }

        try {
            $ticket = $purchase->pay($buyer, $ticket);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        return redirect()->route('customer.tickets.bought')
            ->with('success', 'Payment confirmed. The seller has been notified to transfer the ticket.');
    }

    public function complete($id)
    {
        $ticket = ResaleTicket::findOrFail($id);
        $customerId = Auth::guard('customer')->id();

        if ((int) $ticket->seller_customer_id !== (int) $customerId) {
            return back()->with('error', 'You are not allowed to mark this ticket as sold.');
        }

        if (!in_array($ticket->status, [ResaleTicket::STATUS_RESERVED, ResaleTicket::STATUS_PAID], true)) {
            return back()->with('error', 'This ticket is not in a completable state.');
        }

        $ticket->update([
            'status' => ResaleTicket::STATUS_SOLD,
            'reserved_until' => null,
        ]);

        $alreadyCreated = Transaction::where('booking_id', $ticket->id)
            ->where('transcation_type', 6)
            ->exists();

        if (!$alreadyCreated) {
            $grossTotal = ((float) $ticket->price * (int) $ticket->quantity);
            $commissionPercent = (float) (DB::table('basic_settings')->value('commission') ?? 0);
            if ($commissionPercent < 0) {
                $commissionPercent = 0;
            }
            $commissionAmount = round(($grossTotal * $commissionPercent) / 100, 2);

            Transaction::create([
                'transcation_id' => time() . rand(100, 999),
                'booking_id' => $ticket->id,
                'transcation_type' => 6, // resale sold after seller confirmation
                'customer_id' => $ticket->seller_customer_id,
                'organizer_id' => null,
                'payment_status' => 1,
                'payment_method' => 'pay_at_event',
                'grand_total' => $grossTotal,
                'pre_balance' => 0,
                'after_balance' => 0,
                'commission' => $commissionAmount,
                'tax' => 0,
                'gateway_type' => 'offline',
                'currency_symbol' => null,
                'currency_symbol_position' => null,
            ]);
        }

        return back()->with('success', 'Ticket marked as successfully sold.');
    }

    public function cancelReservation(Request $request, $id)
    {
        $ticket = ResaleTicket::findOrFail($id);

        if ($ticket->buyer_customer_id != Auth::guard('customer')->id()) {
            return back()->with('error', 'Unauthorized.');
        }

        if (in_array($ticket->status, ['sold', 'cancelled', 'paid', 'transferred'])) {
            return back()->with('error', 'This ticket cannot be modified.');
        }

        $eventId = (int) $ticket->event_id;

        if ($ticket->parent_id) {
            $parent = ResaleTicket::find($ticket->parent_id);
            if ($parent) {
                $parent->update([
                    'quantity' => $parent->quantity + $ticket->quantity,
                    'status' => 'listed'
                ]);
                $ticket->delete();
            }
        } else {
            $ticket->update([
                'buyer_customer_id' => null,
                'buyer_external_account' => null,
                'status' => 'listed',
                'reserved_at' => null,
                'reserved_until' => null,
                'payment_deadline' => null,
            ]);
        }

        // If the cancel came from the pay page, bounce the buyer back to the
        // event page so they can pick another listing. Otherwise go to Bought.
        if ($request->input('back') === 'event' || str_contains((string) $request->headers->get('referer'), '/tickets/pay/')) {
            $content = EventContent::where('event_id', $eventId)->first();
            if ($content && !empty($content->slug)) {
                return redirect()
                    ->route('event.details', ['slug' => $content->slug, 'id' => $eventId])
                    ->with('info', __('Reservation cancelled. You can choose another ticket.'));
            }
        }

        return redirect()->route('customer.tickets.bought')
            ->with('success', 'Reservation cancelled successfully.');
    }

    /**
     * Auto-cancel only stale resale reservations (no seller action in 15 minutes)
     * and restore quantity back to listing.
     */
    private function expireStaleResaleReservations(): void
    {
        $expiredIds = ResaleTicket::query()
            ->where('status', 'reserved')
            ->whereNotNull('reserved_until')
            ->where('reserved_until', '<', now())
            ->pluck('id');

        foreach ($expiredIds as $id) {
            DB::transaction(function () use ($id) {
                $ticket = ResaleTicket::where('id', $id)->lockForUpdate()->first();
                if (
                    !$ticket ||
                    $ticket->status !== 'reserved' ||
                    is_null($ticket->reserved_until) ||
                    now()->lt($ticket->reserved_until)
                ) {
                    return;
                }

                if (!empty($ticket->parent_id)) {
                    $parent = ResaleTicket::where('id', $ticket->parent_id)->lockForUpdate()->first();

                    if ($parent) {
                        $parent->update([
                            'quantity' => $parent->quantity + $ticket->quantity,
                            'status' => 'listed',
                        ]);

                        $ticket->delete();
                        return;
                    }
                }

                $ticket->update([
                    'buyer_customer_id' => null,
                    'buyer_external_account' => null,
                    'status' => 'listed',
                    'reserved_until' => null,
                ]);
            });
        }
    }

    /**
     * Auto-expire only stale platform reservations for current customer,
     * then restore ticket inventory (same as reject flow).
     */
    private function expireStalePlatformReservations(int $customerId): void
    {
        $expiredIds = Booking::query()
            ->where('customer_id', $customerId)
            ->whereNotNull('reserved_until')
            ->where('reserved_until', '<', now())
            ->where(function ($q) {
                $q->where('paymentStatus', 'reserved')
                    ->orWhere('paymentStatus', 'pending');
            })
            ->pluck('id');

        foreach ($expiredIds as $id) {
            DB::transaction(function () use ($id) {
                $booking = Booking::where('id', $id)->lockForUpdate()->first();
                if (
                    !$booking ||
                    is_null($booking->reserved_until) ||
                    now()->lt($booking->reserved_until)
                ) {
                    return;
                }

                $ps = strtolower((string) $booking->paymentStatus);
                if (!in_array($ps, ['reserved', 'pending'], true)) {
                    return;
                }

                $this->restoreInventoryForExpiredBooking($booking);

                $booking->update([
                    'paymentStatus' => 'rejected',
                    'reserved_until' => null,
                ]);
            });
        }
    }

    private function restoreInventoryForExpiredBooking(Booking $booking): void
    {
        $event = Event::where('id', $booking->event_id)->first();
        if (!$event) {
            return;
        }

        if ($event->event_type == 'online') {
            $ticket = Ticket::where('event_id', $event->id)->first();
            if ($ticket && $ticket->ticket_available_type == 'limited') {
                $ticket->ticket_available = $ticket->ticket_available + $booking->quantity;
                $ticket->save();
            }
            return;
        }

        $variations = json_decode($booking->variation, true);
        if (!empty($variations) && is_array($variations)) {
            foreach ($variations as $variation) {
                if (empty($variation['ticket_id'])) {
                    continue;
                }
                $ticket = Ticket::where('id', $variation['ticket_id'])->first();
                if (!$ticket) {
                    continue;
                }

                if ($ticket->pricing_type == 'variation') {
                    $ticketVariations = json_decode($ticket->variations, true);
                    if (!is_array($ticketVariations)) {
                        continue;
                    }

                    $updated = [];
                    foreach ($ticketVariations as $tv) {
                        if (($tv['name'] ?? null) == ($variation['name'] ?? null)) {
                            $available = $tv['ticket_available'] ?? 0;
                            if (($tv['ticket_available_type'] ?? null) == 'limited') {
                                $available = intval($available) + intval($variation['qty'] ?? 0);
                            }
                            $tv['ticket_available'] = $available;
                        }
                        $updated[] = $tv;
                    }
                    $ticket->variations = json_encode($updated);
                    $ticket->save();
                } else {
                    if ($ticket->ticket_available_type == 'limited') {
                        $ticket->ticket_available = $ticket->ticket_available + intval($variation['qty'] ?? 0);
                        $ticket->save();
                    }
                }
            }
            return;
        }

        $ticket = Ticket::where('event_id', $event->id)->first();
        if ($ticket && $ticket->ticket_available_type == 'limited') {
            $ticket->ticket_available = $ticket->ticket_available + $booking->quantity;
            $ticket->save();
        }
    }

    private function ensureResaleBookingIds(): void
    {
        $missingIds = ResaleTicket::query()
            ->whereNotNull('buyer_customer_id')
            ->where(function ($q) {
                $q->whereNull('booking_id')->orWhere('booking_id', '');
            })
            ->pluck('id');

        foreach ($missingIds as $id) {
            ResaleTicket::where('id', $id)->update(['booking_id' => uniqid()]);
        }
    }

    private function storeResalePdfs(Request $request): array
    {
        $uploaded = [];

        if (!$request->hasFile('ticket_pdfs')) {
            return $uploaded;
        }

        $directory = public_path('assets/admin/file/resale-tickets/');
        @mkdir($directory, 0775, true);

        foreach ($request->file('ticket_pdfs') as $file) {
            if (!$file) {
                continue;
            }
            $filename = 'resale-ticket-' . uniqid() . '.pdf';
            $file->move($directory, $filename);
            $uploaded[] = $filename;
        }

        return $uploaded;
    }

    private function getStoredPdfList(ResaleTicket $ticket): array
    {
        $pdfs = [];

        if (!empty($ticket->ticket_pdfs)) {
            $decoded = json_decode((string) $ticket->ticket_pdfs, true);
            if (is_array($decoded)) {
                foreach ($decoded as $file) {
                    if (!empty($file)) {
                        $pdfs[] = (string) $file;
                    }
                }
            }
        }

        if (!empty($ticket->ticket_pdf)) {
            $pdfs[] = (string) $ticket->ticket_pdf;
        }

        return array_values(array_unique($pdfs));
    }

    private function deletePdfFiles(array $files): void
    {
        foreach ($files as $file) {
            @unlink(public_path('assets/admin/file/resale-tickets/') . $file);
        }
    }

    private function isEventFinalized(int $eventId): bool
    {
        $endDateTime = Event::where('id', $eventId)->value('end_date_time');

        if (empty($endDateTime)) {
            return false;
        }

        return Carbon::parse($endDateTime)->lt(Carbon::now());
    }

    /**
     * Predetermined zones for the event's location (admin-managed).
     *
     * @return array<int,string>
     */
    private function zoneOptionsForEvent(?Event $event): array
    {
        if (!$event || empty($event->location_id)) {
            return [];
        }

        return EventZone::where('location_id', $event->location_id)
            ->where('status', 1)
            ->orderBy('serial_number', 'asc')
            ->orderBy('name', 'asc')
            ->pluck('name')
            ->filter(fn ($z) => is_string($z) && trim($z) !== '')
            ->unique()
            ->values()
            ->all();
    }

    private function resolveZoneFromRequest(Request $request, ?Event $event): ?string
    {
        $selected = trim((string) $request->input('zone_select', ''));
        $custom = trim((string) $request->input('custom_zone', ''));
        $legacy = trim((string) $request->input('zone', ''));

        if ($selected === '__custom__') {
            return $custom !== '' ? mb_substr($custom, 0, 100) : null;
        }
        if ($selected !== '') {
            return mb_substr($selected, 0, 100);
        }
        if ($legacy !== '') {
            return mb_substr($legacy, 0, 100);
        }
        if ($event && $event->allow_custom_zone && $custom !== '') {
            return mb_substr($custom, 0, 100);
        }

        return null;
    }

    private function assertZoneAllowed(?string $zone, ?Event $event): void
    {
        $options = $this->zoneOptionsForEvent($event);
        $allowCustom = (bool) ($event?->allow_custom_zone);

        if (empty($options) && !$allowCustom) {
            // No location zones configured yet — allow free text for backwards compatibility.
            if ($zone === null || $zone === '') {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'zone_select' => [__('Please enter a zone / category.')],
                ]);
            }
            return;
        }

        if ($zone === null || $zone === '') {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'zone_select' => [__('Please choose a zone.')],
            ]);
        }

        if (!empty($options) && in_array($zone, $options, true)) {
            return;
        }

        if ($allowCustom) {
            return;
        }

        throw \Illuminate\Validation\ValidationException::withMessages([
            'zone_select' => [__('Please choose a zone from the list for this location.')],
        ]);
    }

    public function destroy($id)
    {
        $ticket = ResaleTicket::findOrFail($id);
        $customerId = Auth::guard('customer')->id();

        if ((int) $ticket->seller_customer_id !== (int) $customerId) {
            return redirect()->route('customer.tickets.sold')->with('error', 'You are not allowed to delete this ticket.');
        }

        if ($ticket->status !== 'listed') {
            return redirect()->route('customer.tickets.sold')->with('error', 'Only listed tickets can be deleted.');
        }

        $this->deletePdfFiles($this->getStoredPdfList($ticket));
        $ticket->delete();

        return redirect()->route('customer.tickets.sold')->with('success', 'Ticket deleted successfully.');
    }
}

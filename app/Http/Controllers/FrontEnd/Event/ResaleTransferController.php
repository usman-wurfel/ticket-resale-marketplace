<?php

namespace App\Http\Controllers\FrontEnd\Event;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Event\ResaleTicket;
use App\Models\ResaleNotification;
use App\Services\Resale\TransferService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Seller-facing transfer flow (internal PDF upload or external confirmation)
 * and buyer-facing reassignment flow when a seller misses their deadline.
 */
class ResaleTransferController extends Controller
{
    public function __construct(private TransferService $transfers)
    {
    }

    /**
     * Show the seller's transfer page (either upload PDF or confirm external
     * transfer depending on event.event_ticket_transfer_type).
     */
    public function show($id)
    {
        $ticket = ResaleTicket::with(['event', 'eventContent', 'buyer'])->findOrFail($id);
        $this->assertSellerOr403($ticket);

        return view('frontend.customer.dashboard.resale.transfer', compact('ticket'));
    }

    /**
     * Internal transfer: PDF upload. Accepts one or several files: the seller
     * must provide one PDF per seat (i.e. quantity) across both the files
     * attached at listing-time and the ones uploaded now.
     */
    public function upload(Request $request, $id)
    {
        $request->validate([
            'ticket_pdfs'   => 'nullable|array|max:20',
            'ticket_pdfs.*' => 'file|mimes:pdf|max:20480',
        ]);

        $ticket = ResaleTicket::findOrFail($id);
        $this->assertSellerOr403($ticket);

        $files = $request->file('ticket_pdfs') ?? [];
        if (!is_array($files)) {
            $files = [$files];
        }

        try {
            /** @var Customer $seller */
            $seller = Auth::guard('customer')->user();
            $this->transfers->internalUpload($ticket, $files, $seller);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        return redirect()->route('customer.tickets.sold')
            ->with('success', 'Ticket transferred successfully. Payment will be released 48 hours after the event.');
    }

    /**
     * Serve a ticket PDF to the buyer (the seller and admin can also download
     * for support purposes). Ticket PDFs are stored under
     * public/assets/admin/file/resale-tickets/ but we proxy the download so
     * that access can be gated on the logged-in customer's role.
     */
    public function downloadPdf(Request $request, $id, $index)
    {
        $ticket = ResaleTicket::findOrFail($id);
        $customer = Auth::guard('customer')->user();
        if (!$customer) {
            abort(403);
        }

        $isBuyer = (int) $ticket->buyer_customer_id === (int) $customer->id;
        $isSeller = (int) $ticket->seller_customer_id === (int) $customer->id;
        if (!$isBuyer && !$isSeller) {
            abort(403);
        }

        if ($isBuyer && !in_array((string) $ticket->transfer_status, [
            ResaleTicket::TRANSFER_DONE,
        ], true)) {
            abort(404, 'Ticket PDF is not yet available.');
        }

        $files = [];
        if (!empty($ticket->ticket_pdfs)) {
            $decoded = json_decode((string) $ticket->ticket_pdfs, true);
            if (is_array($decoded)) {
                $files = array_values(array_filter($decoded));
            }
        }

        $idx = (int) $index;
        if (!isset($files[$idx])) {
            abort(404);
        }

        $fullPath = public_path('assets/admin/file/resale-tickets/' . $files[$idx]);
        if (!is_file($fullPath)) {
            abort(404);
        }

        $suggested = 'ticket-' . ($ticket->booking_id ?: $ticket->id) . '-' . ($idx + 1) . '.pdf';
        return response()->download($fullPath, $suggested, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /**
     * External transfer: seller confirms they transferred the ticket on the
     * external platform.
     */
    public function externalConfirm(Request $request, $id)
    {
        $ticket = ResaleTicket::findOrFail($id);
        $this->assertSellerOr403($ticket);

        try {
            /** @var Customer $seller */
            $seller = Auth::guard('customer')->user();
            $this->transfers->externalConfirm($ticket, $seller);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('customer.tickets.sold')
            ->with('success', 'Transfer confirmed. Payment will be released 48 hours after the event.');
    }

    /**
     * After a missed deadline the buyer may accept the reassignment offer; we
     * try to FIFO-allocate an equivalent ticket and redirect to the pay page.
     */
    public function requestReassignment(Request $request, $id)
    {
        $previous = ResaleTicket::findOrFail($id);
        /** @var Customer|null $buyer */
        $buyer = Auth::guard('customer')->user();
        if (!$buyer) {
            abort(403);
        }

        if (!$this->buyerOwnsMissedTicket($previous, $buyer)) {
            return back()->with('error', 'This ticket is not eligible for reassignment.');
        }

        try {
            $replacement = $this->transfers->reassignEquivalent($previous, $buyer);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        if (!$replacement) {
            return back()->with('error', 'No equivalent ticket is available right now. Please choose another ticket.');
        }

        $this->markReassignmentNotificationsHandled($previous->id, $buyer->id);

        return redirect()->route('customer.tickets.pay', $replacement->id)
            ->with('success', 'A new ticket has been reserved for you. Please confirm payment within 10 minutes.');
    }

    /**
     * Buyer declines the equivalent-ticket offer; we simply mark the related
     * notifications as read so the Accept/Decline call-to-action goes away.
     */
    public function declineReassignment(Request $request, $id)
    {
        $previous = ResaleTicket::findOrFail($id);
        /** @var Customer|null $buyer */
        $buyer = Auth::guard('customer')->user();
        if (!$buyer) {
            abort(403);
        }

        if (!$this->buyerOwnsMissedTicket($previous, $buyer)) {
            return back()->with('error', 'This ticket is not eligible for reassignment.');
        }

        $this->markReassignmentNotificationsHandled($previous->id, $buyer->id);

        return back()->with('success', 'Offer declined. You can browse the event to pick another ticket whenever you want.');
    }

    private function buyerOwnsMissedTicket(ResaleTicket $ticket, Customer $buyer): bool
    {
        if ($ticket->transfer_status !== ResaleTicket::TRANSFER_MISSED) {
            return false;
        }
        $owner = (int) ($ticket->buyer_customer_id ?: $ticket->last_buyer_customer_id);

        return $owner === (int) $buyer->id;
    }

    private function markReassignmentNotificationsHandled(int $resaleTicketId, int $buyerId): void
    {
        ResaleNotification::where('customer_id', $buyerId)
            ->where('resale_ticket_id', $resaleTicketId)
            ->whereIn('type', [
                ResaleNotification::TYPE_REASSIGNMENT_OFFERED,
                ResaleNotification::TYPE_REASSIGNMENT_UNAVAILABLE,
            ])
            ->update(['is_read' => true]);
    }

    private function assertSellerOr403(ResaleTicket $ticket): void
    {
        $customer = Auth::guard('customer')->user();
        if (!$customer || (int) $ticket->seller_customer_id !== (int) $customer->id) {
            abort(403);
        }
    }
}

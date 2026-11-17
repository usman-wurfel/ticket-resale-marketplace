<?php

namespace App\Http\Controllers\FrontEnd\Customer;

use App\Http\Controllers\Controller;
use App\Models\ResaleNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:customer');
    }

    public function index()
    {
        $customerId = Auth::guard('customer')->id();

        $notifications = ResaleNotification::where('customer_id', $customerId)
            ->orderByDesc('id')
            ->paginate(25);

        return view('frontend.customer.dashboard.resale.notifications', [
            'notifications' => $notifications,
        ]);
    }

    public function markAllRead(Request $request)
    {
        $customerId = Auth::guard('customer')->id();
        ResaleNotification::where('customer_id', $customerId)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return back()->with('success', 'All notifications marked as read.');
    }
}

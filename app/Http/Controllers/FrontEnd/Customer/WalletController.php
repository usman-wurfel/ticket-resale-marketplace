<?php

namespace App\Http\Controllers\FrontEnd\Customer;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\Withdraw;
use App\Models\WithdrawMethodInput;
use App\Models\WithdrawPaymentMethod;
use App\Services\Resale\BalanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;

/**
 * Customer-facing wallet page.
 *
 * Shows platform_balance (spendable / discounts / refunds), escrow_balance
 * (buyer funds held for active resale purchases), withdrawable_balance (seller
 * proceeds), transactions, and withdrawal requests.
 */
class WalletController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:customer');
    }

    public function index()
    {
        $customer = Auth::guard('customer')->user();

        $transactions = Transaction::where('customer_id', $customer->id)
            ->orderByDesc('id')
            ->limit(25)
            ->get();

        $withdraws = Withdraw::where('user_type', 'customer')
            ->where('customer_id', $customer->id)
            ->orderByDesc('id')
            ->limit(25)
            ->get();

        $methods = WithdrawPaymentMethod::where('status', 1)->get();

        return view('frontend.customer.dashboard.resale.wallet', [
            'customer' => $customer,
            'transactions' => $transactions,
            'withdraws' => $withdraws,
            'methods' => $methods,
        ]);
    }

    /**
     * Return the list of required input fields for a given withdraw method.
     * Used to dynamically render the withdrawal form via AJAX.
     */
    public function methodFields(Request $request)
    {
        $methodId = (int) $request->input('method_id');
        $inputs = WithdrawMethodInput::with('options')
            ->where('withdraw_payment_method_id', $methodId)
            ->orderBy('order_number', 'asc')
            ->get();

        return response()->json($inputs);
    }

    public function withdrawRequest(Request $request, BalanceService $balanceService)
    {
        $customer = Auth::guard('customer')->user();

        $validator = Validator::make($request->all(), [
            'withdraw_method' => 'required|integer|exists:withdraw_payment_methods,id',
            'withdraw_amount' => 'required|numeric|min:0.01',
            'balance_source' => 'required|in:platform,withdrawable',
        ]);
        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $amount = (float) $request->input('withdraw_amount');
        $method = WithdrawPaymentMethod::find($request->input('withdraw_method'));

        $min = (float) ($method->minimum_amount ?? 0);
        $max = (float) ($method->maximum_amount ?? 0);
        if ($min > 0 && $amount < $min) {
            return back()->with('error', 'Amount is below the minimum withdrawal for this method.')->withInput();
        }
        if ($max > 0 && $amount > $max) {
            return back()->with('error', 'Amount is above the maximum withdrawal for this method.')->withInput();
        }
        $balanceSource = (string) $request->input('balance_source');
        $column = $balanceSource === 'platform' ? 'platform_balance' : 'withdrawable_balance';
        if ((float) $customer->{$column} < $amount) {
            return back()->with('error', $balanceSource === 'platform'
                ? 'You do not have enough platform balance.'
                : 'You do not have enough withdrawable balance.')->withInput();
        }

        $inputs = WithdrawMethodInput::where('withdraw_payment_method_id', $method->id)
            ->orderBy('order_number', 'asc')
            ->get();

        $rules = [];
        foreach ($inputs as $in) {
            if ((int) $in->required === 1) {
                $rules[$in->name] = 'required';
            }
        }
        $dynValidator = Validator::make($request->all(), $rules);
        if ($dynValidator->fails()) {
            return back()->withErrors($dynValidator)->withInput();
        }

        $fields = [];
        foreach ($inputs as $in) {
            if ($request->filled($in->name)) {
                $fields[$in->name] = $request->input($in->name);
            }
        }

        try {
            $balanceService->requestWithdrawal(
                $customer,
                $amount,
                (int) $method->id,
                $fields,
                $request->input('additional_reference'),
                $balanceSource
            );
        } catch (\Throwable $e) {
            return back()->with('error', 'Unable to submit withdrawal: ' . $e->getMessage())->withInput();
        }

        Session::flash('success', 'Withdrawal request submitted. It will be executed within 24h.');
        return redirect()->route('customer.wallet');
    }
}

<?php

namespace App\Services\Resale;

use App\Models\Customer;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Customer money buckets:
 *   - platform_balance : spendable wallet (resale discounts, refunds from the
 *     platform). Customers may withdraw from this balance.
 *   - escrow_balance   : buyer funds held for active resale purchases until
 *     the sale is completed or refunded. Never used for discounts.
 *   - withdrawable_balance : seller proceeds after release (T+48h), may be withdrawn.
 */
class BalanceService
{
    /**
     * Buyer completes payment for a reserved resale: the full gross amount is
     * held in escrow_balance (any platform_balance discount was already applied).
     */
    public function recordBuyerPayment(Customer $buyer, float $amount, ?int $resaleTicketId, array $context = []): Transaction
    {
        return DB::transaction(function () use ($buyer, $amount, $resaleTicketId, $context) {
            $pre = (float) $buyer->escrow_balance;
            $buyer->escrow_balance = round($pre + $amount, 2);
            $buyer->save();

            return Transaction::create([
                'transcation_id' => $this->uniqueId(),
                'resale_ticket_id' => $resaleTicketId,
                'transcation_type' => Transaction::TYPE_RESALE_ESCROW,
                'customer_id' => $buyer->id,
                'status' => Transaction::STATUS_PAID,
                'payment_status' => '1',
                'paid_at' => now(),
                'payment_method' => 'platform',
                'gateway_type' => 'platform_simulated',
                'grand_total' => $amount,
                'pre_balance' => $pre,
                'after_balance' => $buyer->escrow_balance,
                'context' => json_encode($context),
            ]);
        });
    }

    /**
     * Apply platform_balance toward the ticket price (never escrow_balance).
     */
    public function applyBalanceDiscount(Customer $buyer, float $requestedAmount, ?int $resaleTicketId = null): float
    {
        if ($requestedAmount <= 0) {
            return 0.0;
        }

        return (float) DB::transaction(function () use ($buyer, $requestedAmount, $resaleTicketId) {
            $available = (float) $buyer->platform_balance;
            $used = min($available, round($requestedAmount, 2));
            if ($used <= 0) {
                return 0.0;
            }

            $buyer->platform_balance = round($available - $used, 2);
            $buyer->save();

            Transaction::create([
                'transcation_id' => $this->uniqueId(),
                'resale_ticket_id' => $resaleTicketId,
                'transcation_type' => Transaction::TYPE_BALANCE_DISCOUNT,
                'customer_id' => $buyer->id,
                'status' => Transaction::STATUS_PAID,
                'payment_status' => '1',
                'paid_at' => now(),
                'payment_method' => 'platform_balance',
                'gateway_type' => 'platform_simulated',
                'grand_total' => $used,
                'pre_balance' => $available,
                'after_balance' => $buyer->platform_balance,
            ]);

            return $used;
        });
    }

    /**
     * Refund the buyer to platform_balance and release the same amount from escrow.
     */
    public function refundBuyer(Customer $buyer, float $amount, ?int $resaleTicketId = null, string $reason = ''): Transaction
    {
        return DB::transaction(function () use ($buyer, $amount, $resaleTicketId, $reason) {
            $prePlatform = (float) $buyer->platform_balance;
            $preEscrow = (float) $buyer->escrow_balance;
            $buyer->platform_balance = round($prePlatform + $amount, 2);
            $fromEscrow = min($preEscrow, round($amount, 2));
            $buyer->escrow_balance = round(max(0.0, $preEscrow - $fromEscrow), 2);
            $buyer->save();

            return Transaction::create([
                'transcation_id' => $this->uniqueId(),
                'resale_ticket_id' => $resaleTicketId,
                'transcation_type' => Transaction::TYPE_RESALE_REFUND,
                'customer_id' => $buyer->id,
                'status' => Transaction::STATUS_REFUNDED,
                'payment_status' => '1',
                'refunded_at' => now(),
                'payment_method' => 'platform',
                'gateway_type' => 'platform_simulated',
                'grand_total' => $amount,
                'pre_balance' => $prePlatform,
                'after_balance' => $buyer->platform_balance,
                'context' => json_encode([
                    'reason' => $reason,
                    'escrow_pre' => $preEscrow,
                    'escrow_after' => $buyer->escrow_balance,
                ]),
            ]);
        });
    }

    /**
     * When a sale completes, remove the settled amount from the buyer's escrow.
     */
    public function settleBuyerEscrowForCompletedPurchase(Customer $buyer, float $amount, ?int $resaleTicketId = null): void
    {
        DB::transaction(function () use ($buyer, $amount, $resaleTicketId) {
            $pre = (float) $buyer->escrow_balance;
            $deduct = min($pre, round($amount, 2));
            if ($deduct <= 0) {
                return;
            }
            $buyer->escrow_balance = round($pre - $deduct, 2);
            $buyer->save();

            Transaction::create([
                'transcation_id' => $this->uniqueId(),
                'resale_ticket_id' => $resaleTicketId,
                'transcation_type' => Transaction::TYPE_RESALE_ESCROW_SETTLE,
                'customer_id' => $buyer->id,
                'status' => Transaction::STATUS_RELEASED,
                'payment_status' => '1',
                'released_at' => now(),
                'payment_method' => 'platform',
                'gateway_type' => 'platform_simulated',
                'grand_total' => $deduct,
                'pre_balance' => $pre,
                'after_balance' => $buyer->escrow_balance,
            ]);
        });
    }

    public function releaseToSeller(Customer $seller, float $amount, ?int $resaleTicketId = null): Transaction
    {
        return DB::transaction(function () use ($seller, $amount, $resaleTicketId) {
            $pre = (float) $seller->withdrawable_balance;
            $seller->withdrawable_balance = round($pre + $amount, 2);
            $seller->save();

            return Transaction::create([
                'transcation_id' => $this->uniqueId(),
                'resale_ticket_id' => $resaleTicketId,
                'transcation_type' => Transaction::TYPE_RESALE_RELEASE,
                'customer_id' => $seller->id,
                'status' => Transaction::STATUS_RELEASED,
                'payment_status' => '1',
                'released_at' => now(),
                'payment_method' => 'platform',
                'gateway_type' => 'platform_simulated',
                'grand_total' => $amount,
                'pre_balance' => $pre,
                'after_balance' => $seller->withdrawable_balance,
            ]);
        });
    }

    /**
     * @param  string  $balanceSource  "platform" | "withdrawable"
     */
    public function requestWithdrawal(
        Customer $customer,
        float $amount,
        int $methodId,
        array $fields = [],
        ?string $additionalReference = null,
        string $balanceSource = 'withdrawable'
    ): \App\Models\Withdraw {
        if ($amount <= 0) {
            throw new \DomainException('Withdrawal amount must be positive.');
        }

        $balanceSource = $balanceSource === 'platform' ? 'platform' : 'withdrawable';

        $column = $balanceSource === 'platform' ? 'platform_balance' : 'withdrawable_balance';
        if ((float) $customer->{$column} < $amount) {
            throw new \DomainException(
                $balanceSource === 'platform'
                    ? 'Insufficient platform balance.'
                    : 'Insufficient withdrawable balance.'
            );
        }

        return DB::transaction(function () use ($customer, $amount, $methodId, $fields, $additionalReference, $balanceSource, $column) {
            $method = \App\Models\WithdrawPaymentMethod::find($methodId);
            $fixed = (float) ($method->fixed_charge ?? 0);
            $pct = (float) ($method->percentage_charge ?? 0);
            $pctAmount = (($amount - $fixed) * $pct) / 100;
            $totalCharge = round($pctAmount + $fixed, 2);
            $payable = round($amount - $totalCharge, 2);

            $pre = (float) $customer->{$column};
            $customer->{$column} = round($pre - $amount, 2);
            $customer->save();

            $w = new \App\Models\Withdraw();
            $w->withdraw_id = uniqid('wdr_');
            $w->user_type = 'customer';
            $w->customer_id = $customer->id;
            $w->organizer_id = null;
            $w->method_id = $methodId;
            $w->amount = $amount;
            $w->payable_amount = $payable;
            $w->total_charge = $totalCharge;
            $w->additional_reference = $additionalReference;
            $w->feilds = json_encode($fields);
            $w->status = 0;
            $w->scheduled_execution_at = now()->addDay();
            if (Schema::hasColumn('withdraws', 'balance_source')) {
                $w->balance_source = $balanceSource;
            }
            $w->save();

            Transaction::create([
                'transcation_id' => $this->uniqueId(),
                'transcation_type' => Transaction::TYPE_WITHDRAWAL,
                'customer_id' => $customer->id,
                'status' => Transaction::STATUS_PENDING,
                'payment_status' => '0',
                'payment_method' => (string) $methodId,
                'gateway_type' => 'platform_simulated',
                'grand_total' => $amount,
                'pre_balance' => $pre,
                'after_balance' => $customer->{$column},
                'context' => json_encode([
                    'withdraw_id' => $w->id,
                    'reference' => $additionalReference,
                    'balance_source' => $balanceSource,
                ]),
            ]);

            return $w;
        });
    }

    private function uniqueId(): string
    {
        return time() . rand(1000, 9999);
    }
}

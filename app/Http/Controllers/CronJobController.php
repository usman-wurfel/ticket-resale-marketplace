<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Jobs\IyzicoEventPendingPayment;
use App\Jobs\IyzicoProductOrderPendingPayment;
use App\Models\Customer;
use App\Models\Event\Booking;
use App\Models\Event\ResaleTicket;
use App\Models\ShopManagement\ProductOrder;
use App\Models\Withdraw;
use App\Services\Resale\BalanceService;
use App\Services\Resale\ConfidenceLevelService;
use App\Services\Resale\PaymentReleaseService;
use App\Services\Resale\TransferService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class CronJobController extends Controller
{
    public function checkIyzicoPendingPayment()
    {
        try {
            /*```````````````````````````````````````````````````````
            ```````````Check Iyzico event pending bookings``````````
            -------------------------------------------------------*/
            $event_bookings = Booking::where([['paymentStatus', 'pending'], ['paymentMethod', 'Iyzico']])->get();
            if (count($event_bookings) > 0) {
                foreach ($event_bookings as $key => $event_booking) {
                    if (!is_null($event_booking->conversation_id)) {
                        IyzicoEventPendingPayment::dispatch($event_booking->id);
                    }
                }
            }
            /*```````````````````````````````````````````````````````
            ```````````Check Iyzico product purchase pending bookings``````````
            -------------------------------------------------------*/
            $productOrders = ProductOrder::where([['payment_status', 'pending'], ['method', 'Iyzico']])->get();
            if (count($productOrders) > 0) {
                foreach ($productOrders as $key => $productOrder) {
                    if (!is_null($productOrder->conversation_id)) {
                        IyzicoProductOrderPendingPayment::dispatch($productOrder->id);
                    }
                }
            }
        } catch (\Throwable $th) {
        }
    }
    public function sendTicket()
    {
        try {
            Artisan::call('queue:work', [
                '--stop-when-empty' => true, // To avoid infinite running in case
            ]);
        } catch (\Throwable $th) {
          
        }
    }

    public function sendPushNotificationPhone()
    {
      try {

        Artisan::call('queue:work', [
          '--stop-when-empty' => true, // To avoid infinite running in case
        ]);
      } catch (\Throwable $th) {
      }
    }

    /**
     * Single cron endpoint orchestrating every time-based task required by
     * the PRD. Hit it once a minute from the server's cron scheduler:
     *
     *     * * * * * curl -s https://example.com/resale/cron > /dev/null
     */
    public function resaleTick(
        TransferService $transfers,
        PaymentReleaseService $release,
        BalanceService $balance,
        ConfidenceLevelService $confidence
    ) {
        $report = [
            'expired_reservations' => 0,
            'missed_transfers' => 0,
            'released_payments' => 0,
            'confidence_refreshed' => 0,
            'depriority_flags_synced' => 0,
            'withdrawals_executed' => 0,
        ];

        try {
            $report['expired_reservations'] = $this->expireUnpaidReservations($balance);
        } catch (\Throwable $e) {
            \Log::warning('resaleTick expireUnpaidReservations failed: ' . $e->getMessage());
        }

        try {
            $report['missed_transfers'] = $transfers->enforceMissedDeadlines();
        } catch (\Throwable $e) {
            \Log::warning('resaleTick missed_transfers failed: ' . $e->getMessage());
        }

        try {
            $report['released_payments'] = $release->releaseDue();
        } catch (\Throwable $e) {
            \Log::warning('resaleTick release failed: ' . $e->getMessage());
        }

        try {
            $report['confidence_refreshed'] = $this->refreshStaleConfidenceLevels($confidence);
        } catch (\Throwable $e) {
            \Log::warning('resaleTick confidence failed: ' . $e->getMessage());
        }

        try {
            $report['depriority_flags_synced'] = $confidence->syncAllTicketDeprioritizedToSellerTiers();
        } catch (\Throwable $e) {
            \Log::warning('resaleTick depriority sync failed: ' . $e->getMessage());
        }

        try {
            $report['withdrawals_executed'] = $this->executeDueWithdrawals();
        } catch (\Throwable $e) {
            \Log::warning('resaleTick withdrawals failed: ' . $e->getMessage());
        }

        return response()->json($report);
    }

    /**
     * Cancel unpaid reservations whose 10-minute payment window is past.
     * The ticket returns to "listed" and is made available again.
     */
    private function expireUnpaidReservations(BalanceService $balance): int
    {
        $now = Carbon::now();
        $ids = ResaleTicket::query()
            ->where('status', ResaleTicket::STATUS_RESERVED)
            ->whereNotNull('payment_deadline')
            ->where('payment_deadline', '<', $now)
            ->pluck('id');

        $count = 0;
        foreach ($ids as $id) {
            $done = DB::transaction(function () use ($id) {
                /** @var ResaleTicket|null $t */
                $t = ResaleTicket::whereKey($id)->lockForUpdate()->first();
                if (!$t || $t->status !== ResaleTicket::STATUS_RESERVED) {
                    return false;
                }
                $t->update([
                    'status' => ResaleTicket::STATUS_LISTED,
                    'buyer_customer_id' => null,
                    'reserved_at' => null,
                    'reserved_until' => null,
                    'payment_deadline' => null,
                ]);
                return true;
            });
            if ($done) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Recalculate confidence levels for users whose scoring is stale OR who
     * have a finalized transaction that wasn't yet reflected. Batched to 50
     * users per run to avoid long requests on shared hosting.
     */
    private function refreshStaleConfidenceLevels(ConfidenceLevelService $confidence): int
    {
        $cutoff = Carbon::now()->subHours(24);
        $ids = Customer::query()
            ->where(function ($q) use ($cutoff) {
                $q->whereNull('confidence_updated_at')
                    ->orWhere('confidence_updated_at', '<', $cutoff);
            })
            ->orderBy('confidence_updated_at')
            ->limit(50)
            ->pluck('id');

        $count = 0;
        foreach ($ids as $id) {
            if ($confidence->recalculateById((int) $id)) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Execute due customer withdrawals (PRD: "within 2 business days"). We
     * subtract the amount from platform_balance or withdrawable_balance
     * (see withdraws.balance_source) and mark the row executed.
     */
    private function executeDueWithdrawals(): int
    {
        $now = Carbon::now();
        $rows = Withdraw::query()
            ->where('user_type', 'customer')
            ->where('status', 0)
            ->whereNotNull('scheduled_execution_at')
            ->where('scheduled_execution_at', '<=', $now)
            ->whereNull('executed_at')
            ->limit(100)
            ->get();

        $count = 0;
        foreach ($rows as $w) {
            $done = DB::transaction(function () use ($w) {
                $customer = Customer::whereKey($w->customer_id)->lockForUpdate()->first();
                if (!$customer) {
                    return false;
                }
                $amount = (float) $w->amount;
                $attrs = $w->getAttributes();
                $source = ($attrs['balance_source'] ?? 'withdrawable') === 'platform' ? 'platform' : 'withdrawable';
                $column = $source === 'platform' ? 'platform_balance' : 'withdrawable_balance';

                if ((float) $customer->{$column} < $amount) {
                    $w->status = 2; // decline
                    $w->save();
                    return false;
                }
                $customer->{$column} = round((float) $customer->{$column} - $amount, 2);
                $customer->save();

                $w->status = 1;
                $w->executed_at = now();
                $w->save();
                return true;
            });
            if ($done) {
                $count++;
            }
        }
        return $count;
    }
}

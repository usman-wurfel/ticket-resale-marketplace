<?php

namespace App\Services\Resale;

use App\Models\Customer;
use App\Models\Event\ResaleTicket;
use App\Models\SupportTicket;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * Bayesian litigation-risk score mapped to stored tiers on {@see Customer::confidence_level}.
 *
 * Stored values are internal tier codes (not English "risk" phrases):
 *   - HIGH  = lowest litigation risk → fewest seller/buyer restrictions
 *   - MEDIUM
 *   - LOW   = highest litigation risk → strictest non-banned rules
 *   - BANNED
 */
class ConfidenceLevelService
{
    private const DEFAULT_MEDIUM_THRESHOLD = 0.15;
    private const DEFAULT_HIGH_THRESHOLD = 0.30;
    private const DEFAULT_BAN_THRESHOLD = 0.60;

    public function recalculate(Customer $customer): string
    {
        $counters = $this->computeCounters($customer);

        $customer->successful_transactions_count = $counters['successful'];
        $customer->litigations_responsible_count = $counters['litigations'];

        $score = $this->computeScore($counters['successful'], $counters['litigations']);
        $level = $this->scoreToLevel($score, $customer->confidence_level, $customer);

        $customer->confidence_score = $score;
        $customer->confidence_level = $level;
        $customer->confidence_updated_at = now();
        $customer->save();

        $this->applyLevelActions($customer);

        return $level;
    }

    public function recalculateById(int $customerId): ?string
    {
        $c = Customer::find($customerId);
        return $c ? $this->recalculate($c) : null;
    }

    /**
     * Compute the two confidence inputs exactly as defined by the client:
     *
     *  successful_transactions_count
     *    A transaction is successful ONLY when the payment was actually freed
     *    to the seller. That happens either:
     *      - the normal way (no litigation, released at T+48h), or
     *      - a litigation whose blame fell on the BUYER (still released).
     *    Cases where the buyer was reimbursed (blame on seller, or unclear)
     *    are NOT successful, even though the ticket is finalized.
     *    The reliable signal for "payment freed to seller" is the existence of
     *    a release-to-seller transaction (Transaction::TYPE_RESALE_RELEASE) on
     *    that resale ticket. We count tickets where the customer is a party
     *    (seller or buyer) and the seller was paid.
     *
     *  litigations_responsible_count
     *    Counts two situations:
     *      - litigations where this user is directly to blame, and
     *      - litigations the user was involved in that were ruled "unclear"
     *        (both parties carry the unclear outcome).
     */
    private function computeCounters(Customer $customer): array
    {
        $successful = (int) DB::table('resale_tickets')
            ->where(function ($q) use ($customer) {
                $q->where('seller_customer_id', $customer->id)
                    ->orWhere('buyer_customer_id', $customer->id);
            })
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('transactions')
                    ->whereColumn('transactions.resale_ticket_id', 'resale_tickets.id')
                    ->where('transactions.transcation_type', \App\Models\Transaction::TYPE_RESALE_RELEASE);
            })
            ->count();

        // 1) Directly to blame (outcome assigned this customer as the culprit).
        $directlyBlamed = (int) SupportTicket::query()
            ->where('is_litigation', true)
            ->where('litigation_status', SupportTicket::LITIGATION_RESOLVED)
            ->where('blame_user_type', 'customer')
            ->where('blame_user_id', $customer->id)
            ->whereIn('outcome', [
                SupportTicket::OUTCOME_SELLER,
                SupportTicket::OUTCOME_BUYER,
            ])
            ->count();

        // 2) Involved in an "unclear" litigation (as opener or counterparty).
        $unclearInvolved = (int) SupportTicket::query()
            ->where('is_litigation', true)
            ->where('litigation_status', SupportTicket::LITIGATION_RESOLVED)
            ->where('outcome', SupportTicket::OUTCOME_UNCLEAR)
            ->where(function ($q) use ($customer) {
                $q->where(function ($q2) use ($customer) {
                    $q2->where('user_type', 'customer')->where('user_id', $customer->id);
                })->orWhere(function ($q2) use ($customer) {
                    $q2->where('counterparty_user_type', 'customer')->where('counterparty_user_id', $customer->id);
                });
            })
            ->count();

        return [
            'successful' => $successful,
            'litigations' => $directlyBlamed + $unclearInvolved,
        ];
    }

    public function computeScore(int $successful, int $litigations): float
    {
        $path = config('resale.confidence.python_script', base_path('scripts/confidence_level.py'));
        $python = config('resale.confidence.python_binary', 'python3');

        if (is_file($path)) {
            try {
                $process = new Process([
                    $python,
                    $path,
                    '--successful',
                    (string) $successful,
                    '--litigations',
                    (string) $litigations,
                ]);
                $process->setTimeout(10);
                $process->run();

                if ($process->isSuccessful()) {
                    $raw = trim($process->getOutput());
                    $decoded = json_decode($raw, true);
                    if (is_array($decoded) && isset($decoded['score'])) {
                        return max(0.0, min(1.0, (float) $decoded['score']));
                    }
                    if (is_numeric($raw)) {
                        return max(0.0, min(1.0, (float) $raw));
                    }
                }
            } catch (ProcessFailedException $e) {
                Log::warning('ConfidenceLevelService python call failed: ' . $e->getMessage());
            } catch (\Throwable $e) {
                Log::warning('ConfidenceLevelService python invocation error: ' . $e->getMessage());
            }
        }

        return $this->computeScoreFallback($successful, $litigations);
    }

    /**
     * Same model as scripts/confidence_level.py (Beta prior + scipy beta.cdf).
     */
    private function computeScoreFallback(int $successful, int $litigations): float
    {
        $k = 15.0;
        $p0 = 0.01;
        $pThreshold = 0.05;

        $successful = max(0, $successful);
        $litigations = max(0, $litigations);
        $n = $successful + $litigations;

        $alphaPost = $k * $p0 + $litigations;
        $betaPost = $k * (1.0 - $p0) + $n - $litigations;

        $cdf = $this->betaCdf($pThreshold, $alphaPost, $betaPost);
        $score = 1.0 - $cdf;

        return max(0.0, min(1.0, $score));
    }

    /**
     * Regularized incomplete beta I_x(a, b) — matches scipy.stats.beta.cdf.
     * Lentz/Numerical Recipes continued-fraction method.
     */
    private function betaCdf(float $x, float $a, float $b): float
    {
        if ($x <= 0.0) {
            return 0.0;
        }
        if ($x >= 1.0) {
            return 1.0;
        }

        $bt = exp(
            $this->lnGamma($a + $b) - $this->lnGamma($a) - $this->lnGamma($b)
            + $a * log($x) + $b * log(1.0 - $x)
        );

        if ($x < ($a + 1.0) / ($a + $b + 2.0)) {
            return $bt * $this->betaContinuedFraction($x, $a, $b) / $a;
        }

        return 1.0 - ($bt * $this->betaContinuedFraction(1.0 - $x, $b, $a) / $b);
    }

    private function betaContinuedFraction(float $x, float $a, float $b): float
    {
        $fpmin = 1e-30;
        $qab = $a + $b;
        $qap = $a + 1.0;
        $qam = $a - 1.0;
        $c = 1.0;
        $d = 1.0 - $qab * $x / $qap;

        if (abs($d) < $fpmin) {
            $d = $fpmin;
        }
        $d = 1.0 / $d;
        $h = $d;

        for ($m = 1; $m <= 200; $m++) {
            $m2 = 2 * $m;
            $aa = $m * ($b - $m) * $x / (($qam + $m2) * ($a + $m2));
            $d = 1.0 + $aa * $d;
            if (abs($d) < $fpmin) {
                $d = $fpmin;
            }
            $c = 1.0 + $aa / $c;
            if (abs($c) < $fpmin) {
                $c = $fpmin;
            }
            $d = 1.0 / $d;
            $del = $d * $c;
            $h *= $del;

            $aa = -($a + $m) * ($qab + $m) * $x / (($a + $m2) * ($qap + $m2));
            $d = 1.0 + $aa * $d;
            if (abs($d) < $fpmin) {
                $d = $fpmin;
            }
            $c = 1.0 + $aa / $c;
            if (abs($c) < $fpmin) {
                $c = $fpmin;
            }
            $d = 1.0 / $d;
            $del = $d * $c;
            $h *= $del;

            if (abs($del - 1.0) < 3e-7) {
                break;
            }
        }

        return $h;
    }

    private function lnGamma(float $z): float
    {
        static $coeff = [
            76.18009172947146,
            -86.50532032941677,
            24.01409824083091,
            -1.23173957245155,
            0.1208650973866179e-2,
            -0.5395239384953e-5,
        ];

        $y = $z;
        $tmp = $z + 5.5;
        $tmp -= ($z + 0.5) * log($tmp);
        $ser = 1.000000000190015;

        foreach ($coeff as $c) {
            $y += 1.0;
            $ser += $c / $y;
        }

        return -$tmp + log(2.5066282746310005 * $ser / $z);
    }

    public function scoreToLevel(float $score, ?string $currentLevel = null, ?Customer $customer = null): string
    {
        $medium = (float) config('resale.confidence.medium_threshold', self::DEFAULT_MEDIUM_THRESHOLD);
        $high = (float) config('resale.confidence.high_threshold', self::DEFAULT_HIGH_THRESHOLD);
        $ban = (float) config('resale.confidence.ban_threshold', self::DEFAULT_BAN_THRESHOLD);

        if ($currentLevel === Customer::CONFIDENCE_BANNED) {
            return Customer::CONFIDENCE_BANNED;
        }

        if ($score >= $ban) {
            // Point 15: after admin manual reinstate, do not auto re-BAN from the
            // same pre-reinstate blame snapshot via cron. If the user is blamed
            // in new litigations afterwards, allow BANNED again.
            if (
                $customer
                && (int) $customer->manual_reinstatement_count > 0
                && $currentLevel === Customer::CONFIDENCE_LOW
                && (int) $customer->litigations_responsible_count <= (int) ($customer->litigations_at_reinstatement ?? 0)
            ) {
                return Customer::CONFIDENCE_LOW;
            }

            return Customer::CONFIDENCE_BANNED;
        }
        if ($score >= $high) {
            return Customer::CONFIDENCE_LOW;
        }
        if ($score >= $medium) {
            return Customer::CONFIDENCE_MEDIUM;
        }

        return Customer::CONFIDENCE_HIGH;
    }

    /**
     * HIGH tier: no restriction (best track record).
     * MEDIUM: deprioritized listings/purchases.
     * LOW: same as MEDIUM, plus at most one active resale row and one open
     *      buyer transaction at a time.
     * BANNED: suspend account; cancel all open listings.
     */
    public function applyLevelActions(Customer $customer): void
    {
        $level = $customer->confidence_level;

        if ($level === Customer::CONFIDENCE_BANNED) {
            if ((int) $customer->status !== 0) {
                $customer->status = 0;
                $customer->save();
            }
            ResaleTicket::where('seller_customer_id', $customer->id)
                ->where('status', ResaleTicket::STATUS_LISTED)
                ->update(['status' => ResaleTicket::STATUS_CANCELLED]);
            return;
        }

        if ($level === Customer::CONFIDENCE_HIGH) {
            // Clear deprioritization on every non-cancelled row for this seller so
            // listings recover when the seller returns to LOW RISK (internal HIGH).
            // Rows locked by a missed transfer deadline keep their deprioritization.
            ResaleTicket::where('seller_customer_id', $customer->id)
                ->where('status', '!=', ResaleTicket::STATUS_CANCELLED)
                ->where(function ($q) {
                    $q->where('deprioritized_locked', false)
                        ->orWhereNull('deprioritized_locked');
                })
                ->update(['deprioritized' => false]);
            return;
        }

        if (in_array($level, [Customer::CONFIDENCE_MEDIUM, Customer::CONFIDENCE_LOW], true)) {
            ResaleTicket::where('seller_customer_id', $customer->id)
                ->whereIn('status', [
                    ResaleTicket::STATUS_LISTED,
                    ResaleTicket::STATUS_RESERVED,
                    ResaleTicket::STATUS_PAID,
                ])
                ->update(['deprioritized' => true]);
        }

        if ($level === Customer::CONFIDENCE_LOW) {
            $listings = ResaleTicket::where('seller_customer_id', $customer->id)
                ->where('status', ResaleTicket::STATUS_LISTED)
                ->orderByDesc('created_at')
                ->get();

            foreach ($listings->slice(1) as $extra) {
                $extra->update(['status' => ResaleTicket::STATUS_CANCELLED]);
            }
        }
    }

    /**
     * LOW-tier buyers may not have more than one active buyer-side resale flow.
     *
     * "Open" here means a row that still requires buyer/seller action in the
     * purchase pipeline: reserved or paid.
     */
    public function canOpenNewBuyerTransaction(Customer $buyer): bool
    {
        return $this->countOpenBuyerResaleTransactions($buyer) === 0;
    }

    /**
     * Number of resale rows still tying up this buyer's single-slot pipeline.
     */
    public function countOpenBuyerResaleTransactions(Customer $buyer): int
    {
        if ($buyer->confidence_level !== Customer::CONFIDENCE_LOW) {
            return 0;
        }

        return (int) ResaleTicket::where('buyer_customer_id', $buyer->id)
            ->whereIn('status', [
                ResaleTicket::STATUS_RESERVED,
                ResaleTicket::STATUS_PAID,
            ])
            ->whereNull('payment_released_at')
            ->count();
    }

    /**
     * Human-readable reason when {@see canOpenNewBuyerTransaction} is false.
     */
    public function buyerOpenTransactionBlockMessage(Customer $buyer): string
    {
        $rows = ResaleTicket::where('buyer_customer_id', $buyer->id)
            ->whereIn('status', [
                ResaleTicket::STATUS_RESERVED,
                ResaleTicket::STATUS_PAID,
            ])
            ->whereNull('payment_released_at')
            ->orderByDesc('id')
            ->get(['id', 'status', 'booking_id']);

        if ($rows->isEmpty()) {
            return __('You already have a resale purchase in progress. Open “My bought tickets” to finish or cancel it before starting another.');
        }

        $summary = $rows->take(3)->map(function (ResaleTicket $r) {
            return '#' . $r->id . ' (' . strtoupper((string) $r->status) . ')';
        })->join(', ');

        return __(
            'Your account is limited to one resale purchase in progress at a time (until that sale fully completes). You already have: :rows. Open “My bought tickets” — you may need to pay for a reservation, cancel it, wait for the seller to transfer, or wait until the sale is finalized.',
            ['rows' => $summary]
        );
    }

    /**
     * Re-align resale_tickets.deprioritized with each seller's current tier for
     * all active marketplace rows. Fixes stale flags when confidence was not
     * recalculated recently or rows were missed.
     *
     * Safety net: any row whose transfer previously failed (missed deadline)
     * is force-deprioritized and locked, no matter how it was relisted. This
     * guarantees a missed-deadline ticket is never sitting on the listing with
     * a clear (priority) flag, and never recovers priority when the seller is
     * LOW RISK.
     */
    public function syncAllTicketDeprioritizedToSellerTiers(): int
    {
        $active = [
            ResaleTicket::STATUS_LISTED,
            ResaleTicket::STATUS_RESERVED,
            ResaleTicket::STATUS_PAID,
        ];

        // Force missed-deadline rows to deprioritized + locked first, so the
        // "cleared" pass below can never un-deprioritize them.
        $missedFixed = ResaleTicket::query()
            ->where('transfer_status', ResaleTicket::TRANSFER_MISSED)
            ->where(function ($q) {
                $q->where('deprioritized', false)
                    ->orWhereNull('deprioritized')
                    ->orWhere('deprioritized_locked', false)
                    ->orWhereNull('deprioritized_locked');
            })
            ->update([
                'deprioritized' => true,
                'deprioritized_locked' => true,
            ]);

        $cleared = ResaleTicket::query()
            ->whereIn('status', $active)
            ->whereHas('seller', function ($q) {
                $q->where('confidence_level', Customer::CONFIDENCE_HIGH);
            })
            ->where('deprioritized', true)
            ->where(function ($q) {
                $q->where('deprioritized_locked', false)
                    ->orWhereNull('deprioritized_locked');
            })
            ->update(['deprioritized' => false]);

        $marked = ResaleTicket::query()
            ->whereIn('status', $active)
            ->whereHas('seller', function ($q) {
                $q->whereIn('confidence_level', [
                    Customer::CONFIDENCE_MEDIUM,
                    Customer::CONFIDENCE_LOW,
                ]);
            })
            ->where(function ($q) {
                $q->where('deprioritized', false)->orWhereNull('deprioritized');
            })
            ->update(['deprioritized' => true]);

        return (int) $missedFixed + (int) $cleared + (int) $marked;
    }
}

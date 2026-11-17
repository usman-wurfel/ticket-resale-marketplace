<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Augments the transactions table to model the resale payment life-cycle:
 *  - a transaction is opened when a buyer is matched with a ticket
 *  - it records the 10 minute payment deadline
 *  - and the payment-release (or refund) timeline once the event ends.
 *
 * New transcation_type codes (kept as int to stay consistent with existing
 * values 1..6):
 *   10 = resale escrow (buyer → platform hold)
 *   11 = resale release to seller (platform → seller withdrawable balance)
 *   12 = resale refund to buyer (platform → buyer balance)
 *   13 = balance used as discount on a resale purchase
 *   14 = withdrawal request (platform → bank)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('transactions')) {
            return;
        }

        Schema::table('transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('transactions', 'resale_ticket_id')) {
                $table->unsignedBigInteger('resale_ticket_id')->nullable()->after('booking_id');
                $table->index('resale_ticket_id');
            }
            if (!Schema::hasColumn('transactions', 'status')) {
                // pending | paid | released | refunded | cancelled | failed
                $table->string('status', 30)->nullable()->after('payment_status');
            }
            if (!Schema::hasColumn('transactions', 'payment_deadline')) {
                $table->dateTime('payment_deadline')->nullable()->after('status');
            }
            if (!Schema::hasColumn('transactions', 'paid_at')) {
                $table->dateTime('paid_at')->nullable()->after('payment_deadline');
            }
            if (!Schema::hasColumn('transactions', 'released_at')) {
                $table->dateTime('released_at')->nullable()->after('paid_at');
            }
            if (!Schema::hasColumn('transactions', 'refunded_at')) {
                $table->dateTime('refunded_at')->nullable()->after('released_at');
            }
            if (!Schema::hasColumn('transactions', 'context')) {
                // free-form JSON (discount applied, balance before/after, etc.)
                $table->text('context')->nullable()->after('refunded_at');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('transactions')) {
            return;
        }

        Schema::table('transactions', function (Blueprint $table) {
            foreach ([
                'resale_ticket_id',
                'status',
                'payment_deadline',
                'paid_at',
                'released_at',
                'refunded_at',
                'context',
            ] as $col) {
                if (Schema::hasColumn('transactions', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};

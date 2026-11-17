<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Persist two pieces of state that the "missed transfer deadline" flow needs:
 *
 *   - deprioritized_locked : if true, the ticket must remain deprioritized in
 *     FIFO even when the seller becomes LOW RISK again. This is required by the
 *     spec: a ticket that has missed a transfer deadline stays deprioritized.
 *   - last_buyer_customer_id : the buyer who was assigned to the row before the
 *     miss. We null out buyer_customer_id when relisting so we still need to
 *     know who to offer reassignment to (and to verify ownership when the buyer
 *     accepts/declines the offer).
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('resale_tickets')) {
            return;
        }

        Schema::table('resale_tickets', function (Blueprint $table) {
            if (!Schema::hasColumn('resale_tickets', 'deprioritized_locked')) {
                $table->boolean('deprioritized_locked')->default(false)->after('deprioritized');
            }
            if (!Schema::hasColumn('resale_tickets', 'last_buyer_customer_id')) {
                $table->unsignedBigInteger('last_buyer_customer_id')->nullable()->after('buyer_customer_id');
                $table->index('last_buyer_customer_id');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('resale_tickets')) {
            return;
        }

        Schema::table('resale_tickets', function (Blueprint $table) {
            if (Schema::hasColumn('resale_tickets', 'deprioritized_locked')) {
                $table->dropColumn('deprioritized_locked');
            }
            if (Schema::hasColumn('resale_tickets', 'last_buyer_customer_id')) {
                try {
                    $table->dropIndex(['last_buyer_customer_id']);
                } catch (\Throwable $e) {
                    // index may not exist on older databases
                }
                $table->dropColumn('last_buyer_customer_id');
            }
        });
    }
};

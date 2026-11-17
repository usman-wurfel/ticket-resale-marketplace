<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Client feedback (Litigations + missed deadlines):
 *
 *  1) The admin must be able to decide, when resolving a litigation, whether a
 *     reimbursement is issued (none / partial / full), whether the payment is
 *     released to the seller, or both at the same time. We store that decision
 *     on the litigation row (support_tickets).
 *
 *  3) A ticket that has missed the transfer deadline twice must be cancelled
 *     automatically. We keep a per-ticket counter for that.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('support_tickets')) {
            Schema::table('support_tickets', function (Blueprint $table) {
                if (!Schema::hasColumn('support_tickets', 'settlement_mode')) {
                    // 'timeline' = follow the standard T+48h timeline / blame rules
                    // 'manual'   = admin decided the financial outcome explicitly
                    $table->string('settlement_mode', 20)->default('timeline')->after('resolver_admin_id');
                }
                if (!Schema::hasColumn('support_tickets', 'reimbursement_type')) {
                    // none | partial | full
                    $table->string('reimbursement_type', 20)->default('none')->after('settlement_mode');
                }
                if (!Schema::hasColumn('support_tickets', 'reimbursement_amount')) {
                    $table->decimal('reimbursement_amount', 12, 2)->default(0)->after('reimbursement_type');
                }
                if (!Schema::hasColumn('support_tickets', 'release_to_seller')) {
                    $table->boolean('release_to_seller')->default(false)->after('reimbursement_amount');
                }
            });
        }

        if (Schema::hasTable('resale_tickets')) {
            Schema::table('resale_tickets', function (Blueprint $table) {
                if (!Schema::hasColumn('resale_tickets', 'missed_deadline_count')) {
                    $table->unsignedInteger('missed_deadline_count')->default(0)->after('transfer_deadline');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('support_tickets')) {
            Schema::table('support_tickets', function (Blueprint $table) {
                foreach (['settlement_mode', 'reimbursement_type', 'reimbursement_amount', 'release_to_seller'] as $col) {
                    if (Schema::hasColumn('support_tickets', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('resale_tickets') && Schema::hasColumn('resale_tickets', 'missed_deadline_count')) {
            Schema::table('resale_tickets', function (Blueprint $table) {
                $table->dropColumn('missed_deadline_count');
            });
        }
    }
};

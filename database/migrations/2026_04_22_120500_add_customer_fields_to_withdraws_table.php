<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Customers (end-users) must be able to request withdrawals of funds that
 * have been released to them after a finalized resale transaction.
 *
 * PRD: "We automatically execute the transfer order to their bank account
 * within 2 business days". We store that scheduled-execution datetime on the
 * row itself so the cron can pick it up.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('withdraws')) {
            return;
        }

        Schema::table('withdraws', function (Blueprint $table) {
            if (!Schema::hasColumn('withdraws', 'user_type')) {
                // customer | organizer (organizer is the legacy default)
                $table->string('user_type', 30)->default('organizer')->after('id');
            }
            if (!Schema::hasColumn('withdraws', 'customer_id')) {
                $table->unsignedBigInteger('customer_id')->nullable()->after('user_type');
            }
            if (!Schema::hasColumn('withdraws', 'scheduled_execution_at')) {
                $table->dateTime('scheduled_execution_at')->nullable()->after('status');
            }
            if (!Schema::hasColumn('withdraws', 'executed_at')) {
                $table->dateTime('executed_at')->nullable()->after('scheduled_execution_at');
            }
        });

        // Make organizer_id nullable for customer-initiated withdrawals.
        DB::statement("ALTER TABLE `withdraws` MODIFY `organizer_id` INT NULL");
    }

    public function down(): void
    {
        if (!Schema::hasTable('withdraws')) {
            return;
        }

        Schema::table('withdraws', function (Blueprint $table) {
            foreach (['user_type', 'customer_id', 'scheduled_execution_at', 'executed_at'] as $col) {
                if (Schema::hasColumn('withdraws', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('customers')) {
            return;
        }

        Schema::table('customers', function (Blueprint $table) {
            if (!Schema::hasColumn('customers', 'platform_balance')) {
                // Money the platform holds on behalf of the user (escrow/credit)
                $table->decimal('platform_balance', 14, 2)->default(0)->after('status');
            }
            if (!Schema::hasColumn('customers', 'withdrawable_balance')) {
                // Funds released to the user, usable for withdrawal
                $table->decimal('withdrawable_balance', 14, 2)->default(0)->after('platform_balance');
            }
            if (!Schema::hasColumn('customers', 'confidence_level')) {
                // LOW | MEDIUM | HIGH | BANNED
                $table->string('confidence_level', 20)->default('HIGH')->after('withdrawable_balance');
            }
            if (!Schema::hasColumn('customers', 'confidence_score')) {
                // Last probability score produced by the Python model (0..1)
                $table->decimal('confidence_score', 6, 4)->nullable()->after('confidence_level');
            }
            if (!Schema::hasColumn('customers', 'confidence_updated_at')) {
                $table->timestamp('confidence_updated_at')->nullable()->after('confidence_score');
            }
            if (!Schema::hasColumn('customers', 'successful_transactions_count')) {
                // Finalized transactions the user participated in (as buyer or seller)
                $table->unsignedInteger('successful_transactions_count')->default(0)->after('confidence_updated_at');
            }
            if (!Schema::hasColumn('customers', 'litigations_responsible_count')) {
                // Litigations the user was found responsible for (blame = this user)
                $table->unsignedInteger('litigations_responsible_count')->default(0)->after('successful_transactions_count');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('customers')) {
            return;
        }

        Schema::table('customers', function (Blueprint $table) {
            foreach ([
                'platform_balance',
                'withdrawable_balance',
                'confidence_level',
                'confidence_score',
                'confidence_updated_at',
                'successful_transactions_count',
                'litigations_responsible_count',
            ] as $col) {
                if (Schema::hasColumn('customers', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('customers') && !Schema::hasColumn('customers', 'escrow_balance')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->decimal('escrow_balance', 14, 2)->default(0)->after('platform_balance');
            });
        }

        if (Schema::hasTable('withdraws') && !Schema::hasColumn('withdraws', 'balance_source')) {
            Schema::table('withdraws', function (Blueprint $table) {
                $table->string('balance_source', 32)->default('withdrawable');
            });
        }

        if (Schema::hasTable('customers') && Schema::hasColumn('customers', 'confidence_level')) {
            DB::statement("ALTER TABLE `customers` MODIFY `confidence_level` VARCHAR(20) NOT NULL DEFAULT 'HIGH'");
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('customers') && Schema::hasColumn('customers', 'escrow_balance')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropColumn('escrow_balance');
            });
        }

        if (Schema::hasTable('withdraws') && Schema::hasColumn('withdraws', 'balance_source')) {
            Schema::table('withdraws', function (Blueprint $table) {
                $table->dropColumn('balance_source');
            });
        }

        if (Schema::hasTable('customers') && Schema::hasColumn('customers', 'confidence_level')) {
            DB::statement("ALTER TABLE `customers` MODIFY `confidence_level` VARCHAR(20) NOT NULL DEFAULT 'LOW'");
        }
    }
};

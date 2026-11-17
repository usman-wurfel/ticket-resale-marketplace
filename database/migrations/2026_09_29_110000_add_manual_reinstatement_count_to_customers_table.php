<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('customers') && !Schema::hasColumn('customers', 'manual_reinstatement_count')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->unsignedInteger('manual_reinstatement_count')->default(0)->after('litigations_responsible_count');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('customers') && Schema::hasColumn('customers', 'manual_reinstatement_count')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropColumn('manual_reinstatement_count');
            });
        }
    }
};

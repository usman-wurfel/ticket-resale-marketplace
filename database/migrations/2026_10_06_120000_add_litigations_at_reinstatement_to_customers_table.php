<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('customers') && !Schema::hasColumn('customers', 'litigations_at_reinstatement')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->unsignedInteger('litigations_at_reinstatement')->nullable()->after('manual_reinstatement_count');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('customers') && Schema::hasColumn('customers', 'litigations_at_reinstatement')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropColumn('litigations_at_reinstatement');
            });
        }
    }
};

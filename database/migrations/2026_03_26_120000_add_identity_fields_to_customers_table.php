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
            if (!Schema::hasColumn('customers', 'phone')) {
                $table->string('phone', 30)->nullable();
            }
            if (!Schema::hasColumn('customers', 'gender')) {
                $table->string('gender', 20)->nullable();
            }
            if (!Schema::hasColumn('customers', 'national_id_number')) {
                $table->string('national_id_number', 100)->nullable();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('customers')) {
            return;
        }

        Schema::table('customers', function (Blueprint $table) {
            if (Schema::hasColumn('customers', 'national_id_number')) {
                $table->dropColumn('national_id_number');
            }
        });
    }
};


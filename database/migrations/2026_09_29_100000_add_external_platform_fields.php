<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('events') && !Schema::hasColumn('events', 'external_platform_name')) {
            Schema::table('events', function (Blueprint $table) {
                $table->string('external_platform_name', 150)->nullable()->after('event_ticket_transfer_type');
            });
        }

        if (Schema::hasTable('resale_tickets') && !Schema::hasColumn('resale_tickets', 'buyer_external_account')) {
            Schema::table('resale_tickets', function (Blueprint $table) {
                $table->string('buyer_external_account', 255)->nullable()->after('buyer_customer_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('resale_tickets') && Schema::hasColumn('resale_tickets', 'buyer_external_account')) {
            Schema::table('resale_tickets', function (Blueprint $table) {
                $table->dropColumn('buyer_external_account');
            });
        }

        if (Schema::hasTable('events') && Schema::hasColumn('events', 'external_platform_name')) {
            Schema::table('events', function (Blueprint $table) {
                $table->dropColumn('external_platform_name');
            });
        }
    }
};

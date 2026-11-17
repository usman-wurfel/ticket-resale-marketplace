<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->string('source_type', 50)->nullable()->after('last_message');
            $table->unsignedBigInteger('source_id')->nullable()->after('source_type');
            $table->unsignedBigInteger('event_id')->nullable()->after('source_id');
            $table->string('booking_ref')->nullable()->after('event_id');
            $table->string('counterparty_user_type', 30)->nullable()->after('booking_ref');
            $table->unsignedBigInteger('counterparty_user_id')->nullable()->after('counterparty_user_type');
            $table->string('counterparty_name')->nullable()->after('counterparty_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->dropColumn([
                'source_type',
                'source_id',
                'event_id',
                'booking_ref',
                'counterparty_user_type',
                'counterparty_user_id',
                'counterparty_name',
            ]);
        });
    }
};


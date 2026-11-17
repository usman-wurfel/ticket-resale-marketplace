<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PRD: "The release date of the tickets for an event is one of the pieces of
     * information we specify when creating an event on our platform".
     *
     * We record when sellers will actually have access to their tickets/QR codes
     * (independent from the event start datetime). If null we assume tickets are
     * immediately available.
     */
    public function up(): void
    {
        if (!Schema::hasTable('events')) {
            return;
        }

        Schema::table('events', function (Blueprint $table) {
            if (!Schema::hasColumn('events', 'start_date_time')) {
                // Canonical event start datetime (T), derived from start_date + start_time.
                $table->dateTime('start_date_time')->nullable()->after('end_date_time');
            }
            if (!Schema::hasColumn('events', 'ticket_availability_datetime')) {
                // When tickets/QR codes become transferable by the seller.
                $table->dateTime('ticket_availability_datetime')->nullable()->after('start_date_time');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('events')) {
            return;
        }

        Schema::table('events', function (Blueprint $table) {
            foreach (['ticket_availability_datetime', 'start_date_time'] as $col) {
                if (Schema::hasColumn('events', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};

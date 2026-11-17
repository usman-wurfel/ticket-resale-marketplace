<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('event_ticket_transfer_type', 20)->nullable()->after('event_type');
        });

        DB::table('events')
            ->whereNull('event_ticket_transfer_type')
            ->update(['event_ticket_transfer_type' => 'external']);
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('event_ticket_transfer_type');
        });
    }
};


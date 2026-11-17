<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resale_tickets', function (Blueprint $table) {
            $table->string('booking_id')->nullable()->after('id');
            $table->index('booking_id');
        });

        $rows = DB::table('resale_tickets')
            ->whereNotNull('buyer_customer_id')
            ->where(function ($q) {
                $q->whereNull('booking_id')->orWhere('booking_id', '');
            })
            ->select('id')
            ->get();

        foreach ($rows as $row) {
            DB::table('resale_tickets')
                ->where('id', $row->id)
                ->update(['booking_id' => uniqid()]);
        }
    }

    public function down(): void
    {
        Schema::table('resale_tickets', function (Blueprint $table) {
            $table->dropIndex(['booking_id']);
            $table->dropColumn('booking_id');
        });
    }
};


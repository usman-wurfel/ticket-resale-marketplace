<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resale_tickets', function (Blueprint $table) {
            $table->text('ticket_pdfs')->nullable()->after('ticket_pdf');
        });
    }

    public function down(): void
    {
        Schema::table('resale_tickets', function (Blueprint $table) {
            $table->dropColumn('ticket_pdfs');
        });
    }
};


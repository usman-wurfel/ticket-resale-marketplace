<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lightweight in-app notifications for the resale marketplace.
 *
 * We intentionally keep the schema small:
 *   - customer_id  : who the notification is for
 *   - type         : event key ("transfer_requested", "payment_released", ...)
 *   - title        : short line shown in the dropdown
 *   - body         : optional longer description
 *   - resale_ticket_id / support_ticket_id : optional deep-link context
 *   - is_read      : simple read flag
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('resale_notifications')) {
            Schema::create('resale_notifications', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('customer_id');
                $table->string('type', 60);
                $table->string('title', 255);
                $table->text('body')->nullable();
                $table->unsignedBigInteger('resale_ticket_id')->nullable();
                $table->unsignedBigInteger('support_ticket_id')->nullable();
                $table->boolean('is_read')->default(false);
                $table->timestamps();
                $table->index(['customer_id', 'is_read']);
                $table->index('type');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('resale_notifications');
    }
};

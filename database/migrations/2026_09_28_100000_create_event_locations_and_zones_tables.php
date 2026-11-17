<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('event_locations')) {
            Schema::create('event_locations', function (Blueprint $table) {
                $table->id();
                $table->bigInteger('language_id')->nullable();
                $table->string('name')->nullable();
                $table->string('slug')->nullable();
                $table->string('status')->nullable();
                $table->integer('serial_number')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('event_zones')) {
            Schema::create('event_zones', function (Blueprint $table) {
                $table->id();
                $table->bigInteger('language_id')->nullable();
                $table->unsignedBigInteger('location_id')->nullable();
                $table->string('name')->nullable();
                $table->string('slug')->nullable();
                $table->string('status')->nullable();
                $table->integer('serial_number')->nullable();
                $table->timestamps();

                $table->index('location_id');
            });
        }

        if (Schema::hasTable('events')) {
            Schema::table('events', function (Blueprint $table) {
                if (!Schema::hasColumn('events', 'location_id')) {
                    $table->unsignedBigInteger('location_id')->nullable()->after('longitude');
                }
                if (!Schema::hasColumn('events', 'allow_custom_zone')) {
                    $table->boolean('allow_custom_zone')->default(false)->after('location_id');
                }
            });
        }

        if (Schema::hasTable('resale_tickets')) {
            Schema::table('resale_tickets', function (Blueprint $table) {
                if (!Schema::hasColumn('resale_tickets', 'entrance_date')) {
                    // Day the ticket grants entrance for (multi-day events).
                    $table->date('entrance_date')->nullable()->after('zone');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('resale_tickets') && Schema::hasColumn('resale_tickets', 'entrance_date')) {
            Schema::table('resale_tickets', function (Blueprint $table) {
                $table->dropColumn('entrance_date');
            });
        }

        if (Schema::hasTable('events')) {
            Schema::table('events', function (Blueprint $table) {
                if (Schema::hasColumn('events', 'allow_custom_zone')) {
                    $table->dropColumn('allow_custom_zone');
                }
                if (Schema::hasColumn('events', 'location_id')) {
                    $table->dropColumn('location_id');
                }
            });
        }

        Schema::dropIfExists('event_zones');
        Schema::dropIfExists('event_locations');
    }
};

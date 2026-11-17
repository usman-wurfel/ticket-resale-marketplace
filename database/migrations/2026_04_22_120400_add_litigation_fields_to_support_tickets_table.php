<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Support tickets double as the INCIDENT / LITIGATION table described in the
 * PRD: a user may open an incident about an event, a specific ticket, or
 * about something unrelated (administrative problems).
 *
 * When the incident is tied to a transferred resale ticket we treat it as a
 * litigation that gates payment release.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('support_tickets')) {
            return;
        }

        Schema::table('support_tickets', function (Blueprint $table) {
            if (!Schema::hasColumn('support_tickets', 'resale_ticket_id')) {
                $table->unsignedBigInteger('resale_ticket_id')->nullable()->after('event_id');
                $table->index('resale_ticket_id');
            }
            if (!Schema::hasColumn('support_tickets', 'is_litigation')) {
                $table->boolean('is_litigation')->default(false)->after('resale_ticket_id');
            }
            if (!Schema::hasColumn('support_tickets', 'litigation_status')) {
                // open | resolved
                $table->string('litigation_status', 20)->nullable()->after('is_litigation');
            }
            if (!Schema::hasColumn('support_tickets', 'outcome')) {
                // seller | buyer | unclear
                $table->string('outcome', 20)->nullable()->after('litigation_status');
            }
            if (!Schema::hasColumn('support_tickets', 'blame_user_type')) {
                // customer (the user-type blamed – sellers and buyers are both customers)
                $table->string('blame_user_type', 30)->nullable()->after('outcome');
            }
            if (!Schema::hasColumn('support_tickets', 'blame_user_id')) {
                $table->unsignedBigInteger('blame_user_id')->nullable()->after('blame_user_type');
            }
            if (!Schema::hasColumn('support_tickets', 'resolved_at')) {
                $table->dateTime('resolved_at')->nullable()->after('blame_user_id');
            }
            if (!Schema::hasColumn('support_tickets', 'resolver_admin_id')) {
                $table->unsignedBigInteger('resolver_admin_id')->nullable()->after('resolved_at');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('support_tickets')) {
            return;
        }

        Schema::table('support_tickets', function (Blueprint $table) {
            foreach ([
                'resale_ticket_id',
                'is_litigation',
                'litigation_status',
                'outcome',
                'blame_user_type',
                'blame_user_id',
                'resolved_at',
                'resolver_admin_id',
            ] as $col) {
                if (Schema::hasColumn('support_tickets', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};

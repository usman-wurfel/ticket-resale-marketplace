<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('resale_tickets')) {
            return;
        }

        Schema::table('resale_tickets', function (Blueprint $table) {
            if (!Schema::hasColumn('resale_tickets', 'zone')) {
                // FIFO matching is per (event, zone, price).
                $table->string('zone', 100)->nullable()->after('event_id');
            }
            if (!Schema::hasColumn('resale_tickets', 'deprioritized')) {
                // When true, the ticket is kept at the end of its FIFO queue
                // (see Confidence-Level rules for MEDIUM / HIGH / failed transfer).
                $table->boolean('deprioritized')->default(false)->after('zone');
            }
            if (!Schema::hasColumn('resale_tickets', 'reserved_at')) {
                // When FIFO allocated the ticket to a buyer.
                $table->dateTime('reserved_at')->nullable()->after('reserved_until');
            }
            if (!Schema::hasColumn('resale_tickets', 'payment_deadline')) {
                // 10 minutes after reservation.
                $table->dateTime('payment_deadline')->nullable()->after('reserved_at');
            }
            if (!Schema::hasColumn('resale_tickets', 'paid_at')) {
                $table->dateTime('paid_at')->nullable()->after('payment_deadline');
            }
            if (!Schema::hasColumn('resale_tickets', 'transfer_status')) {
                // pending | transferred | missed_deadline | cancelled
                $table->string('transfer_status', 30)->nullable()->after('paid_at');
            }
            if (!Schema::hasColumn('resale_tickets', 'transfer_deadline')) {
                $table->dateTime('transfer_deadline')->nullable()->after('transfer_status');
            }
            if (!Schema::hasColumn('resale_tickets', 'transferred_at')) {
                $table->dateTime('transferred_at')->nullable()->after('transfer_deadline');
            }
            if (!Schema::hasColumn('resale_tickets', 'payment_release_due_at')) {
                // T + 48h (may be pushed out by unresolved litigations).
                $table->dateTime('payment_release_due_at')->nullable()->after('transferred_at');
            }
            if (!Schema::hasColumn('resale_tickets', 'payment_released_at')) {
                $table->dateTime('payment_released_at')->nullable()->after('payment_release_due_at');
            }
            if (!Schema::hasColumn('resale_tickets', 'previous_ticket_id')) {
                // When a buyer is re-assigned an equivalent ticket after a
                // missed transfer, we keep a pointer to the previous ticket so
                // the FIFO algorithm never re-allocates the same ticket.
                $table->unsignedBigInteger('previous_ticket_id')->nullable()->after('payment_released_at');
            }
        });

        // Separate block so we can also adjust the status enum-like string length
        // and add additional indexes.
        Schema::table('resale_tickets', function (Blueprint $table) {
            if (!$this->indexExists('resale_tickets', 'resale_tickets_fifo_idx')) {
                $table->index(['event_id', 'zone', 'price', 'status', 'deprioritized', 'created_at'], 'resale_tickets_fifo_idx');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('resale_tickets')) {
            return;
        }

        Schema::table('resale_tickets', function (Blueprint $table) {
            if ($this->indexExists('resale_tickets', 'resale_tickets_fifo_idx')) {
                $table->dropIndex('resale_tickets_fifo_idx');
            }
        });

        Schema::table('resale_tickets', function (Blueprint $table) {
            foreach ([
                'zone',
                'deprioritized',
                'reserved_at',
                'payment_deadline',
                'paid_at',
                'transfer_status',
                'transfer_deadline',
                'transferred_at',
                'payment_release_due_at',
                'payment_released_at',
                'previous_ticket_id',
            ] as $col) {
                if (Schema::hasColumn('resale_tickets', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        try {
            $sm = Schema::getConnection()->getDoctrineSchemaManager();
            foreach ($sm->listTableIndexes($table) as $existing) {
                if ($existing->getName() === $index) {
                    return true;
                }
            }
        } catch (\Throwable $e) {
            // doctrine/dbal may not be installed – fall back to raw query
            $rows = \DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$index]);
            return !empty($rows);
        }
        return false;
    }
};

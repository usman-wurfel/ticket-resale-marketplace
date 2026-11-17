<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('support_tickets') || !Schema::hasColumn('support_tickets', 'user_id')) {
            return;
        }

        // Avoid doctrine/dbal requirement for column changes.
        DB::statement('ALTER TABLE support_tickets MODIFY user_id INT(11) NULL');
    }

    public function down(): void
    {
        if (!Schema::hasTable('support_tickets') || !Schema::hasColumn('support_tickets', 'user_id')) {
            return;
        }

        DB::statement('ALTER TABLE support_tickets MODIFY user_id INT(11) NOT NULL');
    }
};

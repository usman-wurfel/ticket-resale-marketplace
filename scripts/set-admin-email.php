<?php

/**
 * Update admin login email to admin@gmail.com (keep username admin, password admin1).
 */

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (!Schema::hasTable('admins')) {
    fwrite(STDERR, "admins table missing\n");
    exit(1);
}

$hash = Hash::make('admin1');

$updated = DB::table('admins')->where('id', 1)->update([
    'email' => 'admin@gmail.com',
    'username' => 'admin',
    'password' => $hash,
    'status' => 1,
    'updated_at' => now(),
]);

// Fallback: update first admin if id=1 missing
if ($updated === 0) {
    $first = DB::table('admins')->orderBy('id')->first();
    if ($first) {
        DB::table('admins')->where('id', $first->id)->update([
            'email' => 'admin@gmail.com',
            'username' => 'admin',
            'password' => $hash,
            'status' => 1,
            'updated_at' => now(),
        ]);
    }
}

$row = DB::table('admins')->orderBy('id')->first(['id', 'username', 'email', 'status']);
echo 'admin=' . json_encode($row) . PHP_EOL;
echo "password=admin1\n";
echo "OK\n";

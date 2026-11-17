<?php

/**
 * Reset testing account passwords to admin1 on this environment.
 * Run: php8.2 scripts/reset-test-passwords.php
 */

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$plain = 'admin1';
$hash = Hash::make($plain);

$report = [];

function pickLogin($row): array
{
    $username = $row->username ?? null;
    $email = $row->email ?? null;
    $name = $row->name
        ?? trim(($row->first_name ?? '') . ' ' . ($row->last_name ?? ''))
        ?? trim(($row->fname ?? '') . ' ' . ($row->lname ?? ''));
    return [
        'id' => $row->id ?? null,
        'username' => $username,
        'email' => $email,
        'name' => is_string($name) ? trim($name) : '',
        'status' => $row->status ?? null,
    ];
}

// Admins
if (Schema::hasTable('admins')) {
    $n = DB::table('admins')->update([
        'password' => $hash,
        'updated_at' => now(),
    ]);
    foreach (DB::table('admins')->orderBy('id')->get() as $row) {
        $info = pickLogin($row);
        $info['role'] = 'admin';
        $info['updated'] = $n > 0;
        $report[] = $info;
    }
}

// Customers (frontend users / buyers / sellers)
if (Schema::hasTable('customers')) {
    $n = DB::table('customers')->update([
        'password' => $hash,
        'updated_at' => now(),
    ]);
    foreach (DB::table('customers')->orderBy('id')->get() as $row) {
        $info = pickLogin($row);
        $info['role'] = 'customer';
        $info['updated'] = $n > 0;
        $report[] = $info;
    }
}

// Organizers
if (Schema::hasTable('organizers')) {
    $n = DB::table('organizers')->update([
        'password' => $hash,
        'updated_at' => now(),
    ]);
    foreach (DB::table('organizers')->orderBy('id')->get() as $row) {
        $info = pickLogin($row);
        $info['role'] = 'organizer';
        $info['updated'] = $n > 0;
        $report[] = $info;
    }
}

// Legacy users table (if used)
if (Schema::hasTable('users')) {
    $n = DB::table('users')->update([
        'password' => $hash,
        'updated_at' => now(),
    ]);
    foreach (DB::table('users')->orderBy('id')->get() as $row) {
        $info = pickLogin($row);
        $info['role'] = 'user';
        $info['updated'] = $n > 0;
        $report[] = $info;
    }
}

echo "PASSWORD=$plain\n";
echo "COUNT=" . count($report) . "\n";
foreach ($report as $r) {
    echo implode('|', [
        $r['role'],
        'id=' . ($r['id'] ?? ''),
        'username=' . ($r['username'] ?? ''),
        'email=' . ($r['email'] ?? ''),
        'name=' . ($r['name'] ?? ''),
        'status=' . ($r['status'] ?? ''),
    ]) . PHP_EOL;
}
echo "OK\n";

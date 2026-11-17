<?php

/**
 * Live smoke checks for 2026-10-06 client feedback fixes.
 */

use App\Models\Customer;
use App\Services\Resale\ConfidenceLevelService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$ok = true;
function check(string $label, bool $pass, string $detail = ''): void
{
    global $ok;
    if (!$pass) {
        $ok = false;
    }
    echo ($pass ? 'PASS' : 'FAIL') . " | {$label}" . ($detail !== '' ? " | {$detail}" : '') . PHP_EOL;
}

$svcFile = file_get_contents(base_path('app/Services/Resale/ConfidenceLevelService.php')) ?: '';
$ctrlFile = file_get_contents(base_path('app/Http/Controllers/FrontEnd/Event/TicketResaleController.php')) ?: '';
$createIncident = file_get_contents(base_path('resources/views/backend/support_ticket/create.blade.php')) ?: '';
$dropzone = file_get_contents(base_path('public/assets/admin/js/admin_dropzone.js')) ?: '';
$eventCtrl = file_get_contents(base_path('app/Http/Controllers/BackEnd/Event/EventController.php')) ?: '';

check(
    'Point2 no global one-listing lock',
    str_contains($ctrlFile, 'hasStrictResaleSellerRules')
        && !str_contains($ctrlFile, 'every seller may have only one active listing')
);

check(
    'Point13 incident form TinyMCE-safe',
    str_contains($createIncident, 'admin_create_incident_form')
        && str_contains($createIncident, 'tinymce.triggerSave')
        && !preg_match('/name="description"[^>]*required/', $createIncident)
);

check(
    'Point15 litigations_at_reinstatement column',
    Schema::hasColumn('customers', 'litigations_at_reinstatement')
);

check(
    'Point15 scoreToLevel uses litigation snapshot',
    str_contains($svcFile, 'litigations_at_reinstatement')
        && str_contains($svcFile, 'litigations_responsible_count')
);

check(
    'Gallery upload error messaging',
    str_contains($dropzone, 'stringifyUploadMessage')
        && str_contains($eventCtrl, 'got {$width}x{$height}')
);

// Behavioral Point 15 checks
$svc = app(ConfidenceLevelService::class);
$ban = (float) config('resale.confidence.ban_threshold', 0.60);
$fake = new Customer([
    'manual_reinstatement_count' => 1,
    'litigations_at_reinstatement' => 2,
    'litigations_responsible_count' => 2,
    'confidence_level' => Customer::CONFIDENCE_LOW,
]);
$levelSame = $svc->scoreToLevel($ban + 0.1, Customer::CONFIDENCE_LOW, $fake);
check('Point15 same blame stays HIGH RISK/LOW', $levelSame === Customer::CONFIDENCE_LOW, $levelSame);

$fakeWorse = clone $fake;
$fakeWorse->litigations_responsible_count = 3;
$levelWorse = $svc->scoreToLevel($ban + 0.1, Customer::CONFIDENCE_LOW, $fakeWorse);
check('Point15 new blame can BANNED', $levelWorse === Customer::CONFIDENCE_BANNED, $levelWorse);

$admin = DB::table('admins')->orderBy('id')->first(['id', 'username', 'email', 'status']);
check('Admin account present', (bool) $admin, json_encode($admin));

echo ($ok ? "ALL_PASS\n" : "HAS_FAILS\n");
exit($ok ? 0 : 1);

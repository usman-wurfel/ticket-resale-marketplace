<?php

/**
 * One-shot: align live branding with local Ticket Resell look.
 * Run: php8.2 scripts/sync-live-branding.php
 */

use App\Models\BasicSettings\Basic;
use App\Models\Event;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$basic = Basic::query()->first();
if (!$basic) {
    fwrite(STDERR, "basic_settings row missing\n");
    exit(1);
}

$basic->logo = '69e89f55c63f4.png';
$basic->favicon = '69e89f68eedae.png';
$basic->primary_color = 'DCB160';
$basic->website_title = 'Ticket Resell';
$basic->save();

echo 'branding=' . $basic->logo . '|' . $basic->primary_color . '|' . $basic->website_title . PHP_EOL;

$logoPath = base_path('public/assets/admin/img/' . $basic->logo);
echo 'logo_exists=' . (is_file($logoPath) ? '1' : '0') . PHP_EOL;

// Footer logo: Ticket Resell artwork (same as local)
$footerLogoName = '1776854970.png';
$footerLogoSrcCandidates = [
    base_path('public/assets/admin/img/footer_logo/' . $footerLogoName),
    base_path('public/assets/admin/img/69e9eabd8f952.png'),
    base_path('public/assets/admin/img/' . $basic->logo),
];
$footerLogoSrc = null;
foreach ($footerLogoSrcCandidates as $candidate) {
    if (is_file($candidate)) {
        $footerLogoSrc = $candidate;
        break;
    }
}

$footerLogoDir = base_path('public/assets/admin/img/footer_logo');
if (!is_dir($footerLogoDir)) {
    mkdir($footerLogoDir, 0755, true);
}

if ($footerLogoSrc) {
    $footerLogoDest = $footerLogoDir . '/' . $footerLogoName;
    if (!is_file($footerLogoDest) || filesize($footerLogoDest) < 50000) {
        File::copy($footerLogoSrc, $footerLogoDest);
    }
    DB::table('footer_contents')->update([
        'footer_logo' => $footerLogoName,
        'footer_background_color' => '113454',
        'updated_at' => now(),
    ]);
    echo 'footer_logo=' . $footerLogoName . '|exists=' . (is_file($footerLogoDest) ? '1' : '0') . PHP_EOL;
} else {
    echo "footer_logo_src_missing\n";
}

$fallback = '1683371808.png';
$dir = base_path('public/assets/admin/img/event/thumbnail/');
$fixed = 0;

foreach (Event::query()->get(['id', 'thumbnail']) as $event) {
    $file = $dir . ($event->thumbnail ?? '');
    if (!$event->thumbnail || !is_file($file)) {
        $event->thumbnail = $fallback;
        $event->save();
        $fixed++;
    }
}

echo 'fixed_thumbs=' . $fixed . PHP_EOL;
echo "OK\n";

<?php

/**
 * Repair missing public asset files referenced by the DB.
 * Copies a real image into each missing path so the browser stops getting HTML 200s.
 */

use App\Models\Advertisement;
use App\Models\BasicSettings\Basic;
use App\Models\Event;
use App\Models\Event\EventImage;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Schema;

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

function firstExisting(array $candidates): ?string
{
    foreach ($candidates as $path) {
        if ($path && is_file($path)) {
            return $path;
        }
    }
    return null;
}

function ensureCopied(string $dest, string $src, string &$log, bool $replaceTiny = false): void
{
    $dir = dirname($dest);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    // Replace tiny placeholders (< 150KB) left by earlier thumbnail fallbacks
    if (is_file($dest) && filesize($dest) > 1000 && !($replaceTiny && filesize($dest) < 150000)) {
        return;
    }
    if (!copy($src, $dest)) {
        throw new RuntimeException("Failed copy $src -> $dest");
    }
    $log .= "fixed=" . str_replace(base_path() . '/', '', $dest) . PHP_EOL;
}

$log = '';
$galleryFallbackPool = glob(base_path('public/assets/admin/img/event-gallery/*.{jpg,jpeg,png,webp,gif}'), GLOB_BRACE) ?: [];
$thumbFallbackPool = glob(base_path('public/assets/admin/img/event/thumbnail/*.{jpg,jpeg,png,webp,gif}'), GLOB_BRACE) ?: [];

// Prefer largest real gallery photos so placeholders are not tiny 320px thumbs
usort($galleryFallbackPool, static function ($a, $b) {
    return (filesize($b) ?: 0) <=> (filesize($a) ?: 0);
});

$genericFallback = firstExisting(array_merge(
    $galleryFallbackPool,
    [
        base_path('public/assets/front/images/banner.jpg'),
        base_path('public/assets/front/images/hero-bg.jpg'),
        base_path('public/assets/admin/img/62d5204681dc2.jpg'),
    ],
    $thumbFallbackPool
));

if (!$genericFallback) {
    fwrite(STDERR, "No fallback image available on disk\n");
    exit(1);
}

echo 'fallback=' . str_replace(base_path() . '/', '', $genericFallback) . PHP_EOL;

// Event gallery images
if (class_exists(EventImage::class) && Schema::hasTable('event_images')) {
    foreach (EventImage::query()->get(['id', 'event_id', 'image']) as $row) {
        if (!$row->image) {
            continue;
        }
        $dest = base_path('public/assets/admin/img/event-gallery/' . $row->image);
        if (is_file($dest) && filesize($dest) > 1000) {
            continue;
        }
        $event = Event::query()->find($row->event_id);
        $src = firstExisting([
            $genericFallback,
            $event && $event->thumbnail
                ? base_path('public/assets/admin/img/event/thumbnail/' . $event->thumbnail)
                : null,
        ]);
        ensureCopied($dest, $src, $log, true);
    }
}

// Advertisements
if (class_exists(Advertisement::class) && Schema::hasTable('advertisements')) {
    foreach (Advertisement::query()->get() as $ad) {
        $name = $ad->image ?? $ad->ad_image ?? null;
        if (!$name && isset($ad->getAttributes()['image'])) {
            $name = $ad->getAttributes()['image'];
        }
        // Try common column names
        foreach (['image', 'ad_image', 'photo'] as $col) {
            if (!empty($ad->{$col})) {
                $name = $ad->{$col};
                break;
            }
        }
        if (!$name) {
            continue;
        }
        $dest = base_path('public/assets/admin/img/advertisements/' . $name);
        if (is_file($dest) && filesize($dest) > 1000) {
            continue;
        }
        ensureCopied($dest, $genericFallback, $log, true);
    }
}

// Also heal known broken paths from live page even if model columns differ
$hardcoded = [
    'public/assets/admin/img/event-gallery/690ed26ccc711.jpg',
    'public/assets/admin/img/event-gallery/690ed26f2ec1c.jpg',
    'public/assets/admin/img/event-gallery/690ed271bbc5a.jpg',
    'public/assets/admin/img/advertisements/64577dea0f0ec.png',
];
foreach ($hardcoded as $rel) {
    $dest = base_path($rel);
    if (is_file($dest) && filesize($dest) > 150000) {
        continue;
    }
    ensureCopied($dest, $genericFallback, $log, true);
}

// Logo / favicon from basic settings
$basic = Basic::query()->first();
if ($basic) {
    foreach (['logo' => $basic->logo, 'favicon' => $basic->favicon, 'breadcrumb' => $basic->breadcrumb ?? null] as $label => $file) {
        if (!$file) {
            continue;
        }
        $dest = base_path('public/assets/admin/img/' . $file);
        if (is_file($dest) && filesize($dest) > 500) {
            continue;
        }
        ensureCopied($dest, $genericFallback, $log);
    }
}

echo $log === '' ? "nothing_to_fix\n" : $log;
echo "OK\n";

<?php

/**
 * Repair ONLY missing/broken asset files referenced by DB.
 * Never overwrite healthy existing images.
 */

use App\Models\BasicSettings\Basic;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

function firstExisting(array $candidates): ?string
{
    static $breadcrumbSize = null;
    if ($breadcrumbSize === null) {
        $breadcrumbSize = @filesize(base_path('public/assets/admin/img/62d5204681dc2.jpg')) ?: 0;
    }
    foreach ($candidates as $path) {
        if (!$path || !is_file($path)) {
            continue;
        }
        $size = filesize($path) ?: 0;
        if ($size <= 200) {
            continue;
        }
        if ($breadcrumbSize > 0 && $size === $breadcrumbSize && !str_ends_with($path, '62d5204681dc2.jpg')) {
            continue;
        }
        return $path;
    }
    return null;
}

function largestIn(string $dir): array
{
    $dir = rtrim($dir, '/');
    $files = [];
    foreach (['jpg', 'jpeg', 'png', 'webp', 'gif', 'JPG', 'JPEG', 'PNG', 'WEBP', 'GIF'] as $ext) {
        $files = array_merge($files, glob($dir . '/*.' . $ext) ?: []);
    }
    $files = array_values(array_unique($files));
    usort($files, static fn ($a, $b) => (filesize($b) ?: 0) <=> (filesize($a) ?: 0));
    return $files;
}

function isBroken(?string $absPath): bool
{
    if (!$absPath || !is_file($absPath)) {
        return true;
    }
    $size = filesize($absPath) ?: 0;
    // Only treat near-empty files as broken; small partner logos are valid (~2-4KB)
    if ($size < 200) {
        return true;
    }
    $fh = fopen($absPath, 'rb');
    $head = $fh ? fread($fh, 32) : '';
    if ($fh) {
        fclose($fh);
    }
    if (is_string($head) && (str_starts_with($head, '<!DOCTYPE') || str_starts_with($head, '<html'))) {
        return true;
    }
    // Detect earlier accidental overwrite with breadcrumb JPG
    static $breadcrumbSize = null;
    if ($breadcrumbSize === null) {
        $breadcrumbSize = @filesize(base_path('public/assets/admin/img/62d5204681dc2.jpg')) ?: 0;
    }
    if ($breadcrumbSize > 0 && $size === $breadcrumbSize && !str_ends_with($absPath, '62d5204681dc2.jpg')) {
        return true;
    }
    return false;
}

function ensureMissing(string $destRel, ?string $preferredSrc, string $generic, string &$log): void
{
    $dest = base_path($destRel);
    if (!isBroken($dest)) {
        return;
    }
    $dir = dirname($dest);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $src = firstExisting([$preferredSrc, $generic]);
    if (!$src) {
        $log .= "skip_no_src=$destRel\n";
        return;
    }
    File::copy($src, $dest);
    $log .= 'fixed=' . $destRel . '|from=' . str_replace(base_path() . '/', '', $src) . PHP_EOL;
}

$log = '';

$basic = Basic::query()->first();
if ($basic) {
    $basic->logo = '69e89f55c63f4.png';
    $basic->favicon = '69e89f68eedae.png';
    $basic->primary_color = 'DCB160';
    $basic->website_title = 'Ticket Resell';
    if (empty($basic->breadcrumb)) {
        $basic->breadcrumb = '62d5204681dc2.jpg';
    }
    if (empty($basic->preloader)) {
        $basic->preloader = '63cbb14274c51.gif';
    }
    if (Schema::hasColumn('basic_settings', 'breadcrumb_overlay_color')) {
        $basic->breadcrumb_overlay_color = '030A15';
    }
    if (Schema::hasColumn('basic_settings', 'breadcrumb_overlay_opacity')) {
        $basic->breadcrumb_overlay_opacity = '0.80';
    }
    $basic->save();
}

DB::table('footer_contents')->update([
    'footer_logo' => '1776854970.png',
    'footer_background_color' => '113454',
    'updated_at' => now(),
]);

$galleryPool = largestIn(base_path('public/assets/admin/img/event-gallery'));
$thumbPool = largestIn(base_path('public/assets/admin/img/event/thumbnail'));
$frontPool = largestIn(base_path('public/assets/front/images'));
$generic = firstExisting(array_merge($galleryPool, $frontPool, $thumbPool, [
    base_path('public/assets/admin/img/62d5204681dc2.jpg'),
]));

if (!$generic) {
    fwrite(STDERR, "No generic fallback image found\n");
    exit(1);
}
echo 'generic=' . str_replace(base_path() . '/', '', $generic) . PHP_EOL;

$maps = [
    'basic_settings' => [
        'logo' => '',
        'favicon' => '',
        'breadcrumb' => '',
        'preloader' => '',
    ],
    'footer_contents' => [
        'footer_logo' => 'footer_logo/',
    ],
    'advertisements' => [
        'image' => 'advertisements/',
    ],
    'events' => [
        'thumbnail' => 'event/thumbnail/',
        'ticket_logo' => 'event_ticket_logo/',
        'ticket_image' => 'event_ticket/',
        'ticket_slot_image' => 'event_ticket/',
    ],
    'event_images' => [
        'image' => 'event-gallery/',
    ],
    'event_categories' => [
        'image' => 'event-category/',
    ],
    'categories' => [
        'image' => 'event-category/',
    ],
    'hero_sections' => [
        'background_image' => 'hero-section/',
        'image' => 'hero-section/',
    ],
    'action_sections' => [
        'background_image' => 'action-section/',
        'image' => 'action-section/',
    ],
    'partners' => [
        'image' => 'partner/',
    ],
    'testimonials' => [
        'image' => 'testimonial/',
    ],
    'blogs' => [
        'image' => 'blogs/',
    ],
    'blog_informations' => [
        'image' => 'blogs/',
    ],
];

foreach ($maps as $table => $cols) {
    if (!Schema::hasTable($table)) {
        continue;
    }
    $existingCols = Schema::getColumnListing($table);
    $select = array_values(array_filter(array_keys($cols), fn ($c) => in_array($c, $existingCols, true)));
    if ($select === []) {
        continue;
    }
    foreach (DB::table($table)->get($select) as $row) {
        foreach ($select as $col) {
            $name = $row->{$col} ?? null;
            if (!$name || !is_string($name)) {
                continue;
            }
            $rel = 'public/assets/admin/img/' . $cols[$col] . ltrim($name, '/');
            // Never clobber restored wide hero banners with random fallbacks
            if (str_contains($rel, '/hero-section/') && is_file(base_path($rel))) {
                $head = file_get_contents(base_path($rel), false, null, 0, 3) ?: '';
                $size = filesize(base_path($rel)) ?: 0;
                if ($size > 80000 && (str_starts_with($head, "\xFF\xD8\xFF") || str_starts_with($head, 'GIF'))) {
                    continue;
                }
            }
            $folder = dirname(base_path($rel));
            $preferred = firstExisting(largestIn($folder));
            ensureMissing($rel, $preferred, $generic, $log);
        }
    }
}

ensureMissing(
    'public/assets/admin/img/footer_logo/1776854970.png',
    firstExisting([
        base_path('public/assets/admin/img/footer_logo/1776854970.png'),
        base_path('public/assets/admin/img/69e9eabd8f952.png'),
        base_path('public/assets/admin/img/69e89f55c63f4.png'),
    ]),
    $generic,
    $log
);

echo $log === '' ? "nothing_to_fix\n" : $log;
echo "OK\n";

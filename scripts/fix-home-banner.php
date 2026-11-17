<?php

/**
 * Restore the correct wide home hero background and point DB at it.
 */

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$heroDir = base_path('public/assets/admin/img/hero-section');
$dest = $heroDir . '/629ec0bd9c0b0.jpg';

$candidates = [
    $dest,
    base_path('public/assets/front/images/hero-bg.jpg'),
    $heroDir . '/627cf56fdf611.jpg',
    $heroDir . '/62d53dd954205.jpg',
];

$src = null;
foreach ($candidates as $path) {
    if (!is_file($path)) {
        continue;
    }
    $size = filesize($path) ?: 0;
    // Reject tiny / HTML / portrait-ish wrong overwrites (~903x740 PNG renamed as jpg)
    if ($size < 50000) {
        continue;
    }
    $head = file_get_contents($path, false, null, 0, 8) ?: '';
    if (str_starts_with($head, "\x89PNG") && $size < 400000) {
        // likely the wrong square PNG that was copied onto .jpg
        continue;
    }
    if (str_starts_with($head, '<!DOCTYPE') || str_starts_with($head, '<html')) {
        continue;
    }
    $src = $path;
    break;
}

if (!$src) {
    fwrite(STDERR, "No valid hero source found\n");
    exit(1);
}

if (!is_dir($heroDir)) {
    mkdir($heroDir, 0755, true);
}

if ($src !== $dest) {
    File::copy($src, $dest);
}

// Ensure DB points to this filename
if (Schema::hasTable('hero_sections')) {
    DB::table('hero_sections')->update([
        'background_image' => '629ec0bd9c0b0.jpg',
        'updated_at' => now(),
    ]);
}

echo 'hero_src=' . str_replace(base_path() . '/', '', $src) . PHP_EOL;
echo 'hero_dest_size=' . filesize($dest) . PHP_EOL;
echo "OK\n";

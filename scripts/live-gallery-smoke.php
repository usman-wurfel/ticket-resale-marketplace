<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$srcDir = public_path('assets/admin/img/event-gallery');
$sample = null;
foreach (['jpg', 'jpeg', 'png'] as $ext) {
    foreach (glob($srcDir . '/*.' . $ext) ?: [] as $f) {
        if (@filesize($f) > 1000) {
            $sample = $f;
            break 2;
        }
    }
}

if (!$sample) {
    echo "NO_SAMPLE\n";
    exit(0);
}

$tmp = sys_get_temp_dir() . '/live-up-' . uniqid() . '.jpg';
copy($sample, $tmp);
$file = new Illuminate\Http\UploadedFile($tmp, 'qa.jpg', 'image/jpeg', null, true);
$resp = App\Support\EventGalleryUploader::store($file, null);
$data = $resp->getData(true);
echo json_encode($data) . ' status=' . $resp->getStatusCode() . PHP_EOL;

if (!empty($data['file_id'])) {
    $pi = App\Models\Event\EventImage::find($data['file_id']);
    if ($pi) {
        @unlink(public_path('assets/admin/img/event-gallery/' . $pi->image));
        $pi->delete();
    }
}
@unlink($tmp);

exit(($data['status'] ?? '') === 'success' ? 0 : 1);

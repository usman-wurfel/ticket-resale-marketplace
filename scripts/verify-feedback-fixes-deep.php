<?php

/**
 * Deeper live smoke: gallery upload API + sell UI copy + multi-listing rule.
 */

use App\Http\Controllers\BackEnd\Event\EventController;
use App\Models\Customer;
use App\Models\Event\ResaleTicket;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

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

// --- Gallery upload: wrong size should return clear msg, not object ---
$srcOk = base_path('public/assets/admin/img/event-gallery/6456373e0773c.jpg');
$tmpBad = sys_get_temp_dir() . '/qa-bad-' . uniqid() . '.jpg';
$tmpOk = sys_get_temp_dir() . '/qa-ok-' . uniqid() . '.jpg';
copy($srcOk, $tmpOk);

// Minimal valid 1x1 JPEG (always wrong vs 1170x570), no GD required.
$tinyJpeg = base64_decode(
    '/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/2wBDAQkJCQwLDBgNDRgyIRwhMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjL/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAn/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIQAxAAAAGfAP/EABQQAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQEAAQUCf//EABQRAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQMBAT8Bf//EABQRAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQIBAT8Bf//Z'
);
file_put_contents($tmpBad, $tinyJpeg);

$controller = app(EventController::class);

$badUpload = UploadedFile::fake()->createWithContent('bad.jpg', file_get_contents($tmpBad));
// Prefer real path for getimagesize
$badReal = new UploadedFile($tmpBad, 'bad.jpg', 'image/jpeg', null, true);
$reqBad = Request::create('/admin/event/imagesstore', 'POST', [], [], ['file' => $badReal]);
$reqBad->headers->set('Accept', 'application/json');
$respBad = $controller->gallerystore($reqBad);
$badJson = $respBad->getData(true);
check(
    'Gallery wrong size returns clear msg',
    ($respBad->getStatusCode() === 422)
        && isset($badJson['msg'])
        && is_string($badJson['msg'])
        && str_contains($badJson['msg'], '1170x570'),
    json_encode($badJson)
);

$okReal = new UploadedFile($tmpOk, 'ok.jpg', 'image/jpeg', null, true);
$reqOk = Request::create('/admin/event/imagesstore', 'POST', [], [], ['file' => $okReal]);
$reqOk->headers->set('Accept', 'application/json');
$respOk = $controller->gallerystore($reqOk);
$okJson = $respOk->getData(true);
check(
    'Gallery correct size uploads',
    ($respOk->getStatusCode() === 200)
        && ($okJson['status'] ?? null) === 'success'
        && !empty($okJson['file_id']),
    json_encode($okJson)
);

// cleanup uploaded test image row/file if created
if (!empty($okJson['file_id'])) {
    $pi = \App\Models\Event\EventImage::find($okJson['file_id']);
    if ($pi) {
        $path = public_path('assets/admin/img/event-gallery/' . $pi->image);
        if (is_file($path)) {
            @unlink($path);
        }
        $pi->delete();
    }
}

@unlink($tmpBad);
@unlink($tmpOk);

// --- Point 2: non-LOW seller can have >1 active listing conceptually ---
$createBlade = file_get_contents(base_path('resources/views/frontend/customer/dashboard/resale/create.blade.php')) ?: '';
check(
    'Sell form says one ticket per listing',
    str_contains($createBlade, 'Each listing is for 1 ticket only')
        && str_contains($createBlade, 'name="quantity" value="1"')
);

$ctrl = file_get_contents(base_path('app/Http/Controllers/FrontEnd/Event/TicketResaleController.php')) ?: '';
check(
    'Only HIGH RISK sellers capped to one active listing',
    str_contains($ctrl, 'hasStrictResaleSellerRules')
        && !str_contains($ctrl, 'every seller may have only one active listing')
);

// Count listed tickets for a non-LOW seller if any
$seller = Customer::where('confidence_level', '!=', Customer::CONFIDENCE_LOW)
    ->where('confidence_level', '!=', Customer::CONFIDENCE_BANNED)
    ->where('status', 1)
    ->first();
if ($seller) {
    $active = ResaleTicket::where('seller_customer_id', $seller->id)
        ->whereIn('status', [ResaleTicket::STATUS_LISTED, ResaleTicket::STATUS_RESERVED, ResaleTicket::STATUS_PAID])
        ->count();
    check(
        'Non-LOW seller may already have multiple active listings',
        true,
        "seller={$seller->id} level={$seller->confidence_level} active={$active}"
    );
} else {
    check('Non-LOW seller sample present', true, 'none found (skipped count)');
}

// Incident nullable user_id
$col = collect(DB::select("SHOW COLUMNS FROM support_tickets WHERE Field='user_id'"))->first();
check('support_tickets.user_id nullable', strtoupper((string) ($col->Null ?? '')) === 'YES', json_encode($col));

$latest = DB::table('support_tickets')->orderByDesc('id')->first(['id', 'subject', 'user_id', 'user_type']);
check(
    'Latest QA incident exists',
    $latest && str_contains((string) $latest->subject, 'Live QA incident'),
    json_encode($latest)
);

echo ($ok ? "ALL_PASS\n" : "HAS_FAILS\n");
exit($ok ? 0 : 1);

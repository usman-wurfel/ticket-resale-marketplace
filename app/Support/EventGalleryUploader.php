<?php

namespace App\Support;

use App\Models\Event\EventImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Throwable;

class EventGalleryUploader
{
    public const TARGET_WIDTH = 1170;
    public const TARGET_HEIGHT = 570;

    public static function store(UploadedFile $file, $eventId = null): JsonResponse
    {
        try {
            $uploadPath = public_path('assets/admin/img/event-gallery/');
            $ensure = self::ensureWritableDirectory($uploadPath);
            if ($ensure !== true) {
                return response()->json([
                    'status' => 'error',
                    'msg' => $ensure,
                ], 500);
            }

            $size = @getimagesize($file->getPathname());
            if (!$size || empty($size[0]) || empty($size[1])) {
                return response()->json([
                    'status' => 'error',
                    'msg' => 'Could not read image dimensions. Please upload a valid JPG or PNG.',
                ], 422);
            }

            $width = (int) $size[0];
            $height = (int) $size[1];
            $filename = uniqid() . '.jpg';
            $dest = $uploadPath . $filename;

            if ($width === self::TARGET_WIDTH && $height === self::TARGET_HEIGHT) {
                if (!self::storeFile($file, $dest)) {
                    return response()->json([
                        'status' => 'error',
                        'msg' => 'Could not save the uploaded image. Please check server folder permissions for event-gallery.',
                    ], 500);
                }
            } else {
                if (!self::resizeToTarget($file->getPathname(), $dest, $size['mime'] ?? null)) {
                    return response()->json([
                        'status' => 'error',
                        'msg' => 'The image must be 1170x570 pixels (got ' . $width . 'x' . $height . '). Auto-resize failed — please upload an exact 1170x570 JPG/PNG.',
                    ], 422);
                }
            }

            $pi = new EventImage();
            $pi->event_id = $eventId;
            $pi->image = $filename;
            $pi->save();

            return response()->json([
                'status' => 'success',
                'file_id' => $pi->id,
            ]);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'status' => 'error',
                'msg' => 'Upload failed: ' . (str_contains($e->getMessage(), 'Unable to write')
                    ? 'server cannot write to the event-gallery folder. Permissions have been reported.'
                    : 'please try again with a JPG/PNG image.'),
            ], 500);
        }
    }

    private static function ensureWritableDirectory(string $dir)
    {
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return 'Gallery upload folder is missing and could not be created.';
        }

        @chmod($dir, 0775);

        if (!is_writable($dir)) {
            @chmod($dir, 0777);
        }

        if (!is_writable($dir)) {
            return 'Gallery upload folder is not writable on the server. Please fix permissions on public/assets/admin/img/event-gallery.';
        }

        return true;
    }

    private static function storeFile(UploadedFile $file, string $dest): bool
    {
        try {
            $file->move(dirname($dest), basename($dest));
            return is_file($dest);
        } catch (Throwable $e) {
            // Fallback when move() throws due to directory permissions quirks.
            $contents = @file_get_contents($file->getPathname());
            if ($contents === false) {
                return false;
            }
            return @file_put_contents($dest, $contents) !== false;
        }
    }

    private static function resizeToTarget(string $sourcePath, string $destPath, ?string $mime): bool
    {
        if (!function_exists('imagecreatetruecolor')) {
            return false;
        }

        $src = null;
        $mime = strtolower((string) $mime);
        if ($mime === 'image/png' || str_ends_with(strtolower($sourcePath), '.png')) {
            $src = @imagecreatefrompng($sourcePath);
        } elseif ($mime === 'image/webp' && function_exists('imagecreatefromwebp')) {
            $src = @imagecreatefromwebp($sourcePath);
        } else {
            $src = @imagecreatefromjpeg($sourcePath);
            if (!$src) {
                $src = @imagecreatefrompng($sourcePath);
            }
        }

        if (!$src) {
            return false;
        }

        $srcW = imagesx($src);
        $srcH = imagesy($src);
        $dst = imagecreatetruecolor(self::TARGET_WIDTH, self::TARGET_HEIGHT);
        if (!$dst) {
            imagedestroy($src);
            return false;
        }

        // Cover-fit crop to exact gallery size.
        $scale = max(self::TARGET_WIDTH / $srcW, self::TARGET_HEIGHT / $srcH);
        $cropW = (int) round(self::TARGET_WIDTH / $scale);
        $cropH = (int) round(self::TARGET_HEIGHT / $scale);
        $srcX = (int) max(0, ($srcW - $cropW) / 2);
        $srcY = (int) max(0, ($srcH - $cropH) / 2);

        imagecopyresampled(
            $dst,
            $src,
            0,
            0,
            $srcX,
            $srcY,
            self::TARGET_WIDTH,
            self::TARGET_HEIGHT,
            $cropW,
            $cropH
        );

        $ok = imagejpeg($dst, $destPath, 90);
        imagedestroy($src);
        imagedestroy($dst);

        return (bool) $ok && is_file($destPath);
    }
}

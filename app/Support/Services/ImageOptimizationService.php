<?php

namespace App\Support\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class ImageOptimizationService
{
    /**
     * Optimize, resize (max 1600px dimension), convert to WebP, and store the uploaded image.
     * PRD 204.8: "kompres/resize gambar saat upload (maks 1600 px, WebP)".
     *
     * @param  int  $quality  (1-100)
     * @return string Stored file relative path
     */
    public function optimizeAndStore(
        UploadedFile $file,
        string $directory = 'uploads',
        string $disk = 'public',
        int $maxDimension = 1600,
        int $quality = 82
    ): string {
        // Fallback directly if GD or WebP is unavailable
        if (! extension_loaded('gd') || ! function_exists('imagewebp')) {
            return (string) $file->store($directory, $disk);
        }

        try {
            $contents = file_get_contents($file->getRealPath());
            if ($contents === false) {
                return (string) $file->store($directory, $disk);
            }

            $srcImage = @imagecreatefromstring($contents);
            if (! $srcImage) {
                return (string) $file->store($directory, $disk);
            }

            $origWidth = imagesx($srcImage);
            $origHeight = imagesy($srcImage);

            // Determine if downscaling is required
            if ($origWidth > $maxDimension || $origHeight > $maxDimension) {
                $ratio = min($maxDimension / $origWidth, $maxDimension / $origHeight);
                $targetWidth = max(1, (int) round($origWidth * $ratio));
                $targetHeight = max(1, (int) round($origHeight * $ratio));

                $targetImage = imagecreatetruecolor($targetWidth, $targetHeight);
                if (! $targetImage) {
                    imagedestroy($srcImage);

                    return (string) $file->store($directory, $disk);
                }

                // Preserve alpha channel transparency for PNG/WebP inputs
                imagealphablending($targetImage, false);
                imagesavealpha($targetImage, true);

                imagecopyresampled(
                    $targetImage,
                    $srcImage,
                    0,
                    0,
                    0,
                    0,
                    $targetWidth,
                    $targetHeight,
                    $origWidth,
                    $origHeight
                );

                imagedestroy($srcImage);
                $finalImage = $targetImage;
            } else {
                $finalImage = $srcImage;
                // Preserve transparency
                imagealphablending($finalImage, false);
                imagesavealpha($finalImage, true);
            }

            // Capture WebP output stream
            ob_start();
            $success = imagewebp($finalImage, null, $quality);
            $webpData = ob_get_clean();
            imagedestroy($finalImage);

            if (! $success || empty($webpData)) {
                return (string) $file->store($directory, $disk);
            }

            $filename = Str::random(40).'.webp';
            $targetPath = trim($directory, '/').'/'.$filename;

            Storage::disk($disk)->put($targetPath, $webpData);

            return $targetPath;
        } catch (Throwable $e) {
            Log::warning('Image optimization failed, falling back to standard store: '.$e->getMessage());

            return (string) $file->store($directory, $disk);
        }
    }
}

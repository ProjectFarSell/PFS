<?php

namespace App\Services\Security;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SafeImage
{
    public function store(UploadedFile $file, string $directory, string $disk, string $field = 'image', int $maxBytes = 4194304): string
    {
        $info = @getimagesize($file->getRealPath());
        if (! $info || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)
            || $info[0] > 8192 || $info[1] > 8192 || $info[0] * $info[1] > 16000000 || $file->getSize() > $maxBytes) {
            throw ValidationException::withMessages([$field => 'Use a JPG, PNG or WebP photo up to '.($maxBytes / 1048576).' MB and 16 megapixels.']);
        }
        $image = @imagecreatefromstring(file_get_contents($file->getRealPath()));
        if (! $image) {
            throw ValidationException::withMessages([$field => 'This photo could not be processed.']);
        }
        imagesavealpha($image, true);
        // GD writes fresh pixel data, without carrying over EXIF, GPS or trailing content.
        ob_start();
        try {
            $ok = match ($info[2]) {
                IMAGETYPE_JPEG => imagejpeg($image, null, 90),
                IMAGETYPE_PNG => imagepng($image, null, 6),
                IMAGETYPE_WEBP => imagewebp($image, null, 85),
            };
            $bytes = ob_get_contents();
        } finally {
            ob_end_clean();
            imagedestroy($image);
        }
        if (! $ok || $bytes === '') {
            throw ValidationException::withMessages([$field => 'This photo could not be saved.']);
        }
        $extension = match ($info[2]) {
            IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'
        };
        $path = $directory.'/'.Str::uuid().'.'.$extension;
        if (! Storage::disk($disk)->put($path, $bytes)) {
            throw ValidationException::withMessages([$field => 'Photo storage is unavailable. Please try again.']);
        }

        return $path;
    }
}

<?php

namespace Tests\Feature;

use App\Services\Security\SafeImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SafeImageTest extends TestCase
{
    public function test_reencoding_keeps_pixels_and_removes_appended_content(): void
    {
        Storage::fake('local');
        foreach (['jpg', 'png', 'webp'] as $extension) {
            $image = UploadedFile::fake()->image('photo.'.$extension, 20, 30);
            file_put_contents($image->getRealPath(), 'PRIVATE-GPS-METADATA-OR-PAYLOAD', FILE_APPEND);
            $path = app(SafeImage::class)->store($image, 'return-evidence', 'local');
            $bytes = Storage::disk('local')->get($path);
            $this->assertStringNotContainsString('PRIVATE-GPS', $bytes);
            $info = getimagesizefromstring($bytes);
            $this->assertSame(20, $info[0]);
            $this->assertSame(30, $info[1]);
            $this->assertStringEndsWith('.'.$extension, $path);
        }
    }

    public function test_oversized_dimensions_and_forged_images_are_rejected_before_storage(): void
    {
        Storage::fake('local');
        foreach ([UploadedFile::fake()->image('wide.png', 8193, 1), UploadedFile::fake()->create('fake.webp', 10, 'image/webp')] as $file) {
            try {
                app(SafeImage::class)->store($file, 'return-evidence', 'local');
                $this->fail('Invalid photo should not be saved.');
            } catch (ValidationException) {
                $this->assertCount(0, Storage::disk('local')->allFiles());
            }
        }
    }
}

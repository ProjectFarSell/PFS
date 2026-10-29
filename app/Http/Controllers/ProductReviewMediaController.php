<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\ProductReviewMedia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProductReviewMediaController extends Controller
{
    public function show(Request $request, ProductReviewMedia $media): BinaryFileResponse
    {
        $media->load('review.product.shop');
        $review = $media->review;
        $product = $review->product;
        $user = $request->user();
        $isPublic = $product?->is_active && $product->shop?->is_active;
        $canReview = $user && ($user->role === UserRole::Admin || $review->buyer_id === $user->id
            || ($user->role === UserRole::Seller && $product?->shop?->user_id === $user->id));

        abort_unless($isPublic || $canReview, 404);
        $disk = Storage::disk('local');
        abort_unless(str_starts_with($media->path, 'product-review-media/') && $disk->exists($media->path), 404);

        return response()->file($disk->path($media->path), [
            'Content-Type' => $media->mime_type,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}

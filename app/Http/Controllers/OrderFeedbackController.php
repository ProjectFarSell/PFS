<?php

namespace App\Http\Controllers;

use App\Enums\FulfillmentStatus;
use App\Enums\UserRole;
use App\Models\Fulfillment;
use App\Models\Order;
use App\Models\ProductReview;
use App\Models\RiderDeliveryRating;
use App\Models\ShipmentFeedback;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class OrderFeedbackController extends Controller
{
    public function create(Request $request, Fulfillment $fulfillment): View
    {
        $this->authorizeFeedback($request, $fulfillment);
        $fulfillment->load(['order', 'items.product', 'items.review.media', 'rider.user', 'riderRating', 'feedback']);

        return view('orders.feedback', [
            'fulfillment' => $fulfillment,
            'riderTags' => RiderDeliveryRating::TAGS,
            'mediaLimits' => $this->mediaLimits(),
            'hasPendingFeedback' => $fulfillment->items->contains(fn ($item) => ! $item->review)
                || ! $fulfillment->feedback
                || ($fulfillment->rider_id !== null && ! $fulfillment->riderRating),
        ]);
    }

    public function store(Request $request, Fulfillment $fulfillment): RedirectResponse
    {
        $this->authorizeFeedback($request, $fulfillment);
        $fulfillment->load(['items.review', 'riderRating', 'feedback']);

        $rules = [
            'reviews' => ['sometimes', 'array'],
            'reviews.*' => ['array:rating,feedback,is_anonymous,media'],
            'reviews.*.feedback' => ['nullable', 'string', 'max:2000'],
            'reviews.*.is_anonymous' => ['sometimes', 'boolean'],
            'reviews.*.media' => ['sometimes', 'array', 'max:4'],
            'reviews.*.media.*' => ['bail', 'file', 'mimetypes:image/jpeg,image/png,image/webp,video/mp4,video/webm', 'max:20480',
                function ($attribute, $file, $fail) {
                    if (str_starts_with((string) $file->getMimeType(), 'image/') && $file->getSize() > 4 * 1024 * 1024) {
                        $fail('Each review photo must be 4 MB or smaller.');
                    }
                },
            ],
        ];

        foreach ($fulfillment->items as $item) {
            $rules['reviews.'.$item->id.'.rating'] = [$item->review ? 'sometimes' : 'required', 'integer', 'between:1,5'];
        }
        if (! $fulfillment->feedback) {
            $rules['seller_service_rating'] = ['required', 'integer', 'between:1,5'];
            $rules['delivery_speed_rating'] = ['required', 'integer', 'between:1,5'];
        }
        if ($fulfillment->rider_id !== null && ! $fulfillment->riderRating) {
            $rules['rider_rating'] = ['required', 'integer', 'between:1,5'];
            $rules['rider_feedback'] = ['nullable', 'string', 'max:1000'];
            $rules['rider_tags'] = ['sometimes', 'array', 'max:9'];
            $rules['rider_tags.*'] = ['string', 'distinct', Rule::in(RiderDeliveryRating::TAGS)];
        }

        $data = $request->validate($rules);
        $itemIds = array_map('strval', $fulfillment->items->modelKeys());
        if (array_diff(array_map('strval', array_keys($data['reviews'] ?? [])), $itemIds)) {
            throw ValidationException::withMessages(['reviews' => 'Only items from this shipment can be reviewed.']);
        }

        foreach ($data['reviews'] ?? [] as $itemId => $review) {
            $files = collect($review['media'] ?? []);
            $videos = $files->filter(fn ($file) => str_starts_with((string) $file->getMimeType(), 'video/'))->count();
            if ($videos > 1 || $files->count() - $videos > 3) {
                throw ValidationException::withMessages(['reviews.'.$itemId.'.media' => 'Add up to three photos and one video per product.']);
            }
        }

        $uploads = [];
        $allPaths = [];
        try {
            foreach ($fulfillment->items->filter(fn ($item) => ! $item->review) as $item) {
                foreach ($data['reviews'][$item->id]['media'] ?? [] as $file) {
                    $path = $file->store('product-review-media', 'local');
                    if (! $path) {
                        throw new RuntimeException('Review attachments could not be saved.');
                    }
                    $allPaths[] = $path;
                    $uploads[$item->id][] = ['path' => $path, 'mime_type' => $file->getMimeType()];
                }
            }

            $usedPaths = DB::transaction(function () use ($request, $fulfillment, $data, $uploads) {
                // Follow the same lock order as fulfillment transitions.
                $order = Order::query()->lockForUpdate()->findOrFail($fulfillment->order_id);
                abort_unless($order->user_id === $request->user()->id, 404);
                $part = Fulfillment::query()->lockForUpdate()->findOrFail($fulfillment->id);
                abort_unless($part->status === FulfillmentStatus::Completed, 422);
                $used = [];

                foreach ($part->items()->get() as $item) {
                    if ($item->review()->exists()) continue;
                    $entry = $data['reviews'][$item->id];
                    $review = ProductReview::query()->create([
                        'order_item_id' => $item->id,
                        'product_id' => $item->product_id,
                        'buyer_id' => $request->user()->id,
                        'rating' => $entry['rating'],
                        'feedback' => $entry['feedback'] ?? null,
                        'is_anonymous' => filter_var($entry['is_anonymous'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    ]);
                    foreach ($uploads[$item->id] ?? [] as $upload) {
                        $review->media()->create($upload);
                        $used[] = $upload['path'];
                    }
                }

                if (! $part->feedback()->exists()) {
                    ShipmentFeedback::query()->create([
                        'fulfillment_id' => $part->id,
                        'buyer_id' => $request->user()->id,
                        'seller_service_rating' => $data['seller_service_rating'],
                        'delivery_speed_rating' => $data['delivery_speed_rating'],
                    ]);
                }
                if ($part->rider_id !== null && ! $part->riderRating()->exists()) {
                    RiderDeliveryRating::query()->create([
                        'fulfillment_id' => $part->id,
                        'buyer_id' => $request->user()->id,
                        'rider_profile_id' => $part->rider_id,
                        'rating' => $data['rider_rating'],
                        'feedback' => $data['rider_feedback'] ?? null,
                        'tags' => $data['rider_tags'] ?? [],
                    ]);
                }

                return $used;
            }, 3);
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($allPaths);
            throw $exception;
        }

        // A repeated submission may arrive after another request already saved it.
        Storage::disk('local')->delete(array_values(array_diff($allPaths, $usedPaths)));

        return redirect()->route('orders.show', $fulfillment->order_id)->with('status', 'Thank you! Your product and delivery feedback has been saved.');
    }

    private function authorizeFeedback(Request $request, Fulfillment $fulfillment): void
    {
        abort_unless($request->user()->role === UserRole::Buyer, 403);
        abort_unless($fulfillment->order()->where('user_id', $request->user()->id)->exists(), 404);
        abort_unless($fulfillment->status === FulfillmentStatus::Completed, 422, 'Confirm receipt of the shipment before leaving a review.');
    }

    private function mediaLimits(): array
    {
        $uploadLimit = ini_parse_quantity((string) ini_get('upload_max_filesize'));
        $postLimit = ini_parse_quantity((string) ini_get('post_max_size'));
        $photoBytes = min(4 * 1024 * 1024, $uploadLimit > 0 ? $uploadLimit : PHP_INT_MAX);
        $videoBytes = min(20 * 1024 * 1024, $uploadLimit > 0 ? $uploadLimit : PHP_INT_MAX);
        $totalBytes = min(100 * 1024 * 1024, $postLimit > 0 ? max(0, $postLimit - 128 * 1024) : PHP_INT_MAX);

        return [
            'photoBytes' => $photoBytes,
            'videoBytes' => $videoBytes,
            'totalBytes' => $totalBytes,
            'maxFiles' => (int) ini_get('max_file_uploads') ?: 20,
            'photoLabel' => round($photoBytes / 1024 / 1024, 1).' MB',
            'videoLabel' => round($videoBytes / 1024 / 1024, 1).' MB',
            'totalLabel' => round($totalBytes / 1024 / 1024, 1).' MB',
        ];
    }
}

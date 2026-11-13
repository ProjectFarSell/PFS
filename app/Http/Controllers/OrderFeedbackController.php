<?php

namespace App\Http\Controllers;

use App\Enums\FulfillmentStatus;
use App\Enums\UserRole;
use App\Models\Fulfillment;
use App\Models\RiderDeliveryRating;
use App\Services\Orders\ShipmentFeedbackService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

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
        app(ShipmentFeedbackService::class)->submit($request, $fulfillment);

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

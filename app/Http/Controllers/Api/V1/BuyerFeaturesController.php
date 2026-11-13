<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\MobileRequest;
use App\Models\BuyerNotification;
use App\Models\Conversation;
use App\Models\Fulfillment;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\ProductReviewMedia;
use App\Models\ReturnRequest;
use App\Models\RiderDeliveryRating;
use App\Services\Auth\GoogleAccounts;
use App\Services\Chat\ChatMessages;
use App\Services\Mobile\GoogleIdentity;
use App\Services\Mobile\MobileAuthentication;
use App\Services\Mobile\MobileViews;
use App\Services\Mobile\PublicIds;
use App\Services\Mobile\Replay;
use App\Services\Mobile\Reply;
use App\Services\Orders\CancelOrder;
use App\Services\Orders\ReturnRequestService;
use App\Services\Orders\ShipmentFeedbackService;
use App\Services\Security\AccountSecurity;
use App\Services\Security\SafeImage;
use App\Support\PrivacyNotice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class BuyerFeaturesController extends Controller
{
    public function google(MobileRequest $request, GoogleIdentity $google, MobileAuthentication $auth)
    {
        $data = $request->validate(['id_token' => ['required', 'string', 'min:32', 'max:10000'], 'privacy_consent' => ['required', 'accepted'], 'privacy_notice_version' => ['required', 'in:'.PrivacyNotice::VERSION]]);
        $profile = $google->verify($data['id_token']);
        $user = app(GoogleAccounts::class)->resolve($profile, 'buyer');
        $auth->eligible($user, 'buyer');
        $user->forceFill(['privacy_accepted_at' => now(), 'privacy_notice_version' => PrivacyNotice::VERSION])->save();

        return app(AccountSecurity::class)->requiresOtp($user) ? Reply::data($auth->challenge($user, 'buyer'), 202) : Reply::data($auth->issue($user, 'buyer'));
    }

    private function conversation($request, $id): Conversation
    {
        return Conversation::where('buyer_id', $request->user()->id)->where('public_id', $id)->firstOrFail();
    }

    private function message($message, $user): array
    {
        return ['id' => PublicIds::id($message), 'body' => $message->body, 'mine' => $message->sender_id === $user->id, 'sender' => $message->sender?->name ?? 'User', 'created_at' => MobileViews::time($message->created_at)];
    }

    private function thread(Conversation $thread, $user): array
    {
        $thread->loadMissing(['shop', 'product', 'latestMessage.sender']);

        return ['id' => PublicIds::id($thread), 'shop_name' => $thread->shop?->name ?? 'Shop', 'product_name' => $thread->product?->name, 'unread' => (int) ($thread->unread_count ?? 0), 'last_message' => $thread->latestMessage ? $this->message($thread->latestMessage, $user) : null];
    }

    public function conversations(MobileRequest $request)
    {
        $query = Conversation::where('buyer_id', $request->user()->id)->with(['shop', 'product', 'latestMessage.sender'])->withCount(['messages as unread_count' => fn ($q) => $q->whereNull('read_at')->where('sender_id', '!=', $request->user()->id)])->orderByDesc('updated_at')->orderByDesc('id');

        return Reply::page($query->cursorPaginate($request->integer('limit', 20)), fn ($thread) => $this->thread($thread, $request->user()));
    }

    public function start(MobileRequest $request, string $product_id)
    {
        $product = PublicIds::find(Product::class, $product_id);
        abort_unless($product->is_active && $product->shop?->is_active, 404);
        $thread = Conversation::firstOrCreate(['buyer_id' => $request->user()->id, 'shop_id' => $product->shop_id, 'product_id' => $product->id]);

        return Reply::data($this->thread($thread, $request->user()), 201);
    }

    public function messages(MobileRequest $request, string $conversation_id)
    {
        $thread = $this->conversation($request, $conversation_id);
        $page = $thread->messages()->with('sender')->orderByDesc('id')->cursorPaginate($request->integer('limit', 30));
        // Mark only fetched messages, never an unseen concurrently arriving message.
        $thread->messages()->whereIn('id', $page->getCollection()->modelKeys())->where('sender_id', '!=', $request->user()->id)->whereNull('read_at')->update(['read_at' => now()]);

        return Reply::page($page, fn ($message) => $this->message($message, $request->user()));
    }

    public function send(MobileRequest $request, string $conversation_id, Replay $replay)
    {
        $thread = $this->conversation($request, $conversation_id);
        $data = $request->validate(['body' => ['required', 'string', 'max:4000']]);
        if (trim($data['body']) === '') {
            throw ValidationException::withMessages(['body' => 'Enter a message.']);
        }

        return $replay->run($request, $data, fn () => Reply::data($this->message(app(ChatMessages::class)->send($thread, $request->user(), $data['body']), $request->user()), 201));
    }

    public static function review(ProductReview $review): array
    {
        $review->loadMissing(['buyer', 'media']);

        return ['id' => PublicIds::id($review), 'rating' => $review->rating, 'feedback' => $review->feedback, 'author' => $review->authorName(), 'is_anonymous' => $review->is_anonymous, 'created_at' => MobileViews::time($review->created_at), 'media' => $review->media->map(fn ($m) => ['id' => PublicIds::id($m), 'mime_type' => $m->mime_type, 'url' => url('/api/v1/review-media/'.PublicIds::id($m))])->all()];
    }

    public function reviews(MobileRequest $request, string $product_id)
    {
        $product = PublicIds::find(Product::class, $product_id);
        abort_unless($product->is_active && $product->shop?->is_active, 404);

        return Reply::page(ProductReview::where('product_id', $product->id)->with(['buyer', 'media'])->orderByDesc('id')->cursorPaginate($request->integer('limit', 20)), fn ($review) => self::review($review));
    }

    public function media(MobileRequest $request, string $media_id)
    {
        $media = PublicIds::find(ProductReviewMedia::class, $media_id);
        abort_unless($media->review?->product?->is_active && $media->review->product->shop?->is_active, 404);
        abort_unless(str_starts_with($media->path, 'product-review-media/') && Storage::disk('local')->exists($media->path), 404);

        return response()->file(Storage::disk('local')->path($media->path), ['Content-Type' => $media->mime_type, 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    private function part($request, $id): Fulfillment
    {
        return Fulfillment::where('public_id', $id)->whereHas('order', fn ($q) => $q->where('user_id', $request->user()->id))->firstOrFail();
    }

    private function feedbackView(Fulfillment $part): array
    {
        $part->loadMissing(['items.review.media', 'items.review.buyer', 'feedback', 'riderRating']);

        $limits = app(ShipmentFeedbackService::class)->mediaLimits();

        return ['media_limits' => ['photo_bytes' => $limits['photoBytes'], 'video_bytes' => $limits['videoBytes'], 'total_bytes' => $limits['totalBytes']], 'shipment_id' => PublicIds::id($part), 'items' => $part->items->map(fn ($item) => ['id' => PublicIds::id($item), 'name' => $item->name, 'sku' => $item->product_sku, 'review' => $item->review ? self::review($item->review) : null])->all(), 'seller_service_rating' => $part->feedback?->seller_service_rating, 'delivery_speed_rating' => $part->feedback?->delivery_speed_rating, 'needs_rider_rating' => $part->rider_id !== null && ! $part->riderRating, 'rider_rating' => $part->riderRating?->rating, 'rider_tags' => RiderDeliveryRating::TAGS];
    }

    public function feedback(MobileRequest $request, string $shipment_id)
    {
        $part = $this->part($request, $shipment_id);
        app(ShipmentFeedbackService::class)->authorizeFeedback($request, $part);

        return Reply::data($this->feedbackView($part));
    }

    public function submitFeedback(MobileRequest $request, string $shipment_id, Replay $replay)
    {
        $part = $this->part($request, $shipment_id);
        app(ShipmentFeedbackService::class)->authorizeFeedback($request, $part);
        $request->validate(['reviews' => ['sometimes', 'array'], 'reviews.*' => ['array'], 'reviews.*.media' => ['sometimes', 'array', 'max:4']]);
        $part->load('items');
        $mapped = [];
        $files = [];
        foreach ($request->input('reviews', []) as $id => $entry) {
            $item = $part->items->firstWhere('public_id', $id);
            if (! $item) {
                throw ValidationException::withMessages(['reviews' => 'Choose only items from this shipment.']);
            }
            $mapped[$item->id] = $entry;
            $files[$item->id]['media'] = $request->file('reviews.'.$id.'.media', []);
        }
        $adapted = Request::createFrom($request);
        $adapted->merge(['reviews' => $mapped]);
        $adapted->files->set('reviews', $files);
        $paths = [];
        try {
            return $replay->run($request, $request->all(), function () use ($adapted, $part, &$paths) {
                $paths = app(ShipmentFeedbackService::class)->submit($adapted, $part);

                return Reply::data($this->feedbackView($part->fresh()));
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($paths);
            throw $exception;
        }
    }

    public function cancel(MobileRequest $request, string $order_id, Replay $replay)
    {
        $order = $request->user()->orders()->where('public_id', $order_id)->firstOrFail();
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:1000']]);

        return $replay->run($request, $data, function () use ($order, $request, $data) {
            app(CancelOrder::class)->handle($order, $request->user(), $data['reason']);

            return Reply::data(MobileViews::order($order->fresh()));
        });
    }

    private function returnView(ReturnRequest $case): array
    {
        $case->loadMissing(['item.order', 'events']);

        return ['id' => PublicIds::id($case), 'item_id' => PublicIds::id($case->item), 'item_name' => $case->item->name, 'order_number' => $case->item->order->number, 'quantity' => $case->quantity, 'reason' => $case->reason, 'details' => $case->details, 'status' => $case->status, 'label' => $case->label(), 'requested_amount' => MobileViews::cents($case->requested_amount), 'approved_amount' => $case->approved_amount === null ? null : MobileViews::cents($case->approved_amount), 'return_required' => $case->return_required, 'return_received_at' => MobileViews::time($case->return_received_at), 'refunded_at' => MobileViews::time($case->refunded_at), 'refund_reference' => $case->refund_reference, 'has_evidence' => filled($case->evidence_path), 'events' => $case->events->map(fn ($event) => ['status' => $event->status, 'occurred_at' => MobileViews::time($event->created_at), 'note' => $event->note])->all()];
    }

    private function returnsQuery($request)
    {
        return ReturnRequest::whereHas('item.order', fn ($q) => $q->where('user_id', $request->user()->id));
    }

    public function returns(MobileRequest $request)
    {
        return Reply::page($this->returnsQuery($request)->with(['item.order', 'events'])->orderByDesc('id')->cursorPaginate($request->integer('limit', 20)), fn ($case) => $this->returnView($case));
    }

    public function submitReturn(MobileRequest $request, string $item_id, Replay $replay)
    {
        $item = OrderItem::where('public_id', $item_id)->whereHas('order', fn ($q) => $q->where('user_id', $request->user()->id))->firstOrFail();
        $data = $request->validate(['quantity' => ['required', 'integer', 'min:1'], 'reason' => ['required', 'in:damaged,incorrect,missing,other'], 'details' => ['required', 'string', 'min:10', 'max:2000'], 'evidence' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096']]);
        $path = null;
        try {
            return $replay->run($request, $data, function () use ($request, $item, $data, &$path) {
                $path = $request->hasFile('evidence') ? app(SafeImage::class)->store($request->file('evidence'), 'return-evidence', 'local', 'evidence') : null;
                $case = app(ReturnRequestService::class)->submit($item, $request->user(), [...$data, 'evidence_path' => $path]);

                return Reply::data($this->returnView($case->fresh()), 201);
            });
        } catch (\Throwable $exception) {
            if ($path) {
                Storage::disk('local')->delete($path);
            } throw $exception;
        }
    }

    public function evidence(MobileRequest $request, string $return_id)
    {
        $case = $this->returnsQuery($request)->where('public_id', $return_id)->firstOrFail();
        abort_unless($case->evidence_path && str_starts_with($case->evidence_path, 'return-evidence/') && Storage::disk('local')->exists($case->evidence_path), 404);

        return Storage::disk('local')->response($case->evidence_path, null, ['Cache-Control' => 'private, no-store']);
    }

    private function inbox($request)
    {
        return BuyerNotification::where('user_id', $request->user()->id)->whereHas('order', fn ($q) => $q->where('user_id', $request->user()->id));
    }

    private function notificationView(BuyerNotification $entry): array
    {
        return ['id' => PublicIds::id($entry), 'title' => $entry->title, 'body' => $entry->message, 'is_read' => $entry->read_at !== null, 'created_at' => MobileViews::time($entry->created_at), 'order_id' => PublicIds::id($entry->order), 'order_number' => $entry->order->number];
    }

    public function notifications(MobileRequest $request)
    {
        return Reply::page($this->inbox($request)->with('order')->orderByDesc('id')->cursorPaginate($request->integer('limit', 20)), fn ($entry) => $this->notificationView($entry));
    }

    public function read(MobileRequest $request, string $notification_id)
    {
        $entry = $this->inbox($request)->where('public_id', $notification_id)->firstOrFail();
        if (! $entry->read_at) {
            $entry->update(['read_at' => now()]);
        }

        return Reply::data($this->notificationView($entry));
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\FulfillmentStatus;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\MobileRequest;
use App\Models\Fulfillment;
use App\Models\MobileQuote;
use App\Models\User;
use App\Services\Mobile\ApiFailure;
use App\Services\Mobile\MobileCartService;
use App\Services\Mobile\MobileCheckout;
use App\Services\Mobile\MobileViews;
use App\Services\Mobile\Replay;
use App\Services\Mobile\Reply;
use App\Services\Orders\FulfillmentService;
use App\Services\Orders\PlaceOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function quote(MobileRequest $request, MobileCartService $carts, MobileCheckout $checkout)
    {
        $data = $request->validated();

        return DB::transaction(function () use ($request, $data, $carts, $checkout) {
            User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $cart = $carts->get($request->user());
            $carts->version($cart, $data['cart_version']);
            $address = $request->user()->addresses()->where('public_id', $data['address_id'])->lockForUpdate()->firstOrFail();
            $snapshot = $checkout->snapshot($cart, $address, $carts->hydrate($cart));
            $quote = MobileQuote::create(['public_id' => (string) Str::uuid(), 'user_id' => $request->user()->id, 'address_id' => $address->id, 'snapshot' => $snapshot, 'expires_at' => now()->addMinutes(config('mobile.quote_minutes'))]);

            return Reply::data($checkout->summary($snapshot, $address, $quote->public_id, $quote->expires_at), 201);
        }, 3);
    }

    public function store(MobileRequest $request, MobileCartService $carts, MobileCheckout $checkout, Replay $replay)
    {
        $data = $request->validated();
        // Scope ownership before replay, but check expiry/consumption only for a NEW request.
        MobileQuote::where('user_id', $request->user()->id)->where('public_id', $data['quote_id'])->firstOrFail();

        return $replay->run($request, $data, function () use ($request, $data, $carts, $checkout) {
            $quote = MobileQuote::where('user_id', $request->user()->id)->where('public_id', $data['quote_id'])->lockForUpdate()->firstOrFail();
            if ($quote->consumed_at || $quote->expires_at->lte(now()) || ! $quote->address_id) {
                throw new ApiFailure('quote_stale', 'This quote expired or changed. Request another quote.');
            }
            $cart = $carts->get($request->user());
            $carts->version($cart, $data['cart_version']);
            if (! in_array($data['payment_method'], $checkout->methods(), true)) {
                throw ValidationException::withMessages(['payment_method' => 'Only cash on delivery is available.']);
            }
            $address = $request->user()->addresses()->whereKey($quote->address_id)->lockForUpdate()->firstOrFail();
            $current = $checkout->snapshot($cart, $address, $carts->hydrate($cart));
            if ($current !== $quote->snapshot) {
                throw new ApiFailure('quote_stale', 'Your cart, address, price or stock changed. Request another quote.');
            }
            try {
                $order = app(PlaceOrder::class)->handle($request->user(), $address, $carts->hydrate($cart), PaymentMethod::from($data['payment_method']), verify: function ($locked, $shipping) use ($cart, $address, $checkout, $quote) {
                    if ($quote->expires_at->lte(now()) || $checkout->snapshot($cart, $address, $locked, $shipping) !== $quote->snapshot) {
                        throw new ApiFailure('quote_stale', 'Stock or pricing changed. Request another quote.');
                    }
                });
            } catch (ValidationException $exception) {
                if (array_key_exists('cart', $exception->errors())) {
                    throw new ApiFailure('stock_conflict', 'Stock changed. Request another quote.');
                }
                throw $exception;
            }
            $quote->update(['order_id' => $order->id, 'consumed_at' => now()]);
            $cart->update(['lines' => [], 'version' => $cart->version + 1]);

            return Reply::data(MobileViews::order($order), 201);
        });
    }

    public function index(MobileRequest $request)
    {
        return Reply::page($request->user()->orders()->orderByDesc('id')->cursorPaginate($request->integer('limit', 20)), fn ($order) => MobileViews::order($order, false));
    }

    public function show(MobileRequest $request, string $order_id)
    {
        return Reply::data(MobileViews::order($request->user()->orders()->where('public_id', $order_id)->firstOrFail()));
    }

    private function owned(MobileRequest $request, string $id): Fulfillment
    {
        return Fulfillment::where('public_id', $id)->whereHas('order', fn ($q) => $q->where('user_id', $request->user()->id))->firstOrFail();
    }

    public function confirm(MobileRequest $request, string $shipment_id, Replay $replay)
    {
        $part = $this->owned($request, $shipment_id);

        return $replay->run($request, [], function () use ($request, $part) {
            $part->refresh();
            if ($part->status !== FulfillmentStatus::Completed) {
                if ($part->status !== FulfillmentStatus::Delivered) {
                    throw new ApiFailure('state_conflict', 'This shipment is not ready for receipt confirmation.');
                }
                app(FulfillmentService::class)->act($part, $request->user(), 'complete', []);
            }

            return Reply::data(MobileViews::shipment($part->fresh(), false));
        });
    }

    public function photo(MobileRequest $request, string $shipment_id)
    {
        $part = $this->owned($request, $shipment_id);
        abort_unless($part->delivery_photo_path && str_starts_with($part->delivery_photo_path, 'delivery-proof/') && Storage::disk('local')->exists($part->delivery_photo_path), 404);

        return Storage::disk('local')->response($part->delivery_photo_path, null, ['Cache-Control' => 'private, no-store']);
    }
}

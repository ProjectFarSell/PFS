<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\FulfillmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\MobileRequest;
use App\Models\Fulfillment;
use App\Models\Order;
use App\Models\RiderProfile;
use App\Services\Mobile\ApiFailure;
use App\Services\Mobile\MobileViews;
use App\Services\Mobile\PublicIds;
use App\Services\Mobile\Replay;
use App\Services\Mobile\Reply;
use App\Services\Orders\DeliveryRequestService;
use App\Services\Orders\FulfillmentService;
use App\Services\Security\SafeImage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

class RiderController extends Controller
{
    public function status(MobileRequest $request)
    {
        $profile = $request->user()->riderProfile;

        return Reply::data(['approval_status' => $profile?->status->value ?? 'missing', 'is_available' => $profile?->is_available ?? false, 'can_deliver' => $profile?->isApproved() ?? false]);
    }

    public function availability(MobileRequest $request, DeliveryRequestService $dispatch)
    {
        $data = $request->validated();
        DB::transaction(function () use ($request, $data) {
            $profile = RiderProfile::where('user_id', $request->user()->id)->lockForUpdate()->firstOrFail();
            abort_unless($profile->isApproved(), 403);
            $profile->forceFill(['is_available' => $data['is_available']])->save();
        }, 3);
        $dispatch->dispatchPending();
        $request->user()->unsetRelation('riderProfile');

        return $this->status($request);
    }

    public function offers(MobileRequest $request, DeliveryRequestService $dispatch)
    {
        $profile = $request->user()->riderProfile;
        $query = $dispatch->requests($profile)->addSelect('fulfillments.public_id')
            ->addSelect(['offer_public_id' => DB::table('rider_delivery_requests')->select('public_id')->whereColumn('fulfillment_id', 'fulfillments.id')->where('rider_id', $profile->id)->limit(1), 'offer_started_at' => DB::table('rider_delivery_requests')->select('offered_at')->whereColumn('fulfillment_id', 'fulfillments.id')->where('rider_id', $profile->id)->limit(1)]);

        return Reply::page($query->cursorPaginate($request->integer('limit', 20)), fn ($part) => ['id' => $part->offer_public_id, 'shipment_id' => PublicIds::id($part), 'shop_name' => $part->shop_name, 'pickup_city' => $part->pickup_city, 'offered_at' => MobileViews::time(Carbon::parse($part->offer_started_at)), 'expires_at' => MobileViews::time(Carbon::parse($part->offer_expires_at))]);
    }

    public function decision(MobileRequest $request, string $offer_id, Replay $replay)
    {
        $data = $request->validated();
        $offer = DB::table('rider_delivery_requests')->where('public_id', $offer_id)->where('rider_id', $request->user()->riderProfile->id)->first();
        abort_unless($offer, 404);

        return $replay->run($request, $data, function () use ($request, $data, $offer, $offer_id) {
            $part = Fulfillment::findOrFail($offer->fulfillment_id);
            Order::whereKey($part->order_id)->lockForUpdate()->firstOrFail();
            $part = Fulfillment::whereKey($part->id)->lockForUpdate()->firstOrFail();
            $offer = DB::table('rider_delivery_requests')->where('public_id', $offer_id)->lockForUpdate()->first();
            if (! $offer || $offer->declined_at || $part->status !== FulfillmentStatus::Ready || $part->rider_id) {
                throw new ApiFailure('offer_unavailable', 'This offer is no longer available.');
            }
            if (Carbon::parse($offer->expires_at)->lte(now())) {
                throw new ApiFailure('offer_expired', 'This offer expired.');
            }
            $this->act($part, $request, $data['decision'] === 'accept' ? 'claim' : 'decline', []);

            return Reply::data(['offer_id' => $offer_id, 'decision' => $data['decision'], 'shipment' => $data['decision'] === 'accept' ? MobileViews::shipment($part->fresh(), true) : null]);
        });
    }

    public function shipments(MobileRequest $request)
    {
        $data = $request->validate(['view' => ['nullable', 'in:active,history']]);
        $query = Fulfillment::where('rider_id', $request->user()->riderProfile->id)->where('delivery_method', 'internal')->with('order');
        $query->whereIn('status', ($data['view'] ?? 'active') === 'history' ? ['delivered', 'completed', 'returned', 'cancelled'] : ['assigned', 'in_transit', 'delivery_failed', 'returning']);

        return Reply::page($query->orderByDesc('id')->cursorPaginate($request->integer('limit', 20)), fn ($part) => ['id' => PublicIds::id($part), 'order_number' => $part->order->number, 'status' => $part->status->value, 'shop_name' => $part->shop_name, 'allowed_actions' => match ($part->status) {
            FulfillmentStatus::Assigned => ['pickup'], FulfillmentStatus::InTransit => $part->retry_at?->isFuture() ? [] : ['deliver', 'report_failure'], default => []
        }]);
    }

    private function owned(MobileRequest $request, string $id): Fulfillment
    {
        return Fulfillment::where('public_id', $id)->where('rider_id', $request->user()->riderProfile->id)->where('delivery_method', 'internal')->firstOrFail();
    }

    public function shipment(MobileRequest $request, string $shipment_id)
    {
        return Reply::data(MobileViews::shipment($this->owned($request, $shipment_id), true));
    }

    public function transition(MobileRequest $request, string $shipment_id, Replay $replay)
    {
        $data = $request->validated();
        $part = $this->owned($request, $shipment_id);
        $action = match ($request->route()->getName()) {
            'api.mobile.rider.pickup' => 'pickup', 'api.mobile.rider.deliver' => 'deliver', default => 'fail'
        };
        $photo = null;
        try {
            return $replay->run($request, $data, function () use ($request, $data, $part, $shipment_id, $action, &$photo) {
                Order::whereKey($part->order_id)->lockForUpdate()->firstOrFail();
                $part = $this->owned($request, $shipment_id);
                $expected = $action === 'pickup' ? FulfillmentStatus::Assigned : FulfillmentStatus::InTransit;
                if ($part->status !== $expected) {
                    throw new ApiFailure('state_conflict', 'The shipment state changed. Refresh before continuing.');
                }
                if ($action === 'deliver') {
                    $this->pinLimit($request, $part);
                }
                $values = $data;
                if ($action === 'deliver' && $request->hasFile('delivery_photo')) {
                    $photo = app(SafeImage::class)->store($request->file('delivery_photo'), 'delivery-proof', 'local', 'delivery_photo');
                    $values['delivery_photo_path'] = $photo;
                }
                $this->act($part, $request, $action, $values);

                return Reply::data(MobileViews::shipment($part->fresh(), true));
            });
        } catch (\Throwable $exception) {
            if ($photo) {
                Storage::disk('local')->delete($photo);
            }
            throw $exception;
        }
    }

    private function act(Fulfillment $part, MobileRequest $request, string $action, array $data): void
    {
        try {
            app(FulfillmentService::class)->act($part, $request->user(), $action, $data);
        } catch (ValidationException $exception) {
            if (isset($exception->errors()['fulfillment'])) {
                throw new ApiFailure($part->cod_collected_at ? 'dispatch_required' : 'state_conflict', 'The delivery state changed or dispatch review is required.');
            }
            throw $exception;
        }
    }

    private function pinLimit(MobileRequest $request, Fulfillment $part): void
    {
        foreach (['mobile-pin-shipment:'.$part->id => 5, 'mobile-pin-rider:'.$request->user()->id => 20] as $key => $limit) {
            if (RateLimiter::tooManyAttempts($key, $limit)) {
                throw new TooManyRequestsHttpException(RateLimiter::availableIn($key), 'Too many PIN attempts.');
            }
        }
        RateLimiter::hit('mobile-pin-shipment:'.$part->id, 900);
        RateLimiter::hit('mobile-pin-rider:'.$request->user()->id, 900);
    }

    public function earnings(MobileRequest $request)
    {
        $query = $request->user()->riderProfile->earnings();
        $total = MobileViews::cents((clone $query)->sum('amount'));
        $page = $query->with('fulfillment.order')->orderByDesc('id')->cursorPaginate($request->integer('limit', 20));
        $ledger = ['data' => $page->getCollection()->map(fn ($entry) => ['id' => PublicIds::id($entry), 'shipment_id' => PublicIds::id($entry->fulfillment), 'order_number' => $entry->fulfillment->order->number, 'amount' => MobileViews::cents($entry->amount), 'earned_at' => MobileViews::time($entry->earned_at)])->all(), 'next_cursor' => $page->nextCursor()?->encode(), 'has_more' => $page->hasMorePages(), 'server_time' => MobileViews::time(now())];

        return Reply::data(['credited_total' => $total, 'currency' => 'PHP', 'ledger' => $ledger]);
    }
}

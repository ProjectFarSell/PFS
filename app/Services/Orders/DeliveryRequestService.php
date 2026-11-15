<?php

namespace App\Services\Orders;

use App\Models\Fulfillment;
use App\Models\Order;
use App\Models\RiderBarangayAssignment;
use App\Models\RiderProfile;
use App\Services\Mobile\MobileEvents;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DeliveryRequestService
{
    public static function city(string $city): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $city)));
    }

    public function notifyRiders(Fulfillment $part): void
    {
        DB::transaction(function () use ($part) {
            Order::query()->whereKey($part->order_id)->lockForUpdate()->firstOrFail();
            $part = Fulfillment::query()->lockForUpdate()->findOrFail($part->id);
            if ($part->usesExternalCourier() || $part->status->value !== 'ready' || $part->rider_id || ! $part->pickup_city || $part->vehicle_review_pending) {
                return;
            }
            // Barangay-covered shipments are reserved for their designated rider.
            if ($part->delivery_barangay_id && RiderBarangayAssignment::query()
                ->where('psgc_barangay_id', $part->delivery_barangay_id)->exists()) {
                return;
            }
            $active = DB::table('rider_delivery_requests')->where('fulfillment_id', $part->id)
                ->whereNull('declined_at')->where('expires_at', '>', now())->first();
            if ($active) {
                $rider = RiderProfile::find($active->rider_id);
                if ($rider && $rider->isApproved() && $rider->is_available
                    && $rider->user?->role->value === 'rider' && self::city($rider->city) === $part->pickup_city && app(DispatchVehicleEligibility::class)->allows($part, $rider)) {
                    return;
                }
                DB::table('rider_delivery_requests')->where('id', $active->id)->update(['expires_at' => now(), 'updated_at' => now()]);
            }
            $offers = DB::table('rider_delivery_requests')->where('fulfillment_id', $part->id)
                ->whereNotNull('offered_at')->get()->keyBy('rider_id');
            $retryBefore = now()->subMinutes(2);
            $excludedRiders = $offers->filter(fn ($offer) => $offer->declined_at !== null
                || $offer->expires_at === null || $offer->expires_at > $retryBefore->toDateTimeString())->keys();
            $riders = RiderProfile::query()->where('status', 'approved')->where('is_available', true)
                ->whereNotIn('id', $excludedRiders)->whereHas('user', fn ($q) => $q->where('role', 'rider'))->get()
                ->filter(fn ($rider) => self::city($rider->city) === $part->pickup_city)
                ->filter(fn ($rider) => app(DispatchVehicleEligibility::class)->allows($part, $rider))
                ->sortBy(fn ($rider) => [
                    $offers->has($rider->id) ? 1 : 0,
                    $offers->get($rider->id)?->expires_at ?? '',
                    Fulfillment::where('rider_id', $rider->id)->whereIn('status', ['assigned', 'in_transit', 'delivery_failed', 'returning'])->count()
                    + $rider->deliveries()->whereDoesntHave('fulfillments')->whereIn('status', ['assigned', 'in_transit'])->count()
                    + DB::table('rider_delivery_requests')->where('rider_id', $rider->id)
                        ->whereNull('declined_at')->where('expires_at', '>', now())
                        ->whereExists(fn ($q) => $q->selectRaw('1')->from('fulfillments')->whereColumn('fulfillments.id', 'fulfillment_id')->where('status', 'ready')->whereNull('fulfillments.rider_id'))->count(),
                    $rider->id,
                ]);
            foreach ($riders as $candidate) {
                $rider = RiderProfile::query()->lockForUpdate()->find($candidate->id);
                if ($rider && $rider->isApproved() && $rider->is_available && $rider->user?->role->value === 'rider' && self::city($rider->city) === $part->pickup_city && app(DispatchVehicleEligibility::class)->allows($part, $rider)) {
                    DB::table('rider_delivery_requests')->updateOrInsert(
                        ['fulfillment_id' => $part->id, 'rider_id' => $rider->id],
                        ['public_id' => (string) Str::uuid(), 'offered_at' => now(), 'expires_at' => now()->addMinutes(2), 'declined_at' => null, 'created_at' => now(), 'updated_at' => now()]
                    );
                    app(MobileEvents::class)->offered($part, $rider);
                    break;
                }
            }
        }, 3);
    }

    public function notifyAvailableRider(RiderProfile $rider): void
    {
        Fulfillment::query()->where('delivery_method', 'internal')->where('status', 'ready')->whereNull('rider_id')
            ->where('pickup_city', self::city($rider->city))->chunkById(100, function ($parts) {
                foreach ($parts as $part) {
                    $this->notifyRiders($part);
                }
            });
    }

    public function dispatchPending(): void
    {
        Fulfillment::query()->where('delivery_method', 'internal')->where('status', 'ready')->whereNull('rider_id')->whereNotNull('pickup_city')
            ->chunkById(100, function ($parts) {
                foreach ($parts as $part) {
                    $this->notifyRiders($part);
                }
            });
    }

    public function requests(RiderProfile $rider)
    {
        $query = Fulfillment::query()->select(['id', 'shop_name', 'pickup_city', 'created_at'])
            ->addSelect(['offer_expires_at' => DB::table('rider_delivery_requests')->select('expires_at')
                ->whereColumn('fulfillment_id', 'fulfillments.id')->where('rider_id', $rider->id)->limit(1)])
            ->where('status', 'ready')->where('delivery_method', 'internal')->whereNull('rider_id')
            ->where('pickup_city', self::city($rider->city))
            ->whereExists(fn ($q) => $q->selectRaw('1')->from('rider_delivery_requests')
                ->whereColumn('fulfillment_id', 'fulfillments.id')->where('rider_id', $rider->id)
                ->whereNull('declined_at')->where('expires_at', '>', now()))
            ->when(! $rider->is_available, fn ($q) => $q->whereRaw('1 = 0'))
            ->oldest('id');

        return app(DispatchVehicleEligibility::class)->constrain($query, $rider);
    }
}

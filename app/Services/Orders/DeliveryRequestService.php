<?php

namespace App\Services\Orders;

use App\Models\Fulfillment;
use App\Models\Order;
use App\Models\RiderProfile;
use Illuminate\Support\Facades\DB;

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
            if ($part->status->value !== 'ready' || $part->rider_id || ! $part->pickup_city) {
                return;
            }
            $active = DB::table('rider_delivery_requests')->where('fulfillment_id', $part->id)
                ->whereNull('declined_at')->where('expires_at', '>', now())->first();
            if ($active) {
                $rider = RiderProfile::find($active->rider_id);
                if ($rider && $rider->isApproved() && $rider->is_available
                    && $rider->user?->role->value === 'rider' && self::city($rider->city) === $part->pickup_city) {
                    return;
                }
                DB::table('rider_delivery_requests')->where('id', $active->id)->update(['expires_at' => now(), 'updated_at' => now()]);
            }
            $tried = DB::table('rider_delivery_requests')->where('fulfillment_id', $part->id)
                ->whereNotNull('offered_at')->pluck('rider_id');
            $riders = RiderProfile::query()->where('status', 'approved')->where('is_available', true)
                ->whereNotIn('id', $tried)->whereHas('user', fn ($q) => $q->where('role', 'rider'))->get()
                ->filter(fn ($rider) => self::city($rider->city) === $part->pickup_city)
                ->sortBy(fn ($rider) => [
                    Fulfillment::where('rider_id', $rider->id)->whereIn('status', ['assigned', 'in_transit'])->count()
                    + $rider->deliveries()->whereDoesntHave('fulfillments')->whereIn('status', ['assigned', 'in_transit'])->count()
                    + DB::table('rider_delivery_requests')->where('rider_id', $rider->id)
                        ->whereNull('declined_at')->where('expires_at', '>', now())
                        ->whereExists(fn ($q) => $q->selectRaw('1')->from('fulfillments')->whereColumn('fulfillments.id', 'fulfillment_id')->where('status', 'ready')->whereNull('fulfillments.rider_id'))->count(),
                    $rider->id,
                ]);
            foreach ($riders as $candidate) {
                $rider = RiderProfile::query()->lockForUpdate()->find($candidate->id);
                if ($rider && $rider->isApproved() && $rider->is_available && $rider->user?->role->value === 'rider' && self::city($rider->city) === $part->pickup_city) {
                    DB::table('rider_delivery_requests')->updateOrInsert(
                        ['fulfillment_id' => $part->id, 'rider_id' => $rider->id],
                        ['offered_at' => now(), 'expires_at' => now()->addMinutes(2), 'declined_at' => null, 'created_at' => now(), 'updated_at' => now()]
                    );
                    break;
                }
            }
        }, 3);
    }

    public function notifyAvailableRider(RiderProfile $rider): void
    {
        Fulfillment::query()->where('status', 'ready')->whereNull('rider_id')
            ->where('pickup_city', self::city($rider->city))->chunkById(100, function ($parts) {
                foreach ($parts as $part) {
                    $this->notifyRiders($part);
                }
            });
    }

    public function dispatchPending(): void
    {
        Fulfillment::query()->where('status', 'ready')->whereNull('rider_id')->whereNotNull('pickup_city')
            ->chunkById(100, function ($parts) {
                foreach ($parts as $part) {
                    $this->notifyRiders($part);
                }
            });
    }

    public function requests(RiderProfile $rider)
    {
        return Fulfillment::query()->select(['id', 'shop_name', 'pickup_city', 'created_at'])
            ->addSelect(['offer_expires_at' => DB::table('rider_delivery_requests')->select('expires_at')
                ->whereColumn('fulfillment_id', 'fulfillments.id')->where('rider_id', $rider->id)->limit(1)])
            ->where('status', 'ready')->whereNull('rider_id')
            ->where('pickup_city', self::city($rider->city))
            ->whereExists(fn ($q) => $q->selectRaw('1')->from('rider_delivery_requests')
                ->whereColumn('fulfillment_id', 'fulfillments.id')->where('rider_id', $rider->id)
                ->whereNull('declined_at')->where('expires_at', '>', now()))
            ->when(! $rider->is_available, fn ($q) => $q->whereRaw('1 = 0'))
            ->oldest('id');
    }
}

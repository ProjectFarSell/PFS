<?php

namespace App\Services\Mobile;

use App\Jobs\SendMobileEvent;
use App\Models\Fulfillment;
use App\Models\FulfillmentEvent;
use App\Models\MobileEvent;
use App\Models\Order;
use App\Models\RiderProfile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MobileEvents
{
    public function record(string $key, string $type, array $audience, array $ids, ?Fulfillment $part = null, $expires = null): ?MobileEvent
    {
        if (! config('mobile.events_enabled')) {
            return null;
        }
        $ids = array_intersect_key($ids, array_flip(['order_id', 'shipment_id', 'offer_id', 'conversation_id', 'expires_at']));
        $public = (string) Str::uuid();
        $event = MobileEvent::firstOrCreate(['event_key' => $key], ['public_id' => $public, 'type' => $type, 'audience' => array_values(array_unique(array_filter($audience))), 'payload' => ['event_id' => $public, 'type' => $type, 'occurred_at' => MobileViews::time(now()), ...$ids], 'fulfillment_id' => $part?->id, 'expires_at' => $expires ?? now()->addDay()]);
        DB::afterCommit(fn () => $this->enqueue($event->id));

        return $event;
    }

    public function orderPlaced(Order $order): void
    {
        $this->record('order:'.$order->id.':placed', 'order.placed', [$order->user_id], ['order_id' => PublicIds::id($order)]);
    }

    public function shipmentChanged(FulfillmentEvent $change): void
    {
        $part = $change->fulfillment;
        $part->loadMissing(['order', 'rider']);
        $audience = [$part->order->user_id, $part->usesExternalCourier() ? null : $part->rider?->user_id];
        $ids = ['order_id' => PublicIds::id($part->order), 'shipment_id' => PublicIds::id($part)];
        $this->record('shipment-event:'.$change->id, 'shipment.status_changed', $audience, $ids, $part);
        $this->record('order-event:'.$change->id, 'order.status_changed', [$part->order->user_id], $ids, $part);
        if ($change->status === 'assigned' && ! $part->usesExternalCourier()) {
            $this->record('assigned:'.$change->id, 'shipment.assigned', [$part->rider?->user_id], $ids, $part);
            foreach (DB::table('rider_delivery_requests')->where('fulfillment_id', $part->id)->whereNotNull('public_id')->get() as $offer) {
                $profile = RiderProfile::find($offer->rider_id);
                $this->record('offer-taken:'.$offer->public_id, 'offer.taken', [$profile?->user_id], [...$ids, 'offer_id' => $offer->public_id], $part);
            }
        }
        if ($change->status === 'cancelled') {
            $this->record('cancelled:'.$change->id, 'shipment.cancelled', $audience, $ids, $part);
        }
    }

    public function offered(Fulfillment $part, RiderProfile $rider): void
    {
        if (! config('mobile.events_enabled')) {
            return;
        }
        $offer = DB::table('rider_delivery_requests')->where('fulfillment_id', $part->id)->where('rider_id', $rider->id)->first();
        $expiry = Carbon::parse($offer->expires_at);
        $this->record('offer-created:'.$offer->public_id, 'offer.created', [$rider->user_id], ['order_id' => PublicIds::id($part->order), 'shipment_id' => PublicIds::id($part), 'offer_id' => $offer->public_id, 'expires_at' => MobileViews::time($expiry)], $part, $expiry);
    }

    public function expireOffers(): void
    {
        if (! config('mobile.events_enabled')) {
            return;
        }
        DB::table('rider_delivery_requests')->where('expires_at', '<=', now())->where('expires_at', '>', now()->subDay())->whereNull('declined_at')->orderBy('id')->chunkById(100, function ($offers) {
            foreach ($offers as $offer) {
                $part = Fulfillment::find($offer->fulfillment_id);
                $rider = RiderProfile::find($offer->rider_id);
                if ($part && $rider && $offer->public_id) {
                    $this->record('offer-expired:'.$offer->public_id, 'offer.expired', [$rider->user_id], ['order_id' => PublicIds::id($part->order), 'shipment_id' => PublicIds::id($part), 'offer_id' => $offer->public_id], $part);
                }
            }
        });
    }

    public function enqueue(int $id): bool
    {
        if (! config('mobile.push_enabled') && ! config('mobile.realtime_enabled')) {
            return false;
        }
        try {
            Bus::dispatch((new SendMobileEvent($id))->onConnection(config('mobile.connection'))->onQueue(config('mobile.queue')));

            return true;
        } catch (\Throwable) {
            MobileEvent::whereKey($id)->update(['last_error' => 'Queue unavailable; scheduler will retry.']);

            return false;
        }
    }
}

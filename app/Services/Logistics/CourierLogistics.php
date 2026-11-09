<?php

namespace App\Services\Logistics;

use App\Enums\DeliveryMethod;
use App\Enums\FulfillmentStatus as Status;
use App\Enums\UserRole;
use App\Models\CourierBooking;
use App\Models\Fulfillment;
use App\Models\Order;
use App\Models\User;
use App\Services\Orders\FulfillmentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CourierLogistics
{
    public function select(Fulfillment $shipment, User $actor, DeliveryMethod $method): void
    {
        DB::transaction(function () use ($shipment, $actor, $method) {
            [$order, $part] = $this->lock($shipment, $actor);
            $booking = $part->courierBooking;
            if (! in_array($part->status, [Status::Accepted, Status::Ready], true) || $part->rider_id
                || ($booking && ! in_array($booking->status, ['draft', 'quoted'], true))) {
                $this->invalid('Choose a courier before dispatch or booking. An assigned shipment cannot switch couriers.');
            }
            $booking?->delete(); // Draft quotations have not booked a courier or incurred a charge.
            DB::table('rider_delivery_requests')->where('fulfillment_id', $part->id)->delete();
            $part->unsetRelation('courierBooking');
            $part->delivery_method = $method;
            app(FulfillmentService::class)->recordState($order, $part, $actor, $part->status, 'Delivery method selected: '.$method->label().'.');
        }, 3);
    }

    public function manual(Fulfillment $shipment, User $actor, array $data): void
    {
        if (! filter_var($data['verified'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $this->invalid('Verify an existing courier booking before recording it.');
        }
        DB::transaction(function () use ($shipment, $actor, $data) {
            [$order, $part] = $this->lock($shipment, $actor);
            $this->ready($part);
            $booking = $part->courierBooking;
            $staleSubmission = $booking?->status === 'submitting' && $booking->updated_at->lte(now()->subMinutes(2));
            if ($booking && ! in_array($booking->status, ['draft', 'quoted', 'unknown'], true) && ! $staleSubmission) {
                $this->invalid('A booking already exists. Check its reference instead of creating another.');
            }
            $reconcileApi = $booking?->mode === 'api' && ($booking->status === 'unknown' || $staleSubmission);
            if ($order->payment_method->value === 'cod' && ! ($reconcileApi && $booking->isSandbox()) && ! filter_var($data['cod_supported'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                $this->invalid('Verify the courier accepts this shipment and its exact COD amount.');
            }
            $reference = trim($data['reference']);
            if (CourierBooking::where('provider', $part->delivery_method->value)->where('reference', $reference)->where('fulfillment_id', '!=', $part->id)->exists()) {
                $this->invalid('That courier reference is already linked to another shipment.');
            }
            $part->courierBooking()->updateOrCreate(['fulfillment_id' => $part->id], [
                'provider' => $part->delivery_method->value, 'mode' => $reconcileApi ? 'api' : 'manual', 'api_environment' => $reconcileApi ? $booking->api_environment : null,
                'status' => 'booked', 'reference' => $reference, 'tracking_url' => $this->trackingUrl($part->delivery_method, $data['tracking_url'] ?? null),
                'cost_cents' => $this->cents($data['courier_cost']), 'provider_status' => 'MANUAL_BOOKING_VERIFIED',
                'details' => $reconcileApi ? $booking->details : null, 'quote_expires_at' => null, 'booked_at' => now(),
            ]);
            $part->unsetRelation('courierBooking');
            app(FulfillmentService::class)->recordState($order, $part, $actor, Status::Assigned, 'Admin verified an existing '.$part->delivery_method->label().' booking: '.$reference.'. '.$data['note']);
        }, 3);
    }

    public function quote(Fulfillment $shipment, User $actor, array $data): void
    {
        // This bounded quotation request is read-only at the carrier; no delivery is booked.
        DB::transaction(function () use ($shipment, $actor, $data) {
            [, $part] = $this->lock($shipment, $actor);
            $this->ready($part);
            if ($part->delivery_method !== DeliveryMethod::Lalamove || ($part->courierBooking && ! in_array($part->courierBooking->status, ['draft', 'quoted'], true))) {
                $this->invalid('A new Lalamove quotation is unavailable for this shipment.');
            }
            $requestedAt = now();
            $result = app(LalamoveClient::class)->quote([
                ['coordinates' => ['lat' => (string) $data['pickup_lat'], 'lng' => (string) $data['pickup_lng']], 'address' => $data['pickup_address']],
                ['coordinates' => ['lat' => (string) $data['dropoff_lat'], 'lng' => (string) $data['dropoff_lng']], 'address' => $data['dropoff_address']],
            ], $data['service_type']);
            if (empty($result['quotationId']) || count($result['stops'] ?? []) !== 2 || empty($result['stops'][0]['stopId']) || empty($result['stops'][1]['stopId'])
                || ($result['priceBreakdown']['currency'] ?? '') !== 'PHP' || ! isset($result['priceBreakdown']['total'])) {
                $this->invalid('The courier returned an incomplete quotation or unsupported currency.');
            }
            $part->courierBooking()->updateOrCreate(['fulfillment_id' => $part->id], [
                'provider' => 'lalamove', 'mode' => 'api', 'api_environment' => config('logistics.lalamove.environment'),
                'status' => 'quoted', 'cost_cents' => $this->cents($result['priceBreakdown']['total']),
                'quote_expires_at' => $requestedAt->addMinutes(5),
                'details' => ['quotationId' => (string) $result['quotationId'],
                    'sender' => ['stopId' => (string) $result['stops'][0]['stopId'], 'name' => $data['sender_name'], 'phone' => $data['sender_phone']],
                    'recipients' => [['stopId' => (string) $result['stops'][1]['stopId'], 'name' => $data['recipient_name'], 'phone' => $data['recipient_phone']]],
                    'isPODEnabled' => true],
            ]);
        });
    }

    public function book(Fulfillment $shipment, User $actor): void
    {
        $client = app(LalamoveClient::class);
        if (! $client->configured()) {
            $this->invalid('Lalamove API is not configured.');
        }
        $booking = DB::transaction(function () use ($shipment, $actor) {
            [$order, $part] = $this->lock($shipment, $actor);
            $this->ready($part);
            $booking = $part->courierBooking;
            if ($part->delivery_method !== DeliveryMethod::Lalamove || ! $booking || $booking->status !== 'quoted'
                || $booking->quote_expires_at?->isPast() || $booking->api_environment !== config('logistics.lalamove.environment')) {
                $this->invalid('The quotation expired, changed environment or was already submitted. Request a new quotation.');
            }
            if ($booking->api_environment !== 'sandbox') {
                // PH cash handling requirements must be confirmed with the account/provider first.
                $this->invalid('Live COD and demo-payment API bookings are not enabled. Use a manually verified courier booking.');
            }
            $booking->update(['status' => 'submitting']);

            return $booking;
        }, 3);
        // Never hold/retry a DB transaction around a delivery-creating network call.
        try {
            $result = $client->book($booking->details);
            if (empty($result['orderId'])) {
                $this->invalid('Courier booking reference was not returned.');
            }
            if (isset($result['priceBreakdown']['currency']) && $result['priceBreakdown']['currency'] !== 'PHP') {
                $this->invalid('Unexpected courier currency.');
            }
            DB::transaction(function () use ($shipment, $actor, $booking, $result) {
                [$order, $part] = $this->lock($shipment, $actor);
                $current = CourierBooking::lockForUpdate()->findOrFail($booking->id);
                if ($current->status !== 'submitting' || $part->status !== Status::Ready) {
                    $this->invalid('Booking state changed. Reconcile in the provider portal.');
                }
                $current->update(['status' => 'booked', 'reference' => (string) $result['orderId'],
                    'provider_status' => (string) ($result['status'] ?? 'ASSIGNING_DRIVER'), 'booked_at' => now(),
                    'tracking_url' => $this->safeProviderLink($result['shareLink'] ?? null),
                    'cost_cents' => isset($result['priceBreakdown']['total']) ? $this->cents($result['priceBreakdown']['total']) : $current->cost_cents]);
                app(FulfillmentService::class)->recordState($order, $part, $actor, Status::Assigned, 'Lalamove sandbox booking recorded. No live courier or real COD payment is implied.');
            }, 3);
        } catch (\Throwable $exception) {
            DB::transaction(function () use ($booking) {
                Order::whereKey($booking->fulfillment->order_id)->lockForUpdate()->firstOrFail();
                $current = CourierBooking::lockForUpdate()->findOrFail($booking->id);
                if ($current->status === 'submitting') {
                    $current->update(['status' => 'unknown']);
                }
            });
            $this->invalid('Booking outcome is uncertain. Check the provider portal. Do not resubmit; reconcile its reference or confirm it was cancelled/not created.');
        }
    }

    public function sync(Fulfillment $shipment, User $actor): void
    {
        DB::transaction(function () use ($shipment, $actor) {
            [, $part] = $this->lock($shipment, $actor);
            $booking = $part->courierBooking;
            if (! $booking || $booking->mode !== 'api' || $booking->provider !== 'lalamove' || $booking->status !== 'booked'
                || $booking->api_environment !== config('logistics.lalamove.environment')) {
                $this->invalid('An active API booking in the same environment is required.');
            }
            $result = app(LalamoveClient::class)->status($booking->reference);
            if ((string) ($result['orderId'] ?? '') !== $booking->reference || ! is_string($result['status'] ?? null)) {
                $this->invalid('Courier returned a different or incomplete booking.');
            }
            $booking->update(['provider_status' => substr($result['status'], 0, 100), 'last_synced_at' => now()]);
            // A tracking result is not proof of COD collection or buyer acceptance.
        });
    }

    public function cancelRecord(Fulfillment $shipment, User $actor, string $note): void
    {
        DB::transaction(function () use ($shipment, $actor, $note) {
            [$order, $part] = $this->lock($shipment, $actor);
            $booking = $part->courierBooking;
            if (! $booking || ! in_array($part->status, [Status::Ready, Status::Assigned], true)
                || ($booking->status === 'submitting' && $booking->updated_at->gt(now()->subMinutes(2))) || $booking->status === 'cancelled') {
                $this->invalid('An active/pending booking can be reconciled only before pickup.');
            }
            $booking->update(['status' => 'cancelled']);
            app(FulfillmentService::class)->recordState($order, $part, $actor, Status::Ready, 'Admin verified courier cancellation/non-creation in the provider portal. '.$note);
        }, 3);
    }

    private function lock(Fulfillment $shipment, User $actor): array
    {
        $actor->refresh();
        abort_unless($actor->role === UserRole::Admin && ! $actor->suspended_at, 403);
        $order = Order::lockForUpdate()->findOrFail($shipment->order_id);
        $part = Fulfillment::lockForUpdate()->findOrFail($shipment->id);

        return [$order, $part];
    }

    private function ready(Fulfillment $part): void
    {
        if (! $part->usesExternalCourier() || $part->status !== Status::Ready || $part->rider_id) {
            $this->invalid('An unassigned external shipment ready for pickup is required.');
        }
    }

    private function cents(mixed $amount): int
    {
        if (! preg_match('/^\d{1,8}(?:\.\d{1,2})?$/D', (string) $amount)) {
            $this->invalid('Courier cost must be a non-negative PHP amount with at most two decimals.');
        }

        return (int) round((float) $amount * 100);
    }

    public function trackingUrl(DeliveryMethod $method, ?string $url): ?string
    {
        if (blank($url)) {
            return null;
        }
        $parts = parse_url($url);
        $hosts = $method === DeliveryMethod::Lalamove ? ['share.lalamove.com', 'share.sandbox.lalamove.com'] : [];
        if (! $parts || ($parts['scheme'] ?? '') !== 'https' || ! in_array(strtolower($parts['host'] ?? ''), $hosts, true) || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])) {
            $this->invalid('Use an HTTPS tracking link on the courier’s official tracking domain.');
        }

        return $url;
    }

    private function safeProviderLink(?string $url): ?string
    {
        try {
            return $this->trackingUrl(DeliveryMethod::Lalamove, $url);
        } catch (ValidationException) {
            return null;
        }
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['logistics' => $message]);
    }
}

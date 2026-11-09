<?php

namespace App\Services\Logistics;

use App\Enums\FulfillmentStatus as Status;
use App\Enums\PaymentMethod;
use App\Enums\UserRole;
use App\Models\Fulfillment;
use App\Models\Order;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class ExternalDeliveryService
{
    public function transition(Order $order, Fulfillment $part, User $actor, string $action, array $data): array
    {
        abort_unless($actor->role === UserRole::Admin && $part->usesExternalCourier(), 403);
        if ($part->courierBooking?->status !== 'booked' || ! filter_var($data['courier_verified'] ?? false, FILTER_VALIDATE_BOOLEAN)
            || $part->status !== ($action === 'pickup' ? Status::Assigned : Status::InTransit) || $part->retry_at?->isFuture()) {
            throw ValidationException::withMessages(['fulfillment' => 'Verify the current courier booking and event before updating this shipment.']);
        }
        if ($action === 'deliver') {
            if ($order->payment_method === PaymentMethod::Cod && ! $part->courierBooking->isSandbox()) {
                if (! filter_var($data['cod_collected'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                    throw ValidationException::withMessages(['cod_collected' => 'Verify the courier collected the exact COD amount before recording delivery.']);
                }
                $part->cod_collected_at = now();
            }
            $part->delivery_recipient_name = $data['delivery_recipient_name'];
            $part->delivery_note = $data['delivery_note'];
            $part->delivery_photo_path = $data['delivery_photo_path'] ?? null;
        }

        return [$action === 'pickup' ? Status::InTransit : Status::Delivered,
            'Admin verified '.$part->delivery_method->label().' '.$action.' for reference '.$part->courierBooking->reference.'. '.($data['delivery_note'] ?? 'Carrier pickup confirmed.')];
    }
}

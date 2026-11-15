<?php

namespace App\Services\Orders;

use App\Enums\ShippingVehicleTier;
use App\Models\Fulfillment;
use App\Models\User;
use App\Services\Security\AuditTrail;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Caller holds the order then shipment locks. */
class VehicleUpgradeService
{
    public function request(Fulfillment $part, User $seller, string $tier, string $reason): void
    {
        $target = ShippingVehicleTier::tryFrom($tier);
        $base = ShippingVehicleTier::tryFrom($part->required_vehicle_tier ?? $part->shipping_package['vehicle_tier'] ?? '');
        if (! $target || $part->rider_id || $part->vehicle_review_pending || $part->usesExternalCourier()
            || ($base && $target->rank() <= $base->rank()) || mb_strlen(trim($reason)) < 10 || mb_strlen($reason) > 1000) {
            throw ValidationException::withMessages(['requested_vehicle_tier' => 'Request a higher vehicle tier with a reason (10–1000 characters), before rider assignment.']);
        }
        $part->vehicle_upgrade = ['status' => 'pending', 'requested_tier' => $target->value, 'reason' => trim($reason), 'requested_by' => $seller->id, 'requested_at' => now()->toIso8601String()];
        $part->vehicle_review_pending = true;
        DB::table('rider_delivery_requests')->where('fulfillment_id', $part->id)->update(['expires_at' => now(), 'updated_at' => now()]);
        app(AuditTrail::class)->record('shipment.vehicle_upgrade_requested', $part, $part->vehicle_upgrade, $seller);
    }

    public function review(Fulfillment $part, User $admin, string $decision, string $reason): void
    {
        if (! $part->vehicle_review_pending || $part->rider_id || ! in_array($decision, ['approved', 'rejected'], true) || mb_strlen(trim($reason)) < 10 || mb_strlen($reason) > 1000) {
            throw ValidationException::withMessages(['vehicle_upgrade_decision' => 'Review a pending, unassigned request with a reason (10–1000 characters).']);
        }
        if ($decision === 'approved') {
            $part->required_vehicle_tier = $part->vehicle_upgrade['requested_tier'];
        }
        $part->vehicle_review_pending = false;
        $part->vehicle_upgrade = [...$part->vehicle_upgrade, 'status' => $decision, 'reviewed_by' => $admin->id, 'reviewed_at' => now()->toIso8601String(), 'review_note' => trim($reason)];
        // Expired offers must not suppress fresh dispatch after a review.
        DB::table('rider_delivery_requests')->where('fulfillment_id', $part->id)->update(['expires_at' => now()->subMinutes(3), 'updated_at' => now()]);
        app(AuditTrail::class)->record('shipment.vehicle_upgrade_'.$decision, $part, $part->vehicle_upgrade, $admin);
    }
}

<?php

namespace App\Services\Orders;

use App\Models\Fulfillment;
use App\Models\RiderProfile;
use Illuminate\Validation\ValidationException;

class DispatchVehicleEligibility
{
    public function tiers(RiderProfile $rider): array
    {
        return match ($rider->vehicle_type) {
            'motorcycle' => ['motorcycle'], 'car' => ['motorcycle', 'sedan_mpv'],
            'van' => ['motorcycle', 'sedan_mpv', 'van_light_truck'], default => [],
        };
    }

    public function allows(Fulfillment $part, RiderProfile $rider): bool
    {
        $tier = $part->required_vehicle_tier ?? $part->shipping_package['vehicle_tier'] ?? null;

        return ! $part->vehicle_review_pending && ($tier === null || in_array($tier, $this->tiers($rider), true));
    }

    public function constrain($query, RiderProfile $rider)
    {
        return $query->where('vehicle_review_pending', false)->where(function ($query) use ($rider) {
            $query->whereIn('required_vehicle_tier', $this->tiers($rider))->orWhere(function ($query) use ($rider) {
                // Previously created package snapshots have no dispatch column populated.
                $query->whereNull('required_vehicle_tier')->where(function ($query) use ($rider) {
                    $query->whereNull('shipping_package->vehicle_tier')->orWhereIn('shipping_package->vehicle_tier', $this->tiers($rider));
                });
            });
        });
    }

    public function ensure(Fulfillment $part, RiderProfile $rider): void
    {
        if (! $this->allows($part, $rider)) {
            throw ValidationException::withMessages(['rider_id' => 'This rider’s vehicle does not meet the shipment tier, or an upgrade awaits Admin review.']);
        }
    }
}

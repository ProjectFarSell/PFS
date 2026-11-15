<?php

namespace App\Services\Checkout;

use App\Enums\HandlingFlag;
use App\Enums\ShippingVehicleTier;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Collection;

class ShippingPackageCalculator
{
    public function forLines(Collection $lines): array
    {
        return $lines->groupBy(fn ($line) => $line->product->shop_id)->map(fn ($group) => $this->calculate(
            $group->map(fn ($line) => ['qty' => $line->qty, 'shipping_details' => $line->product->shippingDetails()])->all()
        ))->all();
    }

    /** Purchase snapshots only: never look up a product after it has been ordered. */
    public function forItems(Collection $items): array
    {
        return $this->calculate($items->map(fn ($item) => ['qty' => $item->qty, 'shipping_details' => $item->shipping_details])->all());
    }

    public function calculate(array $items): array
    {
        $divisor = (int) config('shipping.volumetric_divisor', 5000);
        if (! in_array($divisor, [3500, 5000], true)) {
            throw new \RuntimeException('Configure SHIPPING_VOLUMETRIC_DIVISOR as 3500 or 5000.');
        }
        $weight = BigDecimal::zero();
        $volume = BigDecimal::zero();
        $maxDimension = BigDecimal::zero();
        $weightComplete = $items !== [];
        $dimensionsComplete = $items !== [];
        $missing = 0;
        $quantity = 0;
        $flags = [];
        foreach ($items as $item) {
            $qty = $item['qty'];
            if (! is_int($qty) || $qty < 1) {
                throw new \InvalidArgumentException('Package quantities must be positive integers.');
            }
            $quantity += $qty;
            $details = $item['shipping_details'] ?? [];
            $hasWeight = isset($details['weight_kg']) && BigDecimal::of((string) $details['weight_kg'])->isGreaterThan(0);
            $hasDimensions = collect(['length_cm', 'width_cm', 'height_cm'])->every(fn ($key) => isset($details[$key]) && BigDecimal::of((string) $details[$key])->isGreaterThan(0));
            if (! $hasWeight || ! $hasDimensions) {
                $missing++;
            }
            $weightComplete = $weightComplete && $hasWeight;
            $dimensionsComplete = $dimensionsComplete && $hasDimensions;
            if ($hasWeight) {
                $weight = $weight->plus(BigDecimal::of((string) $details['weight_kg'])->multipliedBy($qty));
            }
            if ($hasDimensions) {
                $itemVolume = BigDecimal::one();
                foreach (['length_cm', 'width_cm', 'height_cm'] as $key) {
                    $dimension = BigDecimal::of((string) $details[$key]);
                    $itemVolume = $itemVolume->multipliedBy($dimension);
                    $maxDimension = BigDecimal::max($maxDimension, $dimension);
                }
                $volume = $volume->plus($itemVolume->multipliedBy($qty));
            }
            foreach ($details['handling_flags'] ?? [] as $flag) {
                if (is_string($flag) && HandlingFlag::tryFrom($flag)) {
                    $flags[$flag] = true;
                }
            }
        }
        $volumetric = $volume->dividedBy($divisor, 3, RoundingMode::Ceiling);
        $complete = $weightComplete && $dimensionsComplete;
        $chargeable = BigDecimal::max($weight, $volumetric);
        $tier = ! $complete ? null : ($chargeable->isLessThanOrEqualTo(20) && $maxDimension->isLessThanOrEqualTo(50)
            ? ShippingVehicleTier::Motorcycle : ($chargeable->isLessThanOrEqualTo(100) && $maxDimension->isLessThanOrEqualTo(100)
                ? ShippingVehicleTier::SedanMpv : ShippingVehicleTier::VanLightTruck));
        // Canonical enum order makes quote comparison independent of checkbox ordering.
        $handling = array_values(array_filter(array_column(HandlingFlag::cases(), 'value'), fn ($value) => isset($flags[$value])));

        return ['calculation_version' => 1, 'total_quantity' => $quantity, 'volumetric_divisor' => $divisor,
            'actual_weight_kg' => $weightComplete ? (string) $weight->toScale(3) : null,
            'volumetric_weight_kg' => $dimensionsComplete ? (string) $volumetric : null,
            'chargeable_weight_kg' => $complete ? (string) $chargeable->toScale(3) : null,
            'max_single_item_dimension_cm' => $dimensionsComplete ? (string) $maxDimension->toScale(2) : null,
            'vehicle_tier' => $tier?->value, 'status' => $complete ? 'ready' : 'needs_shipping_details',
            'missing_details_item_count' => $missing, 'handling_flags' => $handling,
            'packaging_basis' => 'sum_of_item_volumes', 'pricing_basis' => 'existing_flat_fee'];
    }

    public function api(?array $package): ?array
    {
        if ($package === null) {
            return null;
        }
        foreach (['actual_weight_kg', 'volumetric_weight_kg', 'chargeable_weight_kg', 'max_single_item_dimension_cm'] as $key) {
            $package[$key] = isset($package[$key]) ? (float) $package[$key] : null;
        }

        return $package;
    }
}

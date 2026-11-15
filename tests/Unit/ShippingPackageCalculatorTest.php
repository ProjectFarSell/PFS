<?php

namespace Tests\Unit;

use App\Services\Checkout\ShippingPackageCalculator;
use Tests\TestCase;

class ShippingPackageCalculatorTest extends TestCase
{
    private function item(string $weight, string $length, string $width = '1', string $height = '1', int $qty = 1): array
    {
        return ['qty' => $qty, 'shipping_details' => ['weight_kg' => $weight, 'length_cm' => $length, 'width_cm' => $width, 'height_cm' => $height]];
    }

    public function test_quantity_weight_and_volume_are_summed_before_comparison(): void
    {
        config(['shipping.volumetric_divisor' => 5000]);
        $service = new ShippingPackageCalculator;
        $result = $service->calculate([$this->item('2.125', '20', '30', '40', 3), $this->item('15', '10')]);
        $this->assertSame('21.375', $result['actual_weight_kg']);
        $this->assertSame('14.402', $result['volumetric_weight_kg']);
        $this->assertSame('21.375', $result['chargeable_weight_kg']);
        $this->assertSame('40.00', $result['max_single_item_dimension_cm']);
        $this->assertSame(4, $result['total_quantity']);
        $this->assertSame('sedan_mpv', $result['vehicle_tier']);
    }

    public function test_vehicle_boundaries_use_both_chargeable_weight_and_largest_item_side(): void
    {
        config(['shipping.volumetric_divisor' => 5000]);
        $service = new ShippingPackageCalculator;
        foreach ([
            [$this->item('20', '50', '20', '20'), 'motorcycle'],
            [$this->item('20.001', '1'), 'sedan_mpv'],
            [$this->item('1', '50.01'), 'sedan_mpv'],
            [$this->item('100', '100'), 'sedan_mpv'],
            [$this->item('100.001', '1'), 'van_light_truck'],
            [$this->item('1', '100.01'), 'van_light_truck'],
            [$this->item('1', '100', '100', '50'), 'sedan_mpv'],
            [$this->item('1', '100', '100', '50.01'), 'van_light_truck'],
        ] as [$item, $expected]) {
            $this->assertSame($expected, $service->calculate([$item])['vehicle_tier']);
        }
    }

    public function test_divisor_is_configurable_and_volume_rounds_up_only_after_summing(): void
    {
        $service = new ShippingPackageCalculator;
        config(['shipping.volumetric_divisor' => 3500]);
        $this->assertSame('2.000', $service->calculate([$this->item('1', '70', '10', '10')])['volumetric_weight_kg']);
        config(['shipping.volumetric_divisor' => 5000]);
        $this->assertSame('1.400', $service->calculate([$this->item('1', '70', '10', '10')])['volumetric_weight_kg']);
        $result = $service->calculate([$this->item('0.001', '1'), $this->item('0.001', '1')]);
        $this->assertSame('0.001', $result['volumetric_weight_kg']);
        $this->assertSame('0.002', $result['actual_weight_kg']);
        $large = $service->calculate([$this->item('1000', '1000', '1000', '1000', 1000000)]);
        $this->assertSame('200000000000.000', $large['chargeable_weight_kg']);
    }

    public function test_missing_optional_fields_do_not_become_zero_or_an_unsafe_motorcycle_recommendation(): void
    {
        $service = new ShippingPackageCalculator;
        $items = [$this->item('1', '10'), ['qty' => 2, 'shipping_details' => null]];
        $result = $service->calculate($items);
        $this->assertNull($result['actual_weight_kg']);
        $this->assertNull($result['volumetric_weight_kg']);
        $this->assertNull($result['chargeable_weight_kg']);
        $this->assertNull($result['vehicle_tier']);
        $this->assertSame('needs_shipping_details', $result['status']);
        $this->assertSame(1, $result['missing_details_item_count']);
        $partial = $service->calculate([['qty' => 1, 'shipping_details' => ['weight_kg' => '2.125']]]);
        $this->assertSame('2.125', $partial['actual_weight_kg']);
        $this->assertNull($partial['chargeable_weight_kg']);
        $this->assertNull($service->api(null));
    }

    public function test_flags_are_unioned_without_duplication_and_invalid_divisor_is_rejected(): void
    {
        $service = new ShippingPackageCalculator;
        $one = $this->item('1', '1');
        $two = $this->item('1', '1');
        $one['shipping_details']['handling_flags'] = ['keep_dry', 'fragile'];
        $two['shipping_details']['handling_flags'] = ['fragile', 'keep_cold'];
        $this->assertSame(['fragile', 'keep_dry', 'keep_cold'], $service->calculate([$one, $two])['handling_flags']);
        config(['shipping.volumetric_divisor' => 0]);
        $this->expectException(\RuntimeException::class);
        $service->calculate([$one]);
    }
}

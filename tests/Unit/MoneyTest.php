<?php

namespace Tests\Unit;

use App\Support\Money;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_decimal_inputs_and_signed_output_are_exact(): void
    {
        $this->assertSame(9999999999, Money::cents('99999999.99'));
        $this->assertSame(1, Money::cents('0.01'));
        $this->assertSame(810, Money::cents('8.1'));
        $this->assertSame('-0.01', Money::decimal(-1));
        $this->assertSame('99999999.99', Money::decimal(9999999999));
        foreach (['1e3', '0.001', '-1', ' 1.00', '1,000.00'] as $invalid) {
            try {
                Money::cents($invalid);
                $this->fail('Invalid money accepted.');
            } catch (\InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }
    }
}

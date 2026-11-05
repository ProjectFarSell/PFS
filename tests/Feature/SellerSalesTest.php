<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Order;
use App\Models\ReturnRequest;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SellerSalesTest extends TestCase
{
    use RefreshDatabase;

    private function sale(Shop $shop, string $status, string $payment = 'cod', string $name = 'Shirt', string $date = '2026-11-03'): array
    {
        $order = Order::query()->create(['user_id' => User::factory()->create()->id, 'number' => 'FS-SALE-'.Order::query()->count(), 'status' => 'completed', 'payment_method' => $payment, 'ship_to' => 'PRIVATE ADDRESS', 'subtotal' => 200, 'shipping_fee' => 49, 'total' => 249]);
        $part = $order->fulfillments()->create(['shop_id' => $shop->id, 'shop_name' => $shop->name, 'status' => $status, 'subtotal' => 200, 'shipping_fee' => 49]);
        $item = $order->items()->create(['fulfillment_id' => $part->id, 'name' => $name, 'product_sku' => 'TEST-BKS', 'qty' => 2, 'unit_price' => 100, 'line_total' => 200]);
        if ($status === 'completed') {
            $part->events()->create(['status' => 'completed', 'created_at' => $date.' 12:00:00']);
        }

        return [$order, $part, $item];
    }

    public function test_summary_uses_own_completed_shipments_and_separates_demo_and_refunds(): void
    {
        $seller = User::factory()->seller()->create();
        $shop = Shop::factory()->create(['user_id' => $seller->id]);
        [$order, $part, $item] = $this->sale($shop, 'completed');
        $this->sale($shop, 'pending', 'cod', 'Unfinished item');
        $this->sale($shop, 'cancelled', 'cod', 'Cancelled item');
        $this->sale($shop, 'completed', 'gateway_stub', 'Demo item');
        $this->sale($shop, 'completed', 'cod', 'Old item', '2026-10-01');
        $this->sale(Shop::factory()->create(), 'completed', 'cod', 'Other seller secret');
        ReturnRequest::query()->create(['order_item_id' => $item->id, 'quantity' => 1, 'reason' => 'damaged', 'details' => 'Damage description', 'status' => 'refunded', 'requested_amount' => 100, 'approved_amount' => 80, 'refunded_at' => '2026-11-03 13:00:00']);
        $response = $this->actingAs($seller)->get('/seller/sales?from=2026-11-01&to=2026-11-30');
        $response->assertOk()->assertDontSee('Other seller secret')->assertDontSee('PRIVATE ADDRESS')->assertDontSee('Unfinished item')->assertDontSee('Old item');
        $response->assertViewHas('stats', fn ($stats) => $stats['sales'] == 200 && $stats['refunds'] == 80 && $stats['net'] == 120 && $stats['units'] == 2 && $stats['demo'] == 200 && $stats['shipments'] == 1);
    }

    public function test_csv_contains_only_own_sales_and_neutralizes_spreadsheet_formulas(): void
    {
        $seller = User::factory()->seller()->create();
        $shop = Shop::factory()->create(['user_id' => $seller->id]);
        $this->sale($shop, 'completed', 'cod', '=HYPERLINK("example")');
        $this->sale(Shop::factory()->create(), 'completed', 'cod', 'Other seller secret');
        $response = $this->actingAs($seller)->get('/seller/sales?from=2026-11-01&to=2026-11-30&export=csv')->assertOk()->assertDownload();
        $csv = $response->streamedContent();
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringNotContainsString('Other seller secret', $csv);
        $this->assertStringNotContainsString('PRIVATE ADDRESS', $csv);
    }

    public function test_sales_permissions_empty_state_and_date_validation(): void
    {
        foreach ([UserRole::Buyer, UserRole::Admin, UserRole::Rider] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->get('/seller/sales')->assertForbidden();
        }
        $seller = User::factory()->seller()->create();
        Shop::factory()->create(['user_id' => $seller->id]);
        $this->actingAs($seller)->get('/seller/sales')->assertOk()->assertSee('No completed COD sales');
        $this->get('/seller/sales?from=2026-11-30&to=2026-11-01')->assertSessionHasErrors('to');
        $this->get('/seller/sales?from[]=bad')->assertSessionHasErrors('from');
    }
}

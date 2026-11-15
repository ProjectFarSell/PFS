<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProductShippingMigrationTest extends TestCase
{
    use DatabaseMigrations;

    public function test_migration_is_additive_and_rolls_back_without_repricing_or_backfill(): void
    {
        $product = Product::factory()->create(['price' => '123.45', 'stock' => 7]);
        $buyer = User::factory()->create();
        $order = Order::create(['user_id' => $buyer->id, 'number' => 'SHIPPING-LEGACY', 'status' => 'pending_payment', 'payment_method' => 'cod', 'ship_to' => 'Existing address', 'subtotal' => '123.45', 'shipping_fee' => '49.00', 'total' => '172.45']);
        $item = $order->items()->create(['product_id' => $product->id, 'name' => $product->name, 'qty' => 1, 'unit_price' => '123.45', 'line_total' => '123.45']);
        $accounts = DB::table('users')->count();
        $migration = require database_path('migrations/2026_11_15_030000_add_product_shipping_details.php');
        $migration->down();
        $this->assertFalse(Schema::hasColumn('products', 'weight_kg'));
        $migration->up();
        $this->assertSame('123.45', $product->fresh()->price);
        $this->assertSame(7, $product->fresh()->stock);
        $this->assertNull($product->fresh()->handling_flags);
        $this->assertNull($product->fresh()->weight_kg);
        $this->assertSame(1, DB::table('products')->count());
        $this->assertSame($accounts, DB::table('users')->count());
        $this->assertSame('172.45', $order->fresh()->total);
        $this->assertSame('123.45', $item->fresh()->unit_price);
        $this->assertNull($item->fresh()->shipping_details);
    }

    public function test_package_migration_preserves_existing_shipments_and_keeps_snapshot_null(): void
    {
        $buyer = User::factory()->create();
        $order = Order::create(['user_id' => $buyer->id, 'number' => 'PACKAGE-LEGACY', 'status' => 'pending_payment', 'payment_method' => 'cod', 'ship_to' => 'Existing address', 'subtotal' => '100.00', 'shipping_fee' => '49.00', 'total' => '149.00']);
        $part = $order->fulfillments()->create(['shop_id' => Shop::factory()->create()->id, 'shop_name' => 'Legacy shop', 'status' => 'pending', 'subtotal' => '100.00', 'shipping_fee' => '49.00']);
        $migration = require database_path('migrations/2026_11_15_040000_add_fulfillment_shipping_package.php');
        $migration->down();
        $this->assertFalse(Schema::hasColumn('fulfillments', 'shipping_package'));
        $migration->up();
        $this->assertNull($part->fresh()->shipping_package);
        $this->assertSame('49.00', $part->fresh()->shipping_fee);
        $this->assertSame('149.00', $order->fresh()->total);
        $dispatchMigration = require database_path('migrations/2026_11_15_050000_add_dispatch_vehicle_requirements.php');
        $dispatchMigration->down();
        $this->assertFalse(Schema::hasColumn('fulfillments', 'required_vehicle_tier'));
        $dispatchMigration->up();
        $this->assertNull($part->fresh()->required_vehicle_tier);
        $this->assertNull($part->fresh()->vehicle_upgrade);
        $this->assertFalse($part->fresh()->vehicle_review_pending);
    }
}

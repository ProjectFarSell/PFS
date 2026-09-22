<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fulfillments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shop_id')->nullable()->constrained()->nullOnDelete();
            $table->string('shop_name');
            $table->foreignId('rider_id')->nullable()->constrained('rider_profiles')->nullOnDelete();
            $table->string('status')->default('pending')->index();
            $table->decimal('subtotal', 10, 2);
            $table->decimal('shipping_fee', 10, 2)->default(0);
            $table->text('rejection_reason')->nullable();
            $table->text('delivery_note')->nullable();
            $table->text('pickup_address')->nullable();
            $table->string('pickup_contact')->nullable();
            $table->timestamp('cod_collected_at')->nullable();
            $table->timestamp('stock_released_at')->nullable();
            $table->timestamps();
            $table->unique(['order_id', 'shop_id']);
        });
        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('fulfillment_id')->nullable()->constrained()->nullOnDelete();
        });
        Schema::create('fulfillment_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fulfillment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status');
            $table->text('note')->nullable();
            $table->timestamps();
        });

        // Snapshot existing item ownership without resetting orders, stock, or payment records.
        DB::table('orders')->orderBy('id')->chunkById(100, function ($orders) {
            foreach ($orders as $order) {
                $groups = DB::table('order_items')->leftJoin('products', 'products.id', '=', 'order_items.product_id')
                    ->where('order_items.order_id', $order->id)
                    ->select('order_items.*', 'products.shop_id')->get()->groupBy('shop_id');
                $fee = (int) round((float) $order->shipping_fee * 100);
                $index = 0;
                foreach ($groups as $shopId => $items) {
                    $status = match ($order->status) {
                        'packed' => 'ready', 'assigned' => 'assigned', 'in_transit' => 'in_transit',
                        'delivered' => 'delivered', 'cancelled' => 'rejected', default => 'pending',
                    };
                    $id = DB::table('fulfillments')->insertGetId([
                        'order_id' => $order->id, 'shop_id' => $shopId ?: null,
                        'shop_name' => DB::table('shops')->where('id', $shopId)->value('name') ?? 'Unavailable shop',
                        'rider_id' => $order->rider_id, 'status' => $status,
                        'subtotal' => $items->sum('line_total'),
                        'shipping_fee' => (intdiv($fee, $groups->count()) + ($index++ < $fee % $groups->count() ? 1 : 0)) / 100,
                        'stock_released_at' => $order->stock_released_at,
                        'created_at' => $order->created_at, 'updated_at' => now(),
                    ]);
                    DB::table('order_items')->whereIn('id', $items->pluck('id'))->update(['fulfillment_id' => $id]);
                    DB::table('fulfillment_events')->insert([
                        'fulfillment_id' => $id, 'status' => $status, 'note' => 'Imported existing order status.',
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fulfillment_events');
        Schema::table('order_items', fn (Blueprint $table) => $table->dropConstrainedForeignId('fulfillment_id'));
        Schema::dropIfExists('fulfillments');
    }
};

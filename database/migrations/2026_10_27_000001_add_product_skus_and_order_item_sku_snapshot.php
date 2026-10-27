<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('sku', 100)->nullable()->unique()->after('name');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->string('product_sku', 100)->nullable()->after('name');
        });

        DB::table('products')->where('has_variants', false)->orderBy('id')->each(function ($product) {
            DB::table('products')->where('id', $product->id)->update([
                'sku' => 'FS-PROD-'.str_pad((string) $product->id, 6, '0', STR_PAD_LEFT),
            ]);
        });

        DB::table('order_items')->orderBy('id')->each(function ($item) {
            $variantSku = $item->product_variant_id
                ? DB::table('product_variants')->where('id', $item->product_variant_id)->value('sku')
                : null;
            $productSku = $item->product_id
                ? DB::table('products')->where('id', $item->product_id)->value('sku')
                : null;

            DB::table('order_items')->where('id', $item->id)->update([
                'product_sku' => $variantSku ?: $productSku,
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('order_items', fn (Blueprint $table) => $table->dropColumn('product_sku'));
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['sku']);
            $table->dropColumn('sku');
        });
    }
};

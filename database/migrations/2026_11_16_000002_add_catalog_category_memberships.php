<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_category_product', function (Blueprint $table) {
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->primary(['product_id', 'category_id']);
        });
        DB::table('products')->select(['id', 'category_id'])->orderBy('id')->chunkById(500, function ($rows) {
            DB::table('catalog_category_product')->insert($rows->map(fn ($r) => ['product_id' => $r->id, 'category_id' => $r->category_id])->all());
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_category_product');
    }
};

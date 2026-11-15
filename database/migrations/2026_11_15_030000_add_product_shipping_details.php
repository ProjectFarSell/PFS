<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('length_cm', 8, 2)->nullable();
            $table->decimal('width_cm', 8, 2)->nullable();
            $table->decimal('height_cm', 8, 2)->nullable();
            $table->decimal('weight_kg', 8, 3)->nullable();
            $table->json('handling_flags')->nullable();
        });
        Schema::table('order_items', fn (Blueprint $table) => $table->json('shipping_details')->nullable());
    }

    public function down(): void
    {
        Schema::table('order_items', fn (Blueprint $table) => $table->dropColumn('shipping_details'));
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn(['length_cm', 'width_cm', 'height_cm', 'weight_kg', 'handling_flags']));
    }
};

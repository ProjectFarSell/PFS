<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', fn (Blueprint $table) => $table->boolean('has_variants')->default(false)->after('stock'));
        Schema::table('product_variants', fn (Blueprint $table) => $table->json('options')->nullable()->after('option_value'));
        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('product_variant_id')->nullable()->after('product_id')->constrained('product_variants')->nullOnDelete();
            $table->json('variant_options')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_variant_id');
            $table->dropColumn('variant_options');
        });
        Schema::table('product_variants', fn (Blueprint $table) => $table->dropColumn('options'));
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('has_variants'));
    }
};

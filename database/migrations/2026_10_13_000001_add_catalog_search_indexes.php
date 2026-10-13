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
            $table->index(['is_active', 'category_id'], 'products_active_category_index');
            $table->index(['is_active', 'price'], 'products_active_price_index');
            $table->index(['shop_id', 'is_active'], 'products_shop_active_index');

            // Full-text indexes are only supported by MySQL/MariaDB, not SQLite
            if (DB::getDriverName() !== 'sqlite') {
                $table->fullText(['name', 'description'], 'products_name_description_fulltext');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (DB::getDriverName() !== 'sqlite') {
                $table->dropFullText('products_name_description_fulltext');
            }

            $table->dropIndex('products_shop_active_index');
            $table->dropIndex('products_active_price_index');
            $table->dropIndex('products_active_category_index');
        });
    }
};

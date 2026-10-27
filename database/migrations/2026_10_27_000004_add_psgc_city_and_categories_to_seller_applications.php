<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seller_applications', function (Blueprint $table) {
            $table->foreignId('psgc_city_municipality_id')
                ->nullable()
                ->after('city')
                ->constrained('psgc_cities_municipalities')
                ->nullOnDelete();
            $table->json('category_ids')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('seller_applications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('psgc_city_municipality_id');
            $table->dropColumn('category_ids');
        });
    }
};

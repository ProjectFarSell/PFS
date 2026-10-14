<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->foreignId('psgc_city_municipality_id')
                ->nullable()
                ->constrained('psgc_cities_municipalities')
                ->nullOnDelete()
                ->after('city');
            $table->index('psgc_city_municipality_id');
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropForeign(['psgc_city_municipality_id']);
            $table->dropColumn('psgc_city_municipality_id');
        });
    }
};

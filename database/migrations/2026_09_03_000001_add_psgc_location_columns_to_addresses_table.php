<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->foreignId('psgc_region_id')->nullable()->after('region')->constrained()->nullOnDelete();
            $table->foreignId('psgc_province_id')->nullable()->after('psgc_region_id')->constrained()->nullOnDelete();
            $table->foreignId('psgc_city_municipality_id')->nullable()->after('psgc_province_id')->constrained('psgc_cities_municipalities')->nullOnDelete();
            $table->foreignId('psgc_barangay_id')->nullable()->after('psgc_city_municipality_id')->constrained('psgc_barangays')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('psgc_barangay_id');
            $table->dropConstrainedForeignId('psgc_city_municipality_id');
            $table->dropConstrainedForeignId('psgc_province_id');
            $table->dropConstrainedForeignId('psgc_region_id');
        });
    }
};

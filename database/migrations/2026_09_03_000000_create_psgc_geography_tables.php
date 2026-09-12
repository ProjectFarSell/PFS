<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('psgc_regions', function (Blueprint $table) {
            $table->id();
            $table->string('psgc_code', 10)->unique();
            $table->string('region_code', 2)->unique();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('psgc_provinces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('psgc_region_id')->constrained()->cascadeOnDelete();
            $table->string('psgc_code', 10)->unique();
            $table->string('province_code', 3);
            $table->string('name');
            $table->timestamps();

            $table->unique(['psgc_region_id', 'province_code']);
        });

        Schema::create('psgc_cities_municipalities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('psgc_province_id')->constrained()->cascadeOnDelete();
            $table->string('psgc_code', 10)->unique();
            $table->string('city_municipality_code', 5);
            $table->string('name');
            $table->timestamps();

            $table->unique(['psgc_province_id', 'city_municipality_code'], 'psgc_city_code_unique');
        });

        Schema::create('psgc_barangays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('psgc_city_municipality_id')->constrained('psgc_cities_municipalities')->cascadeOnDelete();
            $table->string('psgc_code', 10)->unique();
            $table->string('barangay_code', 8);
            $table->string('name');
            $table->timestamps();

            $table->unique(['psgc_city_municipality_id', 'barangay_code'], 'psgc_barangay_code_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('psgc_barangays');
        Schema::dropIfExists('psgc_cities_municipalities');
        Schema::dropIfExists('psgc_provinces');
        Schema::dropIfExists('psgc_regions');
    }
};

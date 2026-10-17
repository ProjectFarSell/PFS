<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rider_barangay_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rider_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('psgc_barangay_id')->constrained('psgc_barangays')->cascadeOnDelete();
            $table->timestamps();

            $table->unique('psgc_barangay_id', 'rider_barangay_unique');
            $table->unique(['rider_profile_id', 'psgc_barangay_id'], 'rider_barangay_pair_unique');
        });

        Schema::table('fulfillments', function (Blueprint $table) {
            $table->foreignId('delivery_barangay_id')
                ->nullable()
                ->after('rider_id')
                ->constrained('psgc_barangays')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('fulfillments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('delivery_barangay_id');
        });

        Schema::dropIfExists('rider_barangay_assignments');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rider_profiles', fn (Blueprint $table) => $table->boolean('is_available')->default(false));
        Schema::table('fulfillments', fn (Blueprint $table) => $table->string('pickup_city')->nullable());
        Schema::create('rider_delivery_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fulfillment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rider_id')->constrained('rider_profiles')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['fulfillment_id', 'rider_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rider_delivery_requests');
        Schema::table('fulfillments', fn (Blueprint $table) => $table->dropColumn('pickup_city'));
        Schema::table('rider_profiles', fn (Blueprint $table) => $table->dropColumn('is_available'));
    }
};

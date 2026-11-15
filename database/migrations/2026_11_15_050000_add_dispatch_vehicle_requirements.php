<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fulfillments', function (Blueprint $table) {
            $table->string('required_vehicle_tier', 24)->nullable();
            $table->boolean('vehicle_review_pending')->default(false);
            $table->json('vehicle_upgrade')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('fulfillments', fn (Blueprint $table) => $table->dropColumn(['required_vehicle_tier', 'vehicle_review_pending', 'vehicle_upgrade']));
    }
};

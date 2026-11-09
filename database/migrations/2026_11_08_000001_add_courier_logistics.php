<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fulfillments', fn (Blueprint $table) => $table->string('delivery_method')->default('internal')->index());
        Schema::table('shipment_settlements', fn (Blueprint $table) => $table->unsignedBigInteger('courier_cost_cents')->default(0));
        Schema::create('courier_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fulfillment_id')->unique()->constrained()->restrictOnDelete();
            $table->string('provider');
            $table->string('mode')->default('manual');
            $table->string('api_environment')->nullable();
            $table->string('status')->default('draft')->index();
            $table->string('reference')->nullable();
            $table->text('tracking_url')->nullable();
            $table->unsignedBigInteger('cost_cents')->nullable();
            $table->string('provider_status')->nullable();
            $table->text('details')->nullable();
            $table->timestamp('quote_expires_at')->nullable();
            $table->timestamp('booked_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'reference']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courier_bookings');
        Schema::table('shipment_settlements', fn (Blueprint $table) => $table->dropColumn('courier_cost_cents'));
        Schema::table('fulfillments', function (Blueprint $table) {
            $table->dropIndex(['delivery_method']);
            $table->dropColumn('delivery_method');
        });
    }
};

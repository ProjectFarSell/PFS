<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fulfillments', function (Blueprint $table) {
            $table->timestamp('retry_at')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->string('return_disposition')->nullable();
            $table->json('fee_policy')->nullable();
        });
        Schema::create('delivery_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fulfillment_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('number');
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason');
            $table->string('contact_method');
            $table->text('note');
            $table->timestamps();
            $table->unique(['fulfillment_id', 'number']);
        });
        Schema::create('shipment_settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fulfillment_id')->unique()->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('goods_cents');
            $table->unsignedBigInteger('delivery_cents');
            $table->unsignedInteger('commission_bps');
            $table->unsignedBigInteger('commission_cents');
            $table->unsignedBigInteger('seller_cents');
            $table->unsignedBigInteger('rider_cents');
            $table->unsignedBigInteger('cod_cost_cents');
            $table->unsignedBigInteger('operating_cost_cents');
            $table->bigInteger('contribution_cents');
            $table->timestamp('earned_at');
            $table->timestamps();
        });
        Schema::create('settlement_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_settlement_id')->constrained()->restrictOnDelete();
            $table->foreignId('return_request_id')->unique()->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('refund_cents');
            $table->unsignedBigInteger('commission_reversal_cents');
            $table->unsignedBigInteger('seller_debit_cents');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settlement_adjustments');
        Schema::dropIfExists('shipment_settlements');
        Schema::dropIfExists('delivery_attempts');
        Schema::table('fulfillments', fn (Blueprint $table) => $table->dropColumn(['retry_at', 'returned_at', 'return_disposition', 'fee_policy']));
    }
};

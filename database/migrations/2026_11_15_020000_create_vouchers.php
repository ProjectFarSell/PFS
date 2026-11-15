<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name', 120);
            $table->string('type', 24);
            $table->foreignId('shop_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedInteger('value');
            $table->unsignedBigInteger('minimum_spend_cents')->default(0);
            $table->unsignedBigInteger('maximum_discount_cents')->nullable();
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('per_buyer_limit')->default(1);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
        Schema::create('voucher_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voucher_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->unique()->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('discount_cents');
            $table->unsignedBigInteger('shipping_discount_cents');
            $table->timestamp('created_at');
            $table->index(['voucher_id', 'user_id']);
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('discount_cents')->default(0);
            $table->unsignedBigInteger('shipping_discount_cents')->default(0);
            $table->json('voucher_snapshot')->nullable();
        });
        Schema::table('order_items', fn (Blueprint $table) => $table->unsignedBigInteger('discount_cents')->default(0));
        Schema::table('fulfillments', function (Blueprint $table) {
            $table->unsignedBigInteger('discount_cents')->default(0);
            $table->unsignedBigInteger('shipping_discount_cents')->default(0);
        });
        Schema::table('shipment_settlements', function (Blueprint $table) {
            $table->unsignedBigInteger('voucher_cents')->default(0);
            $table->unsignedBigInteger('seller_discount_cents')->default(0);
        });
        Schema::table('settlement_adjustments', fn (Blueprint $table) => $table->unsignedBigInteger('voucher_reversal_cents')->default(0));
    }

    public function down(): void
    {
        Schema::table('settlement_adjustments', fn (Blueprint $table) => $table->dropColumn('voucher_reversal_cents'));
        Schema::table('shipment_settlements', fn (Blueprint $table) => $table->dropColumn(['voucher_cents', 'seller_discount_cents']));
        Schema::table('fulfillments', fn (Blueprint $table) => $table->dropColumn(['discount_cents', 'shipping_discount_cents']));
        Schema::table('order_items', fn (Blueprint $table) => $table->dropColumn('discount_cents'));
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn(['discount_cents', 'shipping_discount_cents', 'voucher_snapshot']));
        Schema::dropIfExists('voucher_redemptions');
        Schema::dropIfExists('vouchers');
    }
};

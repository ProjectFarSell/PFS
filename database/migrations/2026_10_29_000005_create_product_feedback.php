<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('buyer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('feedback')->nullable();
            $table->boolean('is_anonymous')->default(false);
            $table->timestamps();
            $table->index(['product_id', 'created_at']);
        });

        Schema::create('product_review_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_review_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('mime_type');
            $table->timestamps();
        });

        Schema::create('shipment_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fulfillment_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('buyer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('seller_service_rating');
            $table->unsignedTinyInteger('delivery_speed_rating');
            $table->timestamps();
        });

        Schema::table('rider_delivery_ratings', function (Blueprint $table) {
            $table->json('tags')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('rider_delivery_ratings', fn (Blueprint $table) => $table->dropColumn('tags'));
        Schema::dropIfExists('shipment_feedback');
        Schema::dropIfExists('product_review_media');
        Schema::dropIfExists('product_reviews');
    }
};

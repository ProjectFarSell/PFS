<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fulfillments', function (Blueprint $table): void {
            $table->string('delivery_recipient_name')->nullable()->after('delivery_note');
            $table->string('delivery_photo_path')->nullable()->after('delivery_recipient_name');
            $table->text('delivery_pin')->nullable()->after('delivery_photo_path');
            $table->string('delivery_pin_hash')->nullable()->after('delivery_pin');
        });
    }

    public function down(): void
    {
        Schema::table('fulfillments', function (Blueprint $table): void {
            $table->dropColumn(['delivery_recipient_name', 'delivery_photo_path', 'delivery_pin', 'delivery_pin_hash']);
        });
    }
};

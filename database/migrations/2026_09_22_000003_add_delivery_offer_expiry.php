<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rider_delivery_requests', function (Blueprint $table) {
            $table->timestamp('offered_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('declined_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('rider_delivery_requests', function (Blueprint $table) {
            $table->dropIndex(['expires_at']);
            $table->dropColumn(['offered_at', 'expires_at', 'declined_at']);
        });
    }
};

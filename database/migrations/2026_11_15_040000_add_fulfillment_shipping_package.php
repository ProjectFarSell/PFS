<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fulfillments', fn (Blueprint $table) => $table->json('shipping_package')->nullable());
    }

    public function down(): void
    {
        Schema::table('fulfillments', fn (Blueprint $table) => $table->dropColumn('shipping_package'));
    }
};

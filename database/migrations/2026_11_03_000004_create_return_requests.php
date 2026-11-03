<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('return_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->unique()->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->string('reason', 30);
            $table->text('details');
            $table->string('evidence_path')->nullable();
            $table->string('status', 30)->default('pending')->index();
            $table->decimal('requested_amount', 12, 2);
            $table->decimal('approved_amount', 12, 2)->nullable();
            $table->boolean('return_required')->default(false);
            $table->timestamp('return_received_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->string('refund_reference')->nullable();
            $table->timestamps();
        });
        Schema::create('return_request_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('return_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 30);
            $table->text('note');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_request_events');
        Schema::dropIfExists('return_requests');
    }
};

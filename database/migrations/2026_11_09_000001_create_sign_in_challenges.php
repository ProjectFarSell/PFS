<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sign_in_challenges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('context_hash', 64);
            $table->string('identity_hash', 64);
            $table->string('channel', 10);
            $table->string('code_hash')->nullable();
            $table->string('phone_proof_hash', 64)->nullable()->unique();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('sent_at');
            $table->timestamp('expires_at')->index();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'context_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sign_in_challenges');
    }
};

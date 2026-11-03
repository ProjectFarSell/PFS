<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('security_version')->default(0);
            $table->timestamp('suspended_at')->nullable();
            $table->text('suspension_reason')->nullable();
            $table->text('mfa_secret')->nullable();
            $table->timestamp('mfa_confirmed_at')->nullable();
            $table->text('mfa_recovery_codes')->nullable();
            $table->unsignedBigInteger('mfa_last_step')->nullable();
        });
        Schema::create('security_audit_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 80)->index();
            $table->string('target_type', 80);
            $table->unsignedBigInteger('target_id')->nullable();
            $table->json('changes');
            $table->timestamp('created_at')->useCurrent()->index();
            $table->index(['target_type', 'target_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_audit_events');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['security_version', 'suspended_at', 'suspension_reason', 'mfa_secret', 'mfa_confirmed_at', 'mfa_recovery_codes', 'mfa_last_step']));
    }
};

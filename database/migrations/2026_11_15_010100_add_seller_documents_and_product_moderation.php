<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seller_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_application_id')->constrained('seller_applications')->cascadeOnDelete();
            $table->string('kind', 40)->default('business_permit');
            $table->string('file_path');
            $table->string('mime_type', 100);
            $table->unsignedInteger('size_bytes');
            $table->timestamps();
            $table->unique(['seller_application_id', 'kind']);
        });
        Schema::table('products', function (Blueprint $table) {
            $table->timestamp('admin_hidden_at')->nullable()->index();
            $table->foreignId('admin_hidden_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('admin_hide_reason')->nullable();
            $table->boolean('admin_restore_active')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['admin_hidden_at']);
            $table->dropForeign(['admin_hidden_by']);
            $table->dropColumn(['admin_hidden_at', 'admin_hidden_by', 'admin_hide_reason', 'admin_restore_active']);
        });
        Schema::dropIfExists('seller_documents');
    }
};

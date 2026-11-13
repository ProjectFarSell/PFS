<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private array $publicTables = ['users', 'products', 'product_variants', 'shops', 'categories', 'addresses', 'orders', 'order_items', 'fulfillments', 'fulfillment_events', 'rider_earnings', 'rider_delivery_requests', 'account_deletion_requests', 'psgc_regions', 'psgc_provinces', 'psgc_cities_municipalities', 'psgc_barangays'];

    public function up(): void
    {
        foreach ($this->publicTables as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->uuid('public_id')->nullable()->unique());
            DB::table($name)->orderBy('id')->chunkById(200, function ($rows) use ($name) {
                $bindings = [];
                $cases = [];
                foreach ($rows as $row) {
                    $cases[] = 'WHEN ? THEN ?';
                    $bindings[] = $row->id;
                    $bindings[] = (string) Str::uuid();
                }
                $ids = $rows->pluck('id')->all();
                DB::update('UPDATE '.DB::connection()->getQueryGrammar()->wrapTable($name).' SET public_id = CASE id '.implode(' ', $cases).' END WHERE id IN ('.implode(',', array_fill(0, count($ids), '?')).')', [...$bindings, ...$ids]);
            });
        }
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->text('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->string('mobile_app', 16)->nullable();
            $table->string('identity_hash', 64)->nullable();
            $table->timestamp('authenticated_at')->nullable();
            $table->timestamps();
        });
        Schema::create('mobile_auth_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('token_id')->nullable()->constrained('personal_access_tokens')->cascadeOnDelete();
            $table->string('secret_hash', 64)->unique();
            $table->string('context_hash', 64)->unique();
            $table->string('identity_hash', 64);
            $table->string('app', 16);
            $table->string('purpose', 16);
            $table->timestamp('expires_at')->index();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('mobile_carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('version')->default(0);
            $table->json('lines');
            $table->timestamps();
        });
        Schema::create('mobile_quotes', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('address_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->longText('snapshot');
            $table->timestamp('expires_at')->index();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('mobile_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('scope', 64);
            $table->uuid('request_key');
            $table->string('payload_hash', 64);
            $table->unsignedSmallInteger('status');
            $table->longText('result');
            $table->timestamp('created_at')->useCurrent()->index();
            $table->unique(['user_id', 'scope', 'request_key']);
        });
        Schema::create('mobile_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('token_id')->constrained('personal_access_tokens')->cascadeOnDelete();
            $table->uuid('installation_id')->unique();
            $table->string('platform', 16);
            $table->string('app', 16);
            $table->text('fcm_token');
            $table->string('token_hash', 64)->unique();
            $table->json('enabled_events');
            $table->uuid('binding_version');
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });
        Schema::create('mobile_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('event_key')->unique();
            $table->string('type', 80);
            $table->json('audience');
            $table->json('payload');
            $table->foreignId('fulfillment_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('expires_at')->index();
            $table->timestamp('broadcast_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->string('last_error')->nullable();
            $table->timestamps();
        });
        Schema::create('mobile_push_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mobile_event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mobile_device_id')->constrained()->cascadeOnDelete();
            $table->uuid('binding_version');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('skipped_at')->nullable();
            $table->timestamps();
            $table->unique(['mobile_event_id', 'mobile_device_id', 'binding_version'], 'mobile_delivery_binding');
        });
    }

    public function down(): void
    {
        foreach (['mobile_push_deliveries', 'mobile_events', 'mobile_devices', 'mobile_receipts', 'mobile_quotes', 'mobile_carts', 'mobile_auth_tickets', 'personal_access_tokens'] as $table) {
            Schema::dropIfExists($table);
        }
        foreach ($this->publicTables as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropUnique(['public_id']);
                $table->dropColumn('public_id');
            });
        }
    }
};

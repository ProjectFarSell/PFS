<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rider_earnings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fulfillment_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('rider_profile_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 10, 2);
            $table->timestamp('earned_at');
            $table->timestamps();
            $table->index(['rider_profile_id', 'earned_at']);
        });

        Schema::create('rider_delivery_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fulfillment_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('buyer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('rider_profile_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('feedback')->nullable();
            $table->timestamps();
            $table->index(['rider_profile_id', 'created_at']);
        });

        // Preserve a ledger row for completed deliveries that predate this feature.
        DB::table('fulfillments')->where('status', 'completed')->whereNotNull('rider_id')
            ->orderBy('id')->chunkById(100, function ($fulfillments) {
                foreach ($fulfillments as $fulfillment) {
                    $earnedAt = DB::table('fulfillment_events')
                        ->where('fulfillment_id', $fulfillment->id)
                        ->where('status', 'completed')
                        ->orderByDesc('created_at')
                        ->value('created_at') ?? $fulfillment->updated_at;

                    DB::table('rider_earnings')->insertOrIgnore([
                        'fulfillment_id' => $fulfillment->id,
                        'rider_profile_id' => $fulfillment->rider_id,
                        'amount' => $fulfillment->shipping_fee,
                        'earned_at' => $earnedAt,
                        'created_at' => $earnedAt,
                        'updated_at' => $earnedAt,
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('rider_delivery_ratings');
        Schema::dropIfExists('rider_earnings');
    }
};

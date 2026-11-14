<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private array $tables = ['conversations', 'messages', 'product_reviews', 'product_review_media', 'return_requests', 'buyer_notifications'];

    public function up(): void
    {
        foreach ($this->tables as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->uuid('public_id')->nullable()->unique());
            DB::table($name)->orderBy('id')->chunkById(200, function ($rows) use ($name) {
                $cases = [];
                $bindings = [];
                foreach ($rows as $row) {
                    $cases[] = 'WHEN ? THEN ?';
                    $bindings[] = $row->id;
                    $bindings[] = (string) Str::uuid();
                }
                $ids = $rows->pluck('id')->all();
                DB::update('UPDATE '.DB::connection()->getQueryGrammar()->wrapTable($name).' SET public_id = CASE id '.implode(' ', $cases).' END WHERE id IN ('.implode(',', array_fill(0, count($ids), '?')).')', [...$bindings, ...$ids]);
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropUnique([$column = 'public_id']));
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn('public_id'));
        }
    }
};

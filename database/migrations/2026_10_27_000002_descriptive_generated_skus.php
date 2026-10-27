<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('products')->where('sku', 'like', 'FS-PROD-%')->orderBy('id')->each(function ($product) {
            $base = $this->nameCode($product->name).'-'.str_pad((string) $product->id, 6, '0', STR_PAD_LEFT);
            $sku = $base;
            $suffix = 1;
            while (DB::table('products')->where('sku', $sku)->where('id', '!=', $product->id)->exists()
                || DB::table('product_variants')->where('sku', $sku)->exists()) {
                $sku = $base.'-'.$suffix++;
            }
            DB::table('products')->where('id', $product->id)->update(['sku' => $sku]);
        });

        DB::table('product_variants')->orderBy('id')->each(function ($variant) {
            if (! preg_match('/^FS-[A-Z0-9]{12}$/', $variant->sku)) {
                return;
            }

            $product = DB::table('products')->where('id', $variant->product_id)->first();
            if (! $product) {
                return;
            }

            $options = json_decode($variant->options ?? '[]', true) ?: [];
            if (! $options && $variant->option_name && $variant->option_value) {
                $options = [$variant->option_name => $variant->option_value];
            }
            $optionCode = collect($options)->map(fn ($value, $name) =>
                $this->token((string) $name, 6).'-'.$this->token((string) $value, 16)
            )->join('-');
            $base = $this->nameCode($product->name).'-'.str_pad((string) $product->id, 6, '0', STR_PAD_LEFT)
                .($optionCode !== '' ? '-'.substr($optionCode, 0, 48) : '-VAR-'.$variant->id);
            $sku = $base;
            $suffix = 1;
            while (DB::table('products')->where('sku', $sku)->exists()
                || DB::table('product_variants')->where('sku', $sku)->where('id', '!=', $variant->id)->exists()) {
                $sku = $base.'-'.$suffix++;
            }
            DB::table('product_variants')->where('id', $variant->id)->update(['sku' => $sku]);
        });

        DB::table('order_items')->orderBy('id')->each(function ($item) {
            $sku = $item->product_variant_id
                ? DB::table('product_variants')->where('id', $item->product_variant_id)->value('sku')
                : null;
            $sku ??= $item->product_id
                ? DB::table('products')->where('id', $item->product_id)->value('sku')
                : null;

            DB::table('order_items')->where('id', $item->id)->update(['product_sku' => $sku]);
        });
    }

    public function down(): void
    {
        // Generated SKUs remain valid identifiers if this data migration is rolled back.
    }

    private function nameCode(string $name): string
    {
        return trim(substr(Str::upper(Str::slug($name)), 0, 40), '-') ?: 'PRODUCT';
    }

    private function token(string $value, int $length): string
    {
        return trim(substr(Str::upper(Str::slug($value)), 0, $length), '-') ?: 'VALUE';
    }
};

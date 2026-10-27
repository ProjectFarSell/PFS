<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('products')->whereNotNull('sku')->orderBy('id')->each(function ($product) {
            $oldName = trim(substr(Str::upper(Str::slug($product->name)), 0, 40), '-') ?: 'PRODUCT';
            $previousGenerated = $oldName.'-'.str_pad((string) $product->id, 6, '0', STR_PAD_LEFT);
            if ($product->sku !== $previousGenerated && ! preg_match('/^FS-PROD-\d+$/', $product->sku)) {
                return;
            }

            $sku = $this->productCode($product->name).'-'.str_pad((string) $product->id, 4, '0', STR_PAD_LEFT);
            DB::table('products')->where('id', $product->id)->update(['sku' => $sku]);
        });

        DB::table('product_variants')->orderBy('id')->each(function ($variant) {
            $product = DB::table('products')->where('id', $variant->product_id)->first();
            if (! $product) {
                return;
            }

            $oldName = trim(substr(Str::upper(Str::slug($product->name)), 0, 40), '-') ?: 'PRODUCT';
            $oldPrefix = $oldName.'-'.str_pad((string) $product->id, 6, '0', STR_PAD_LEFT).'-';
            $isPreviousGenerated = str_starts_with($variant->sku, $oldPrefix);
            $isFactoryGenerated = (bool) preg_match('/^SKU-[A-Z0-9]{4}-\d{4}$/', $variant->sku);
            $isOriginalGenerated = (bool) preg_match('/^FS-[A-Z0-9]{12}$/', $variant->sku);
            if (! $isPreviousGenerated && ! $isFactoryGenerated && ! $isOriginalGenerated) {
                return;
            }

            $options = json_decode($variant->options ?? '[]', true) ?: [];
            if (! $options && $variant->option_name && $variant->option_value) {
                $options = [$variant->option_name => $variant->option_value];
            }
            $sku = $this->variantCode($product, $variant, $options);
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
        // Short SKUs remain valid if this data migration is rolled back.
    }

    private function productCode(string $name): string
    {
        $words = preg_split('/[^A-Z0-9]+/', Str::upper($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $code = count($words) > 1
            ? implode('', array_map(fn ($word) => $word[0], array_slice($words, 0, 3)))
            : substr($words[0] ?? 'PRD', 0, 3);

        return str_pad(substr($code ?: 'PRD', 0, 3), 3, 'X');
    }

    private function variantCode(object $product, object $variant, array $options): string
    {
        $ordered = collect($options)->sortBy(fn ($value, $name) => match (mb_strtolower((string) $name)) {
            'color' => 0,
            'size' => 1,
            default => 2,
        });
        $optionCode = $ordered->take(2)->map(fn ($value, $name) => $this->optionCode((string) $name, (string) $value))->join('');
        $productIdCode = strtoupper(base_convert((string) $product->id, 10, 36));
        $variantIdCode = strtoupper(base_convert((string) $variant->id, 10, 36));
        $prefix = $this->productCode($product->name);
        $maxPrefix = max(1, 12 - strlen($productIdCode) - 1 - strlen($optionCode) - strlen($variantIdCode));

        return substr($prefix, 0, $maxPrefix).$productIdCode.'-'.$optionCode.$variantIdCode;
    }

    private function optionCode(string $name, string $value): string
    {
        $normalized = mb_strtolower(trim($value));
        $codes = [
            'black' => 'BK', 'white' => 'WH', 'black/white' => 'BW', 'black and white' => 'BW',
            'red' => 'RD', 'blue' => 'BL', 'green' => 'GN', 'yellow' => 'YL', 'pink' => 'PK',
            'purple' => 'PU', 'gray' => 'GY', 'grey' => 'GY', 'brown' => 'BN', 'orange' => 'OR',
            'xs' => 'XS', 's' => 'S', 'm' => 'M', 'l' => 'L', 'xl' => 'XL', 'xxl' => '2X', 'xxxl' => '3X',
        ];
        if (mb_strtolower($name) === 'size' && preg_match('/\d+(?:\.\d+)?/', $normalized, $match)) {
            return substr(preg_replace('/\D/', '', $match[0]), 0, 3) ?: 'SZ';
        }

        $code = $codes[$normalized] ?? strtoupper(substr(preg_replace('/[^a-z0-9]/i', '', $value) ?: 'XX', 0, 2));

        return str_pad(substr($code, 0, 2), 2, 'X');
    }
};

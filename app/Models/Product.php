<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'shop_id',
        'category_id',
        'name',
        'sku',
        'slug',
        'description',
        'price',
        'compare_at_price',
        'stock',
        'has_variants',
        'image_path',
        'is_flash',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'compare_at_price' => 'decimal:2',
            'has_variants' => 'boolean',
            'is_flash' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Product $product) {
            $product->slug ??= Str::slug($product->name).'-'.Str::lower(Str::random(5));
        });
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function savedItems(): HasMany
    {
        return $this->hasMany(SavedItem::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class);
    }

    public function formattedPrice(): string
    {
        return 'PHP '.number_format((float) $this->price, 2);
    }

    /**
     * Returns the public URL for the product image served from Cloudflare R2.
     */
    public function imageUrl(): ?string
    {
        if (! $this->image_path) {
            return null;
        }

        $baseUrl = rtrim(config('filesystems.disks.r2.url'), '/');

        return $baseUrl.'/'.ltrim($this->image_path, '/');
    }

    public function availableStock(): int
    {
        if (array_key_exists('available_stock', $this->attributes)) {
            return (int) $this->attributes['available_stock'];
        }

        if (! $this->has_variants) {
            return (int) $this->stock;
        }

        return (int) ($this->relationLoaded('variants')
            ? $this->variants->where('is_active', true)->sum('stock')
            : $this->variants()->where('is_active', true)->sum('stock'));
    }

    public function scopeWithAvailableStock($query)
    {
        return $query->select('products.*')->addSelect(DB::raw(
            'CASE WHEN products.has_variants = 1 THEN COALESCE('
            .'(SELECT SUM(product_variants.stock) FROM product_variants '
            .'WHERE product_variants.product_id = products.id AND product_variants.is_active = 1), 0) '
            .'ELSE products.stock END AS available_stock'
        ));
    }

    public function scopeLowStock($query, int $threshold = 5)
    {
        return $query->withAvailableStock()
            ->where('products.is_active', true)
            ->whereRaw(
                'CASE WHEN products.has_variants = 1 THEN COALESCE('
                .'(SELECT SUM(product_variants.stock) FROM product_variants '
                .'WHERE product_variants.product_id = products.id AND product_variants.is_active = 1), 0) '
                .'ELSE products.stock END <= ?',
                [$threshold]
            );
    }

    public function editVersion(): string
    {
        $variants = $this->variants()->orderBy('id')->get(['id', 'sku', 'stock', 'is_active', 'price_override', 'options', 'updated_at'])
            ->map(fn ($variant) => $variant->getAttributes())->all();

        return hash('sha256', json_encode([$this->getAttributes(), $variants], JSON_THROW_ON_ERROR));
    }

    public function scopeVisible($query)
    {
        return $query->where('is_active', true)->whereHas('shop', fn ($shop) => $shop->where('is_active', true));
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}

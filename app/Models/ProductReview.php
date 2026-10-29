<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductReview extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['rating' => 'integer', 'is_anonymous' => 'boolean'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function media(): HasMany
    {
        return $this->hasMany(ProductReviewMedia::class)->orderBy('id');
    }

    public function authorName(): string
    {
        return $this->is_anonymous ? 'Anonymous buyer' : ($this->buyer?->name ?? 'Buyer');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductReviewMedia extends Model
{
    protected $table = 'product_review_media';

    protected $guarded = ['id'];

    public function review(): BelongsTo
    {
        return $this->belongsTo(ProductReview::class, 'product_review_id');
    }

    public function isVideo(): bool
    {
        return str_starts_with($this->mime_type, 'video/');
    }
}

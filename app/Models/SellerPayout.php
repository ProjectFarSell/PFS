<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SellerPayout extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['amount_cents' => 'integer', 'paid_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    public function shop()
    {
        return $this->belongsTo(Shop::class);
    }
}

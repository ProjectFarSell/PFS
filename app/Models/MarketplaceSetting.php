<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketplaceSetting extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['commission_bps' => 'integer', 'delivery_fee_cents' => 'integer', 'payout_hold_days' => 'integer', 'version' => 'integer'];
    }
}

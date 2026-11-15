<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RiderCodRemittance extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['amount_cents' => 'integer', 'received_at' => 'datetime', 'voided_at' => 'datetime'];
    }

    public function rider()
    {
        return $this->belongsTo(RiderProfile::class, 'rider_profile_id');
    }
}

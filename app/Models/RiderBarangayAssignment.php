<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiderBarangayAssignment extends Model
{
    protected $guarded = ['id'];

    public function riderProfile(): BelongsTo
    {
        return $this->belongsTo(RiderProfile::class);
    }

    public function barangay(): BelongsTo
    {
        return $this->belongsTo(PsgcBarangay::class, 'psgc_barangay_id');
    }
}

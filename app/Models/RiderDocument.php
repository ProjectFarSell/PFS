<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RiderDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'rider_profile_id',
        'document_type',
        'file_path',
        'verified',
    ];

    protected function casts(): array
    {
        return [
            'verified' => 'boolean',
        ];
    }

    public function riderProfile()
    {
        return $this->belongsTo(RiderProfile::class);
    }
}

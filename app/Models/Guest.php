<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Guest extends Model
{
    use HasFactory;

    protected $fillable = [
        'session_token',
        'converted_user_id',
        'cart_data',
    ];

    protected function casts(): array
    {
        return [
            'cart_data' => 'array',
        ];
    }

    public function convertedUser()
    {
        return $this->belongsTo(User::class, 'converted_user_id');
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SignInChallenge extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['code_hash', 'context_hash', 'identity_hash', 'phone_proof_hash'];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime', 'expires_at' => 'datetime', 'consumed_at' => 'datetime', 'attempts' => 'integer'];
    }
}

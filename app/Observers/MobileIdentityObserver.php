<?php

namespace App\Observers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class MobileIdentityObserver
{
    public function creating(Model $model): void
    {
        // Never accept a caller-provided public ID on a new record.
        $model->setAttribute('public_id', (string) Str::uuid());
    }
}

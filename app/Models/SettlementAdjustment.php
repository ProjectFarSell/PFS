<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SettlementAdjustment extends Model
{
    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Settlement adjustments cannot be overwritten.'));
        static::deleting(fn () => throw new \LogicException('Settlement adjustments must be retained.'));
    }
}

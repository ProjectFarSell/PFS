<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class SecurityAuditEvent extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['changes' => 'array', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Audit events cannot be edited.'));
        static::deleting(fn () => throw new LogicException('Audit events cannot be deleted through the application.'));
    }
}

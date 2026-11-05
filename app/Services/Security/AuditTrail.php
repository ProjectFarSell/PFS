<?php

namespace App\Services\Security;

use App\Models\SecurityAuditEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AuditTrail
{
    public function record(string $action, Model $target, array $changes = []): void
    {
        SecurityAuditEvent::query()->create([
            'actor_id' => auth()->id() && User::query()->whereKey(auth()->id())->exists() ? auth()->id() : null, 'action' => $action,
            'target_type' => class_basename($target), 'target_id' => $target->getKey(),
            'changes' => $changes, 'created_at' => now(),
        ]);
    }
}

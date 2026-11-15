<?php

namespace App\Services\Security;

use App\Models\SecurityAuditEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AuditTrail
{
    public function record(string $action, Model $target, array $changes = [], ?User $actor = null): void
    {
        $actorId = $actor?->id ?? auth()->id();
        SecurityAuditEvent::query()->create([
            'actor_id' => $actorId && User::query()->whereKey($actorId)->exists() ? $actorId : null, 'action' => $action,
            'target_type' => class_basename($target), 'target_id' => $target->getKey(),
            'changes' => $changes, 'created_at' => now(),
        ]);
    }
}

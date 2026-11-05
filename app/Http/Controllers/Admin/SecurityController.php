<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\SecurityAuditEvent;
use App\Models\User;
use App\Services\Security\AccountSecurity;
use App\Services\Security\AuditTrail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SecurityController extends Controller
{
    public function index(Request $request)
    {
        return $this->accounts($request, 'active');
    }

    public function suspended(Request $request)
    {
        return $this->accounts($request, 'suspended');
    }

    public function restoration(Request $request)
    {
        return $this->accounts($request, 'restore');
    }

    private function accounts(Request $request, string $section)
    {
        $suspended = $section !== 'active';
        $pageRoute = match ($section) {
            'suspended' => 'admin.security.accounts.suspended',
            'restore' => 'admin.security.accounts.restore',
            default => 'admin.security.accounts',
        };
        $data = $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $users = User::query()->when($suspended, fn ($q) => $q->whereNotNull('suspended_at'), fn ($q) => $q->whereNull('suspended_at'))
            ->when($data['q'] ?? null, fn ($q, $search) => $q->where(fn ($q) => $q->where('email', 'like', '%'.$search.'%')->orWhere('name', 'like', '%'.$search.'%')))
            ->orderByDesc('suspended_at')->orderBy('id')->paginate(20)->withQueryString();

        $activeCount = User::query()->whereNull('suspended_at')->count();
        $suspendedCount = User::query()->whereNotNull('suspended_at')->count();

        return view('admin.security.accounts', compact('users', 'suspended', 'section', 'pageRoute', 'activeCount', 'suspendedCount'));
    }

    public function update(Request $request, User $user, AccountSecurity $security)
    {
        $data = $request->validate(['action' => ['required', Rule::in(['suspend', 'restore'])], 'reason' => ['required', 'string', 'min:5', 'max:1000'], 'restore_shop' => ['nullable', 'boolean']]);
        abort_if($user->id === $request->user()->id || $user->role === UserRole::Admin, 403, 'Admin accounts cannot be suspended here.');
        DB::transaction(function () use ($user, $data, $security) {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            abort_if($user->role === UserRole::Admin, 403);
            $suspend = $data['action'] === 'suspend';
            if ($suspend === ($user->suspended_at !== null)) {
                return;
            }
            $user->forceFill(['suspended_at' => $suspend ? now() : null, 'suspension_reason' => $data['reason'], 'security_version' => $user->security_version + 1, 'remember_token' => Str::random(60)])->save();
            // Restoring an account never republishes its shop automatically.
            if ($suspend && $user->shop) {
                $user->shop->update(['is_active' => false]);
            }
            if (! $suspend && ($data['restore_shop'] ?? false) && $user->shop) {
                $user->shop->update(['is_active' => true]);
            }
            $security->deleteSessions($user);
            app(AuditTrail::class)->record('account.'.$data['action'], $user);
        }, 3);

        return back()->with('status', 'Account access updated. Existing orders and history were retained.');
    }

    public function audit(Request $request)
    {
        $data = $request->validate(['action' => ['nullable', 'string', 'max:80'], 'actor_id' => ['nullable', 'integer', 'min:1']]);
        $events = SecurityAuditEvent::query()->when($data['action'] ?? null, fn ($q, $action) => $q->where('action', $action))
            ->when($data['actor_id'] ?? null, fn ($q, $id) => $q->where('actor_id', $id))->latest('id')->paginate(30)->withQueryString();

        return view('admin.security.audit', compact('events'));
    }
}

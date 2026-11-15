<?php

namespace App\Services\Orders;

use App\Enums\UserRole;
use App\Models\MarketplaceSetting;
use App\Models\User;
use App\Services\Security\AuditTrail;
use Illuminate\Validation\ValidationException;

class MarketplaceSettings
{
    public function current(bool $lock = false): MarketplaceSetting
    {
        return MarketplaceSetting::query()->when($lock, fn ($query) => $query->lockForUpdate())->findOrFail(1);
    }

    public function update(User $admin, array $values): MarketplaceSetting
    {
        $current = $admin->fresh();
        abort_unless($current?->role === UserRole::Admin && ! $current->suspended_at, 403);
        $data = validator($values, ['commission_bps' => ['required', 'integer', 'between:0,10000'],
            'delivery_fee_cents' => ['required', 'integer', 'between:0,100000'],
            'payout_hold_days' => ['required', 'integer', 'between:0,90'], 'version' => ['required', 'integer', 'min:1']])->validate();

        return app(FinancialTransaction::class)->run(function () use ($data, $admin) {
            $row = $this->current(true);
            if ($row->version !== (int) $data['version']) {
                throw ValidationException::withMessages(['version' => 'Settings changed. Reload the form before saving.']);
            }
            $before = $row->only(['commission_bps', 'delivery_fee_cents', 'payout_hold_days', 'version']);
            $row->forceFill(['commission_bps' => (int) $data['commission_bps'], 'delivery_fee_cents' => (int) $data['delivery_fee_cents'],
                'payout_hold_days' => (int) $data['payout_hold_days'], 'version' => $row->version + 1, 'updated_by' => $admin->id])->save();
            app(AuditTrail::class)->record('marketplace_settings.updated', $row, ['before' => $before, 'after' => $row->only(array_keys($before))], $admin);

            return $row;
        });
    }
}

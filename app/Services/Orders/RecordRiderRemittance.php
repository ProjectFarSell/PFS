<?php

namespace App\Services\Orders;

use App\Enums\UserRole;
use App\Models\RiderCodRemittance;
use App\Models\RiderProfile;
use App\Models\User;
use App\Services\Security\AuditTrail;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class RecordRiderRemittance
{
    public function record(RiderProfile $rider, User $admin, int $amountCents, Carbon $receivedAt, string $note): RiderCodRemittance
    {
        $this->admin($admin);
        if ($receivedAt->isFuture()) {
            throw ValidationException::withMessages(['received_at' => 'The received date cannot be in the future.']);
        }
        $note = trim($note);
        if ($note === '' || mb_strlen($note) > 1000) {
            throw ValidationException::withMessages(['note' => 'Enter a note, up to 1000 characters.']);
        }

        return app(FinancialTransaction::class)->run(function () use ($rider, $admin, $amountCents, $receivedAt, $note) {
            $locked = RiderProfile::whereKey($rider->id)->lockForUpdate()->firstOrFail();
            $balance = app(RiderCodBalance::class)->forRider($locked);
            if ($amountCents <= 0 || $amountCents > $balance['owed_cents']) {
                throw ValidationException::withMessages(['amount' => 'Enter a positive amount no greater than the current COD balance.']);
            }
            $row = RiderCodRemittance::create(['rider_profile_id' => $locked->id, 'amount_cents' => $amountCents,
                'received_at' => $receivedAt->copy()->utc(), 'note' => $note, 'recorded_by' => $admin->id]);
            app(AuditTrail::class)->record('rider_cod_remittance.recorded', $row, ['rider_profile_id' => $locked->id, 'amount_cents' => $amountCents, 'received_at' => $row->received_at->toIso8601String()], $admin);

            return $row;
        });
    }

    public function void(RiderCodRemittance $remittance, User $admin, string $reason): void
    {
        $this->admin($admin);
        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 1000) {
            throw ValidationException::withMessages(['void_reason' => 'Enter a void reason, up to 1000 characters.']);
        }
        app(FinancialTransaction::class)->run(function () use ($remittance, $admin, $reason) {
            RiderProfile::whereKey($remittance->rider_profile_id)->lockForUpdate()->firstOrFail();
            $row = RiderCodRemittance::whereKey($remittance->id)->lockForUpdate()->firstOrFail();
            if ($row->voided_at) {
                throw ValidationException::withMessages(['remittance' => 'This remittance has already been voided.']);
            }
            $row->forceFill(['voided_at' => now(), 'voided_by' => $admin->id, 'void_reason' => $reason])->save();
            app(AuditTrail::class)->record('rider_cod_remittance.voided', $row, ['amount_cents' => $row->amount_cents, 'reason' => $reason], $admin);
        });
    }

    private function admin(User $admin): void
    {
        $current = $admin->fresh();
        abort_unless($current?->role === UserRole::Admin && ! $current->suspended_at, 403);
    }
}

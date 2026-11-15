<?php

namespace App\Services\Orders;

use App\Enums\UserRole;
use App\Models\SellerPayout;
use App\Models\User;
use App\Services\Security\AuditTrail;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManageSellerPayout
{
    public function __construct(
        private readonly FinancialTransaction $transactions,
        private readonly FinancialLocks $locks,
        private readonly SellerPayoutEligibility $eligibility,
    ) {}

    public function cancel(SellerPayout $payout, User $admin, string $reason): void
    {
        $this->admin($admin);
        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 1000) {
            throw ValidationException::withMessages(['cancellation_reason' => 'Enter a cancellation reason, up to 1000 characters.']);
        }
        $this->transactions->run(function () use ($payout, $admin, $reason) {
            $this->locks->shop($payout->shop_id);
            $row = SellerPayout::whereKey($payout->id)->lockForUpdate()->firstOrFail();
            $this->unpaid($row);
            $items = DB::table('seller_payout_items')->where('seller_payout_id', $row->id)->orderBy('seller_payable_entry_id')->get();
            $this->locks->entries($row->shop_id, $items->pluck('seller_payable_entry_id')->all());
            DB::table('seller_payout_items')->where('seller_payout_id', $row->id)->update(['active_entry_id' => null]);
            $row->forceFill(['status' => 'cancelled', 'cancelled_by' => $admin->id, 'cancelled_at' => now(), 'cancellation_reason' => $reason])->save();
            app(AuditTrail::class)->record('seller_payout.cancelled', $row, ['status' => ['unpaid', 'cancelled'], 'reason' => $reason], $admin);
        });
    }

    public function pay(SellerPayout $payout, User $admin, string $reference, Carbon $paidAt, ?int $holdDays = null): void
    {
        $this->admin($admin);
        $holdDays ??= app(MarketplaceSettings::class)->current()->payout_hold_days;
        $reference = trim($reference);
        if ($reference === '' || mb_strlen($reference) > 120) {
            throw ValidationException::withMessages(['payment_reference' => 'Enter a payment reference, up to 120 characters.']);
        }
        if ($paidAt->isFuture()) {
            throw ValidationException::withMessages(['paid_at' => 'The payment date cannot be in the future.']);
        }
        $this->transactions->run(function () use ($payout, $admin, $reference, $paidAt, $holdDays) {
            $this->locks->shop($payout->shop_id);
            $row = SellerPayout::whereKey($payout->id)->lockForUpdate()->firstOrFail();
            $this->unpaid($row);
            $ids = DB::table('seller_payable_entries')->where('shop_id', $row->shop_id)->orderBy('id')->pluck('id')->all();
            $entries = $this->locks->entries($row->shop_id, $ids)->keyBy('id');
            $items = DB::table('seller_payout_items')->where('seller_payout_id', $row->id)->orderBy('seller_payable_entry_id')->get();
            $sum = 0;
            foreach ($items as $item) {
                $entry = $entries->get($item->seller_payable_entry_id);
                if (! $entry || (int) $item->active_entry_id !== (int) $entry->id
                    || (int) $item->amount_cents !== (int) $entry->amount_cents || ! $this->eligibility->isEligible($entry, $holdDays)) {
                    $this->stale();
                }
                $sum += (int) $item->amount_cents;
            }
            if ($items->isEmpty() || $sum <= 0 || $sum !== $row->amount_cents) {
                $this->stale();
            }
            // New refund debits must be reconciled before the recorded batch is paid.
            $active = DB::table('seller_payout_items')->whereIn('active_entry_id', $entries->keys())->pluck('active_entry_id')->map(fn ($id) => (int) $id);
            if ($entries->contains(fn ($entry) => $entry->settlement_adjustment_id !== null && ! $active->contains((int) $entry->id))) {
                $this->stale();
            }
            $row->forceFill(['status' => 'paid', 'paid_by' => $admin->id, 'paid_at' => $paidAt->copy()->utc(), 'payment_reference' => $reference])->save();
            app(AuditTrail::class)->record('seller_payout.paid', $row, ['status' => ['unpaid', 'paid'], 'amount_cents' => $sum, 'payment_reference' => $reference, 'paid_at' => $row->paid_at->toIso8601String()], $admin);
        });
    }

    private function admin(User $admin): void
    {
        $current = $admin->fresh();
        abort_unless($current?->role === UserRole::Admin && ! $current->suspended_at, 403);
    }

    private function unpaid(SellerPayout $row): void
    {
        if ($row->status !== 'unpaid') {
            throw ValidationException::withMessages(['payout' => 'Only an unpaid payout can be changed.']);
        }
    }

    private function stale(): never
    {
        throw ValidationException::withMessages(['payout' => 'The batch eligibility or balance changed. Cancel this unpaid batch and rebuild it before recording payment.']);
    }
}

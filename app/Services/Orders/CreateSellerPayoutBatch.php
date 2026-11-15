<?php

namespace App\Services\Orders;

use App\Enums\UserRole;
use App\Models\SellerPayout;
use App\Models\Shop;
use App\Models\User;
use App\Services\Security\AuditTrail;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

class CreateSellerPayoutBatch
{
    public function __construct(
        private readonly FinancialTransaction $transactions,
        private readonly FinancialLocks $locks,
        private readonly SellerPayoutEligibility $eligibility,
    ) {}

    /** @return array{id: int, created: bool} */
    public function create(Shop $shop, User $admin, ?int $holdDays = null): array
    {
        $current = $admin->fresh();
        abort_unless($current?->role === UserRole::Admin && ! $current->suspended_at, 403);
        $holdDays ??= app(MarketplaceSettings::class)->current()->payout_hold_days;

        return $this->transactions->run(function () use ($shop, $admin, $holdDays): array {
            $this->locks->shop($shop->id);
            $existing = DB::table('seller_payouts')->where('shop_id', $shop->id)->where('status', 'unpaid')->first();
            if ($existing) {
                return ['id' => (int) $existing->id, 'created' => false];
            }

            $ids = DB::table('seller_payable_entries as entries')->where('entries.shop_id', $shop->id)
                ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('seller_payout_items as items')->whereColumn('items.active_entry_id', 'entries.id'))
                ->orderBy('entries.id')->pluck('entries.id')->all();
            $entries = $this->locks->entries($shop->id, $ids)->filter(fn ($entry) => $this->eligibility->isEligible($entry, $holdDays));
            $total = $entries->reduce(fn (int $sum, $entry): int => $sum + (int) $entry->amount_cents, 0);
            if ($total <= 0) {
                throw ValidationException::withMessages(['payout' => 'The eligible balance must be positive.']);
            }

            $payoutId = DB::table('seller_payouts')->insertGetId(['shop_id' => $shop->id, 'status' => 'unpaid', 'amount_cents' => $total, 'created_by' => $admin->id, 'created_at' => now(), 'updated_at' => now()]);
            foreach ($entries as $entry) {
                DB::table('seller_payout_items')->insert(['seller_payout_id' => $payoutId, 'seller_payable_entry_id' => $entry->id, 'active_entry_id' => $entry->id, 'amount_cents' => (int) $entry->amount_cents]);
            }
            if ((int) DB::table('seller_payout_items')->where('seller_payout_id', $payoutId)->sum('amount_cents') !== $total) {
                throw new LogicException('Payout amount does not match its items.');
            }
            app(AuditTrail::class)->record('seller_payout.created', SellerPayout::findOrFail($payoutId), ['shop_id' => $shop->id, 'amount_cents' => $total], $admin);

            return ['id' => (int) $payoutId, 'created' => true];
        });
    }
}

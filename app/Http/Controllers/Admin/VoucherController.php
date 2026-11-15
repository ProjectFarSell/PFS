<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Models\Voucher;
use App\Services\Security\AuditTrail;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class VoucherController extends Controller
{
    public function index()
    {
        return view('admin.vouchers.index', ['vouchers' => Voucher::with('shop')->select('vouchers.*')->when(auth()->user()->role === UserRole::Seller, fn ($q) => $q->where('shop_id', $this->shop()->id))
            ->selectSub(DB::table('voucher_redemptions')->selectRaw('COUNT(*)')->whereColumn('voucher_id', 'vouchers.id'), 'uses')
            ->latest('id')->paginate(20)]);
    }

    public function create()
    {
        return view('admin.vouchers.form', ['voucher' => new Voucher(['type' => 'fixed', 'per_buyer_limit' => 1, 'is_active' => true])]);
    }

    public function edit(Voucher $voucher)
    {
        $this->owned($voucher);

        return view('admin.vouchers.form', compact('voucher'));
    }

    public function store(Request $request)
    {
        return $this->save($request, new Voucher);
    }

    public function update(Request $request, Voucher $voucher)
    {
        return $this->save($request, $voucher);
    }

    private function save(Request $request, Voucher $voucher)
    {
        if ($voucher->exists) {
            $this->owned($voucher);
        }
        $shopId = $voucher->exists ? $voucher->shop_id : ($request->user()->role === UserRole::Seller ? $this->shop()->id : null);
        if (is_string($request->input('code'))) {
            $request->merge(['code' => strtoupper(trim($request->input('code')))]);
        }
        $data = $request->validate([
            'code' => ['required', 'string', 'regex:/^[A-Z0-9][A-Z0-9_-]{2,31}$/D', Rule::unique('vouchers')->ignore($voucher->id)],
            'name' => ['required', 'string', 'max:120'], 'type' => ['required', Rule::in($shopId === null ? ['fixed', 'percentage', 'free_shipping'] : ['fixed', 'percentage'])],
            'discount_value' => ['required_unless:type,free_shipping', 'nullable', 'numeric', 'decimal:0,2', 'min:0.01', 'max:1000000'],
            'minimum_spend' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:1000000'],
            'maximum_discount' => ['nullable', 'numeric', 'decimal:0,2', 'min:0.01', 'max:1000000'],
            'usage_limit' => ['nullable', 'integer', 'between:1,1000000'], 'per_buyer_limit' => ['required', 'integer', 'between:1,1000'],
            'starts_at' => ['nullable', 'date'], 'expires_at' => ['nullable', 'date', 'after:starts_at'],
            'is_active' => ['required', 'boolean'], 'version' => [$voucher->exists ? 'required' : 'nullable', 'integer', 'min:1'],
        ]);
        if ($data['type'] === 'percentage' && Money::cents((string) $data['discount_value']) > 10000) {
            throw ValidationException::withMessages(['discount_value' => 'Percentage must be at most 100.']);
        }
        DB::transaction(function () use ($request, $voucher, $data, $shopId) {
            $row = $voucher->exists ? Voucher::whereKey($voucher->id)->lockForUpdate()->firstOrFail() : $voucher;
            if ($row->exists && $row->version !== (int) $data['version']) {
                throw ValidationException::withMessages(['version' => 'This voucher changed. Reload before saving.']);
            }
            $before = $row->exists ? $row->getAttributes() : [];
            $row->fill(['code' => $data['code'], 'name' => $data['name'], 'type' => $data['type'],
                'value' => $data['type'] === 'free_shipping' ? 0 : Money::cents((string) $data['discount_value']),
                'minimum_spend_cents' => Money::cents((string) $data['minimum_spend']),
                'maximum_discount_cents' => isset($data['maximum_discount']) ? Money::cents((string) $data['maximum_discount']) : null,
                'usage_limit' => $data['usage_limit'] ?? null, 'per_buyer_limit' => $data['per_buyer_limit'],
                'starts_at' => $data['starts_at'] ?? null, 'expires_at' => $data['expires_at'] ?? null,
                'is_active' => $data['is_active'], 'version' => $row->exists ? $row->version + 1 : 1]);
            if (! $row->exists) {
                $row->created_by = $request->user()->id;
                $row->shop_id = $shopId;
            }
            $row->save();
            app(AuditTrail::class)->record('voucher.saved', $row, ['before' => $before, 'after' => $row->getAttributes()]);
        }, 3);

        return redirect()->route($request->user()->role === UserRole::Seller ? 'seller.vouchers.index' : 'admin.vouchers.index')->with('status', 'Voucher saved. Existing orders retain their original discount.');
    }

    private function shop(): Shop
    {
        return Shop::where('user_id', auth()->id())->where('is_active', true)->firstOrFail();
    }

    private function owned(Voucher $voucher): void
    {
        abort_unless(auth()->user()->role === UserRole::Admin || (int) $voucher->shop_id === (int) $this->shop()->id, 404);
    }
}

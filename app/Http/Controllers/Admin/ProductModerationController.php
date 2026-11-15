<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Shop;
use App\Services\Security\AuditTrail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductModerationController extends Controller
{
    public function hide(Request $request, Product $product)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $this->change($request, $product, $data['reason'], true);

        return to_route('admin.products.index', ['visibility' => 'hidden'])->with('status', 'Product hidden from the storefront.');
    }

    public function restore(Request $request, Product $product)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $this->change($request, $product, $data['reason'], false);

        return to_route('admin.products.index')->with('status', 'Admin restriction removed. The listing retains its earlier publishing state.');
    }

    private function change(Request $request, Product $product, string $reason, bool $hide): void
    {
        abort_unless($request->user()->role === UserRole::Admin, 403);
        DB::transaction(function () use ($request, $product, $reason, $hide) {
            Shop::whereKey($product->shop_id)->lockForUpdate()->firstOrFail();
            $row = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
            if ($hide === (bool) $row->admin_hidden_at) {
                throw ValidationException::withMessages(['product' => $hide ? 'This product is already hidden.' : 'This product has no admin restriction to restore.']);
            }
            $before = $row->only(['is_active', 'admin_hidden_at', 'admin_hide_reason']);
            if ($hide) {
                $row->forceFill(['admin_restore_active' => $row->is_active, 'admin_hidden_at' => now(), 'admin_hidden_by' => $request->user()->id,
                    'admin_hide_reason' => $reason, 'is_active' => false])->save();
            } else {
                $row->forceFill(['is_active' => (bool) $row->admin_restore_active, 'admin_hidden_at' => null, 'admin_hidden_by' => null,
                    'admin_hide_reason' => null, 'admin_restore_active' => null])->save();
            }
            app(AuditTrail::class)->record($hide ? 'product.hidden' : 'product.restored', $row, ['reason' => $reason, 'before' => $before, 'after' => $row->only(['is_active', 'admin_hidden_at'])]);
        }, 3);
    }
}

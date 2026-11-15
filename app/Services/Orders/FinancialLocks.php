<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Models\Shop;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FinancialLocks
{
    public function order(int $id): Order
    {
        return Order::query()->lockForUpdate()->findOrFail($id);
    }

    public function orders(array $ids): Collection
    {
        return Order::query()->whereKey($ids)->orderBy('id')->lockForUpdate()->get();
    }

    public function shop(int $id): Shop
    {
        return Shop::query()->lockForUpdate()->findOrFail($id);
    }

    public function entries(int $shopId, array $entryIds): Collection
    {
        return DB::table('seller_payable_entries')->where('shop_id', $shopId)
            ->whereIn('id', $entryIds)->orderBy('id')->lockForUpdate()->get();
    }
}

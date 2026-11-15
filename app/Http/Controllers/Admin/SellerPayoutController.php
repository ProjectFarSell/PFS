<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SellerPayout;
use App\Models\Shop;
use App\Services\Orders\CreateSellerPayoutBatch;
use App\Services\Orders\ManageSellerPayout;
use App\Services\Orders\MarketplaceSettings;
use App\Services\Orders\SellerPayableBalance;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class SellerPayoutController extends Controller
{
    public function index(SellerPayableBalance $balances, MarketplaceSettings $settings)
    {
        $shops = Shop::with('owner:id,name')->orderBy('id')->paginate(20);
        $holdDays = $settings->current()->payout_hold_days;
        $breakdowns = $shops->getCollection()->mapWithKeys(fn ($shop) => [$shop->id => $balances->forShop($shop, $holdDays)]);

        return view('admin.payouts.index', compact('shops', 'breakdowns', 'holdDays'));
    }

    public function show(Shop $shop, SellerPayableBalance $balances, MarketplaceSettings $settings)
    {
        $holdDays = $settings->current()->payout_hold_days;
        $balance = $balances->forShop($shop, $holdDays);
        $payouts = SellerPayout::where('shop_id', $shop->id)->latest('id')->paginate(20);
        $isAdmin = true;

        return view('payouts.history', compact('shop', 'balance', 'payouts', 'holdDays', 'isAdmin'));
    }

    public function store(Request $request, Shop $shop, CreateSellerPayoutBatch $batches)
    {
        $result = $batches->create($shop, $request->user());

        return to_route('admin.payouts.show', $shop)->with('status', $result['created'] ? 'Unpaid payout batch created.' : 'This seller already has an unpaid payout batch.');
    }

    public function cancel(Request $request, SellerPayout $payout, ManageSellerPayout $service)
    {
        $data = $request->validate(['cancellation_reason' => ['required', 'string', 'max:1000']]);
        $service->cancel($payout, $request->user(), $data['cancellation_reason']);

        return to_route('admin.payouts.show', $payout->shop)->with('status', 'Payout batch cancelled. Its entries are available for a new batch.');
    }

    public function pay(Request $request, SellerPayout $payout, ManageSellerPayout $service)
    {
        $data = $request->validate(['payment_reference' => ['required', 'string', 'max:120'], 'paid_at' => ['required', 'date', 'before_or_equal:now']]);
        $service->pay($payout, $request->user(), $data['payment_reference'], Carbon::parse($data['paid_at']));

        return to_route('admin.payouts.show', $payout->shop)->with('status', 'Manual payout payment recorded. No money was transferred by FarSell.');
    }
}

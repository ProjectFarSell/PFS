<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\SellerPayout;
use App\Services\Orders\MarketplaceSettings;
use App\Services\Orders\SellerPayableBalance;
use Illuminate\Http\Request;

class PayoutController extends Controller
{
    public function __invoke(Request $request, SellerPayableBalance $balances, MarketplaceSettings $settings)
    {
        $shop = $request->user()->shop()->firstOrFail();
        $holdDays = $settings->current()->payout_hold_days;
        $balance = $balances->forShop($shop, $holdDays);
        $payouts = SellerPayout::where('shop_id', $shop->id)->latest('id')->paginate(20);
        $isAdmin = false;

        return view('payouts.history', compact('shop', 'balance', 'payouts', 'holdDays', 'isAdmin'));
    }
}

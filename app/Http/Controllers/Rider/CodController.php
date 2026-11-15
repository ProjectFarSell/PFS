<?php

namespace App\Http\Controllers\Rider;

use App\Http\Controllers\Controller;
use App\Models\RiderCodRemittance;
use App\Services\Orders\RiderCodBalance;
use Illuminate\Http\Request;

class CodController extends Controller
{
    public function __invoke(Request $request, RiderCodBalance $balances)
    {
        $rider = $request->user()->riderProfile()->firstOrFail();
        $balance = $balances->forRider($rider);
        $remittances = RiderCodRemittance::where('rider_profile_id', $rider->id)->latest('id')->paginate(20);
        $isAdmin = false;

        return view('remittances.history', compact('rider', 'balance', 'remittances', 'isAdmin'));
    }
}

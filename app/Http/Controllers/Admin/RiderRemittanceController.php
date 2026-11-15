<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RiderCodRemittance;
use App\Models\RiderProfile;
use App\Services\Orders\RecordRiderRemittance;
use App\Services\Orders\RiderCodBalance;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class RiderRemittanceController extends Controller
{
    public function index(RiderCodBalance $balances)
    {
        $riders = RiderProfile::with('user:id,name')->orderBy('id')->paginate(20);
        $breakdowns = $riders->getCollection()->mapWithKeys(fn ($rider) => [$rider->id => $balances->forRider($rider)]);

        return view('admin.remittances.index', compact('riders', 'breakdowns'));
    }

    public function show(RiderProfile $riderProfile, RiderCodBalance $balances)
    {
        $rider = $riderProfile->load('user:id,name');
        $balance = $balances->forRider($rider);
        $remittances = RiderCodRemittance::where('rider_profile_id', $rider->id)->latest('id')->paginate(20);
        $isAdmin = true;

        return view('remittances.history', compact('rider', 'balance', 'remittances', 'isAdmin'));
    }

    public function store(Request $request, RiderProfile $riderProfile, RecordRiderRemittance $service)
    {
        $data = $request->validate(['amount' => ['required', 'string', 'regex:/^\d{1,10}(?:\.\d{1,2})?$/D'],
            'received_at' => ['required', 'date', 'before_or_equal:now'], 'note' => ['required', 'string', 'max:1000']]);
        $service->record($riderProfile, $request->user(), Money::cents($data['amount']), Carbon::parse($data['received_at']), $data['note']);

        return to_route('admin.remittances.show', $riderProfile)->with('status', 'COD remittance recorded. Rider delivery earnings are unchanged.');
    }

    public function void(Request $request, RiderCodRemittance $remittance, RecordRiderRemittance $service)
    {
        $data = $request->validate(['void_reason' => ['required', 'string', 'max:1000']]);
        $service->void($remittance, $request->user(), $data['void_reason']);

        return to_route('admin.remittances.show', $remittance->rider_profile_id)->with('status', 'Remittance voided and retained in history.');
    }
}

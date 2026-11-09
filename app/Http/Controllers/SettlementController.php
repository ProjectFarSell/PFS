<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\ShipmentSettlement;
use Illuminate\Http\Request;

class SettlementController extends Controller
{
    public function __invoke(Request $request)
    {
        $isAdmin = $request->user()->role === UserRole::Admin;
        abort_unless($isAdmin || $request->user()->role === UserRole::Seller, 403);
        $data = $request->validate(['from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d', ...($request->filled('from') ? ['after_or_equal:from'] : [])]]);
        $rows = ShipmentSettlement::query()
            ->when(! $isAdmin, fn ($q) => $q->whereHas('fulfillment.shop', fn ($shop) => $shop->where('user_id', $request->user()->id)))
            ->when($data['from'] ?? null, fn ($q, $date) => $q->where('earned_at', '>=', $date.' 00:00:00'))
            ->when($data['to'] ?? null, fn ($q, $date) => $q->where('earned_at', '<=', $date.' 23:59:59'))
            ->with(['fulfillment.order:id,number', 'adjustments'])->latest('id')->paginate(20)->withQueryString();

        return view('settlements.index', compact('rows', 'isAdmin'));
    }
}

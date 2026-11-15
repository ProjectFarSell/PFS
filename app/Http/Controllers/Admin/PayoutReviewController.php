<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\ShipmentSettlement;
use Illuminate\View\View;

class PayoutReviewController extends Controller
{
    public function __invoke(): View
    {
        abort_unless(auth()->user()?->role === UserRole::Admin, 403);

        $settlements = ShipmentSettlement::query()
            ->whereHas('fulfillment', fn ($query) => $query->whereDoesntHave('events', fn ($events) => $events->where('status', 'delivered')))
            ->with(['fulfillment.order', 'fulfillment.shop'])
            ->latest('id')
            ->paginate(20);

        return view('admin.payouts.needs-review', compact('settlements'));
    }
}

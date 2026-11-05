<?php

namespace App\Http\Controllers\Rider;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Fulfillment;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HistoryController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user->role === UserRole::Rider && $user->riderProfile?->isApproved(), 403);

        $deliveries = Fulfillment::query()
            ->where('rider_id', $user->riderProfile->id)
            ->with(['order:id,number', 'events'])
            ->latest('updated_at')->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('rider.history', compact('deliveries'));
    }
}

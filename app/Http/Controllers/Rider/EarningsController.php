<?php

namespace App\Http\Controllers\Rider;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EarningsController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user->role === UserRole::Rider && $user->riderProfile?->isApproved(), 403);

        $profile = $user->riderProfile;
        $earnings = $profile->earnings()
            ->with('fulfillment.order:id,number')
            ->latest('earned_at')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('rider.earnings', [
            'earnings' => $earnings,
            'totalEarned' => $profile->earnings()->sum('amount'),
        ]);
    }
}

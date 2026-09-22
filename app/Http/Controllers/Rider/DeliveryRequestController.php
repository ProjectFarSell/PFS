<?php

namespace App\Http\Controllers\Rider;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\RiderProfile;
use App\Services\Orders\DeliveryRequestService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DeliveryRequestController extends Controller
{
    public function availability(Request $request, DeliveryRequestService $service)
    {
        $data = $request->validate(['is_available' => ['required', 'boolean']]);
        DB::transaction(function () use ($request, $data) {
            abort_unless($request->user()->role === UserRole::Rider, 403);
            $profile = RiderProfile::query()->where('user_id', $request->user()->id)->lockForUpdate()->first();
            abort_unless($profile?->isApproved(), 403);
            $profile->forceFill(['is_available' => (bool) $data['is_available']])->save();
        }, 3);
        // Dispatch after releasing the profile lock; dispatch locks order then profile.
        $service->dispatchPending();

        return back()->with('status', $data['is_available'] ? 'You are available for delivery requests.' : 'New requests paused. Your accepted deliveries remain active.');
    }

    public function index(Request $request, DeliveryRequestService $service)
    {
        $profile = $request->user()->riderProfile;
        abort_unless($request->user()->role === UserRole::Rider && $profile?->isApproved(), 403);
        $service->dispatchPending();
        $query = $service->requests($profile);
        $count = (clone $query)->count();
        $requests = $query->limit(30)->get();

        return response()->json([
            'count' => $count,
            'html' => view('rider.delivery-requests', compact('requests', 'count'))->render(),
        ])->header('Cache-Control', 'no-store');
    }
}

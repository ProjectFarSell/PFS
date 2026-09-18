<?php

namespace App\Http\Controllers\Rider;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $profile = $user->riderProfile()->first(['id', 'user_id', 'status', 'vehicle_type', 'city']);
        $canViewDeliveries = $profile !== null && $profile->isApproved()
            && in_array($user->role, [UserRole::Rider, UserRole::Admin], true);

        if (! $canViewDeliveries) {
            // Applicants may see their own status, never any assignment data.
            return view('rider.dashboard', compact('profile', 'canViewDeliveries'));
        }

        $activeStatuses = [OrderStatus::Assigned->value, OrderStatus::InTransit->value];
        $validated = $request->validate(['status' => ['nullable', Rule::in($activeStatuses)]]);
        $selectedStatus = $validated['status'] ?? null;

        // orders.rider_id references rider_profiles.id, not users.id.
        $counts = $profile->deliveries()
            ->whereIn('status', [...$activeStatuses, OrderStatus::Delivered->value])
            ->selectRaw('status, COUNT(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status');
        $stats = [
            'Assigned' => (int) ($counts[OrderStatus::Assigned->value] ?? 0),
            'In transit' => (int) ($counts[OrderStatus::InTransit->value] ?? 0),
            'Completed' => (int) ($counts[OrderStatus::Delivered->value] ?? 0),
        ];

        $deliveries = $profile->deliveries()
            ->select(['id', 'number', 'status', 'ship_to', 'created_at'])
            ->whereIn('status', $activeStatuses)
            ->when($selectedStatus, fn ($query) => $query->where('status', $selectedStatus))
            ->orderBy('created_at')->orderBy('id')->paginate(10)->withQueryString();

        return view('rider.dashboard', compact('profile', 'canViewDeliveries', 'stats', 'deliveries', 'selectedStatus'));
    }
}

<?php

namespace App\Http\Controllers\Rider;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Fulfillment;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $profile = $user->riderProfile()->first(['id', 'user_id', 'status', 'vehicle_type', 'city', 'is_available']);
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
            ->whereDoesntHave('fulfillments')
            ->whereIn('status', [...$activeStatuses, OrderStatus::Delivered->value])
            ->selectRaw('status, COUNT(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status');
        $stats = [
            'Assigned' => (int) ($counts[OrderStatus::Assigned->value] ?? 0),
            'In transit' => (int) ($counts[OrderStatus::InTransit->value] ?? 0),
            'Completed' => (int) ($counts[OrderStatus::Delivered->value] ?? 0),
        ];

        $shipmentCounts = Fulfillment::query()->where('rider_id', $profile->id)
            ->selectRaw('status, COUNT(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status');
        $stats['Assigned'] += (int) ($shipmentCounts['assigned'] ?? 0);
        $stats['In transit'] += (int) ($shipmentCounts['in_transit'] ?? 0);
        $stats['Completed'] += (int) ($shipmentCounts['delivered'] ?? 0) + (int) ($shipmentCounts['completed'] ?? 0);
        $shipments = Fulfillment::query()->where('rider_id', $profile->id)
            ->whereIn('status', $activeStatuses)
            ->when($selectedStatus, fn ($q) => $q->where('status', $selectedStatus))
            ->with('order:id,number')->oldest()->paginate(10, ['*'], 'shipments_page')->withQueryString();

        $deliveries = $profile->deliveries()
            ->whereDoesntHave('fulfillments')
            ->select(['id', 'number', 'status', 'ship_to', 'created_at'])
            ->whereIn('status', $activeStatuses)
            ->when($selectedStatus, fn ($query) => $query->where('status', $selectedStatus))
            ->orderBy('created_at')->orderBy('id')->paginate(10)->withQueryString();

        return view('rider.dashboard', compact('profile', 'canViewDeliveries', 'stats', 'deliveries', 'selectedStatus', 'shipments'));
    }
}

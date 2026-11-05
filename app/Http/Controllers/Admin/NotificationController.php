<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NotificationDelivery;
use App\Services\Notifications\OrderNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $data = $request->validate([
            'status' => ['nullable', Rule::in(NotificationDelivery::STATUSES)],
            'type' => ['nullable', Rule::in(array_keys(NotificationDelivery::TYPES))],
        ]);
        $deliveries = NotificationDelivery::query()->with('order:id,number')
            ->when($data['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($data['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
            ->latest('id')->paginate(20)->withQueryString();
        $counts = NotificationDelivery::query()->selectRaw('status, COUNT(*) AS aggregate')->groupBy('status')->pluck('aggregate', 'status');

        return view('admin.notifications.index', compact('deliveries', 'counts'));
    }

    public function retry(NotificationDelivery $delivery, OrderNotificationService $notifications): RedirectResponse
    {
        abort_unless(in_array($delivery->status, ['failed', 'pending'], true), 422, 'Only failed or pending messages can be retried manually.');
        $queued = $notifications->retry($delivery);

        return back()->with('status', $queued ? 'Notification queued for another delivery attempt.' : 'Notification remains pending. Check the queue connection or notification settings.');
    }
}

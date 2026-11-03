<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\BuyerNotification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    private function owned(Request $request)
    {
        return BuyerNotification::query()->where('user_id', $request->user()->id)
            ->whereHas('order', fn ($q) => $q->where('user_id', $request->user()->id));
    }

    public function index(Request $request)
    {
        $notifications = $this->owned($request)->with('order:id,number')->latest('id')->paginate(20);

        return view('account.notifications', compact('notifications'));
    }

    public function unread(Request $request)
    {
        return response()->json(['unread' => $this->owned($request)->whereNull('read_at')->count()]);
    }

    public function open(Request $request, BuyerNotification $notification)
    {
        $owned = $this->owned($request)->findOrFail($notification->id);
        $this->owned($request)->whereKey($owned->id)->whereNull('read_at')->update(['read_at' => now()]);

        return to_route('orders.show', $owned->order_id);
    }

    public function readAll(Request $request)
    {
        $this->owned($request)->whereNull('read_at')->update(['read_at' => now()]);

        return back()->with('status', 'All notifications marked as read.');
    }
}

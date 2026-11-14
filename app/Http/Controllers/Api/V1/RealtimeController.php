<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\MobileRequest;
use App\Models\Order;
use App\Services\Mobile\ApiFailure;
use App\Services\Mobile\PublicIds;

class RealtimeController extends Controller
{
    public function channel(MobileRequest $request)
    {
        $data = $request->validated();
        $user = $request->user();
        if ($user->isRider()) {
            abort_unless($user->riderProfile?->isApproved(), 403);
        }
        $channel = $data['channel_name'];
        $allowed = $channel === 'private-user.'.PublicIds::id($user);
        if (str_starts_with($channel, 'private-order.')) {
            $id = substr($channel, strlen('private-order.'));
            $allowed = Order::where('public_id', $id)->where(function ($query) use ($user) {
                if ($user->role->value === 'buyer') {
                    $query->where('user_id', $user->id);
                } else {
                    $query->whereHas('fulfillments', fn ($q) => $q->where('delivery_method', 'internal')->where('rider_id', $user->riderProfile->id)->whereIn('status', ['assigned', 'in_transit', 'delivery_failed', 'returning']));
                }
            })->exists();
        }
        abort_unless($allowed, 403);
        $key = config('broadcasting.connections.reverb.key');
        $secret = config('broadcasting.connections.reverb.secret');
        if (! config('mobile.realtime_enabled') || ! $key || ! $secret) {
            throw new ApiFailure('service_unavailable', 'Real-time updates are not configured yet.', 503);
        }

        return response()->json(['auth' => $key.':'.hash_hmac('sha256', $data['socket_id'].':'.$channel, $secret)])->header('Cache-Control', 'private, no-store');
    }
}

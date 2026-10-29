<?php

namespace App\Http\Controllers;

use App\Enums\FulfillmentStatus;
use App\Enums\UserRole;
use App\Models\Fulfillment;
use App\Models\RiderDeliveryRating;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RiderDeliveryRatingController extends Controller
{
    public function store(Request $request, Fulfillment $fulfillment): RedirectResponse
    {
        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'feedback' => ['nullable', 'string', 'max:1000'],
        ]);

        abort_unless($request->user()->role === UserRole::Buyer, 403);

        $created = DB::transaction(function () use ($request, $fulfillment, $data) {
            $part = Fulfillment::query()->lockForUpdate()->findOrFail($fulfillment->id);
            abort_unless($part->order()->where('user_id', $request->user()->id)->exists(), 404);
            abort_unless($part->status === FulfillmentStatus::Completed && $part->rider_id !== null, 422);

            if (RiderDeliveryRating::query()->where('fulfillment_id', $part->id)->exists()) {
                return false;
            }

            RiderDeliveryRating::query()->create([
                'fulfillment_id' => $part->id,
                'buyer_id' => $request->user()->id,
                'rider_profile_id' => $part->rider_id,
                'rating' => $data['rating'],
                'feedback' => $data['feedback'] ?? null,
            ]);

            return true;
        }, 3);

        return back()->with('status', $created ? 'Thanks for rating your delivery.' : 'You have already rated this delivery.');
    }
}

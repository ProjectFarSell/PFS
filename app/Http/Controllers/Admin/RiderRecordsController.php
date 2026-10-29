<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FulfillmentEvent;
use App\Models\RiderDeliveryRating;
use App\Models\RiderEarning;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RiderRecordsController extends Controller
{
    public function index(Request $request): View
    {
        $data = $request->validate(['section' => ['nullable', Rule::in(['history', 'earnings', 'feedback'])]]);
        $section = $data['section'] ?? 'history';

        $records = match ($section) {
            'earnings' => RiderEarning::query()
                ->with(['fulfillment.order:id,number', 'riderProfile.user:id,name'])
                ->latest('earned_at')->latest('id')->paginate(20)->withQueryString(),
            'feedback' => RiderDeliveryRating::query()
                ->with(['fulfillment.order:id,number', 'buyer:id,name', 'riderProfile.user:id,name'])
                ->latest('id')->paginate(20)->withQueryString(),
            default => FulfillmentEvent::query()
                ->with(['fulfillment.order:id,number', 'fulfillment.rider.user:id,name', 'actor:id,name'])
                ->latest('id')->paginate(20)->withQueryString(),
        };

        return view('admin.rider-records.index', compact('records', 'section'));
    }
}

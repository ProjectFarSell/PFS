<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductReview;
use App\Models\ShipmentFeedback;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ReviewController extends Controller
{
    public function index(Request $request): View
    {
        $data = $request->validate(['type' => ['nullable', Rule::in(['products', 'service'])]]);
        $type = $data['type'] ?? 'products';
        $records = $type === 'products'
            ? ProductReview::query()->with(['buyer:id,name', 'orderItem.order:id,number', 'media'])
                ->latest('id')->paginate(15)->withQueryString()
            : ShipmentFeedback::query()->with(['buyer:id,name', 'fulfillment.order:id,number', 'fulfillment.riderRating'])
                ->latest('id')->paginate(20)->withQueryString();

        return view('admin.reviews.index', compact('records', 'type'));
    }
}

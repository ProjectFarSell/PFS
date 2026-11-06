<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\OrderItem;
use App\Models\ReplacementRequest;
use App\Services\Orders\ReplacementService;
use App\Support\Cart;
use Illuminate\Http\Request;

class ReplacementController extends Controller
{
    public function index(Request $request)
    {
        $role = $request->user()->role;
        $cases = ReplacementRequest::query()
            ->when($role === UserRole::Buyer, fn ($q) => $q->whereHas('item.order', fn ($q) => $q->where('user_id', $request->user()->id)))
            ->when($role === UserRole::Seller, fn ($q) => $q->whereHas('item.fulfillment.shop', fn ($q) => $q->where('user_id', $request->user()->id)))
            ->with(['item.order', 'variant', 'newOrder'])->latest('id')->paginate(15);

        return view('replacements.index', compact('cases', 'role'));
    }

    public function create(Request $request, OrderItem $item, ReplacementService $service)
    {
        abort_unless($item->order->user_id === $request->user()->id, 403);
        abort_unless($service->eligible($item) && ! $item->replacementRequest()->exists(), 422);
        $item->load('product.variants');

        return view('replacements.create', compact('item'));
    }

    public function store(Request $request, OrderItem $item, ReplacementService $service)
    {
        $data = $request->validate(['quantity' => ['required', 'integer', 'min:1', 'max:99'], 'variant_id' => ['nullable', 'integer'], 'note' => ['required', 'string', 'min:10', 'max:1000']]);
        $service->submit($item, $request->user(), $data);

        return to_route('replacements.index')->with('status', 'Replacement request submitted for review.');
    }

    public function review(Request $request, ReplacementRequest $replacement, ReplacementService $service)
    {
        $data = $request->validate(['decision' => ['required', 'in:approved,rejected'], 'review_note' => ['required', 'string', 'min:10', 'max:1000']]);
        $service->review($replacement, $request->user(), $data);

        return back()->with('status', 'Replacement request reviewed.');
    }

    public function checkout(Request $request, ReplacementRequest $replacement, ReplacementService $service)
    {
        abort_unless($replacement->item->order->user_id === $request->user()->id, 403);
        abort_unless($replacement->status === 'approved' && $service->eligible($replacement->item), 422);
        Cart::startBuyNow($replacement->item->product_id, $replacement->quantity, $replacement->product_variant_id);
        $request->session()->put(ReplacementService::SESSION_KEY, $replacement->id);

        return to_route('checkout.create');
    }
}

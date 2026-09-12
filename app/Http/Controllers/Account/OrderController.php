<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Order::class);

        $orders = $request->user()->orders()
            ->withSum('items', 'qty')
            ->latest()
            ->orderByDesc('id')
            ->paginate(10);

        return view('orders.index', compact('orders'));
    }
}

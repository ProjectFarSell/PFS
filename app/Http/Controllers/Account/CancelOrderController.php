<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Orders\CancelOrder;
use Illuminate\Http\Request;

class CancelOrderController extends Controller
{
    public function __invoke(Request $request, Order $order, CancelOrder $service)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:1000']]);
        $service->handle($order, $request->user(), $data['reason']);

        return to_route('orders.show', $order)->with('status', 'Order cancelled. Reserved stock has been released.');
    }
}

<?php

namespace App\Http\Controllers\Checkout;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Support\Cart;
use App\Support\GuestSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function create(): View|RedirectResponse
    {
        if (Cart::count() === 0) {
            return redirect()->route('cart.index')->with('status', 'Your cart is empty.');
        }

        GuestSession::start();

        return view('checkout.create', [
            'lines' => Cart::hydrated(),
            'subtotal' => Cart::subtotal(),
            'shipping' => 49,
            'guest' => auth()->guest(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (Cart::count() === 0) {
            return redirect()->route('cart.index');
        }

        $data = $request->validate([
            'guest_name' => [auth()->check() ? 'nullable' : 'required', 'string', 'max:120'],
            'guest_email' => [auth()->check() ? 'nullable' : 'required', 'email', 'max:180'],
            'ship_to' => ['required', 'string', 'max:500'],
            'phone' => ['required', 'string', 'max:40'],
            'payment_method' => ['required', 'in:cod,gateway_stub'],
        ]);

        $subtotal = Cart::subtotal();
        $shipping = 49.00;

        $order = Order::query()->create([
            'user_id' => auth()->id(),
            'number' => 'FS'.now()->format('ymd').Str::upper(Str::random(6)),
            'status' => OrderStatus::Paid,
            'payment_method' => PaymentMethod::from($data['payment_method']),
            'guest_email' => $data['guest_email'] ?? auth()->user()?->email,
            'guest_name' => $data['guest_name'] ?? auth()->user()?->name,
            'ship_to' => $data['ship_to'].' · '.$data['phone'],
            'subtotal' => $subtotal,
            'shipping_fee' => $shipping,
            'total' => $subtotal + $shipping,
        ]);

        foreach (Cart::hydrated() as $line) {
            $order->items()->create([
                'product_id' => $line->product->id,
                'name' => $line->product->name,
                'qty' => $line->qty,
                'unit_price' => $line->product->price,
                'line_total' => $line->line_total,
            ]);
        }

        Cart::clear();

        if (auth()->guest()) {
            $ids = Session::get('farsell.guest_orders', []);
            $ids[] = $order->id;
            Session::put('farsell.guest_orders', $ids);
        }

        return redirect()->route('orders.show', $order)->with('status', 'Order placed.');
    }

    public function show(Order $order): View
    {
        $this->authorizeView($order);
        $order->load('items');

        return view('orders.show', compact('order'));
    }

    private function authorizeView(Order $order): void
    {
        if (auth()->check() && $order->user_id === auth()->id()) {
            return;
        }

        if (auth()->guest() && in_array($order->id, Session::get('farsell.guest_orders', []), true)) {
            return;
        }

        abort_unless(auth()->user()?->role?->value === 'admin', 403);
    }
}

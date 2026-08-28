<?php

namespace App\Http\Controllers\Checkout;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Support\Cart;
use App\Support\GuestSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
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
        $paymentMethod = PaymentMethod::from($data['payment_method']);
        $lines = Cart::hydrated();

        // COD isn't actually paid yet at order placement — only gateway payments
        // that clear immediately should be marked Paid here. COD stays
        // PendingPayment until payment is collected on delivery.
        $initialStatus = $paymentMethod === PaymentMethod::Cod
            ? OrderStatus::PendingPayment
            : OrderStatus::Paid;

        $order = DB::transaction(function () use ($lines, $subtotal, $shipping, $paymentMethod, $initialStatus, $data) {
            // Lock and re-check stock inside the transaction so two concurrent
            // checkouts can't both oversell the last units of a product.
            foreach ($lines as $line) {
                $product = Product::query()->lockForUpdate()->find($line->product->id);

                if (! $product || $product->stock < $line->qty) {
                    throw ValidationException::withMessages([
                        'cart' => $line->product->name.' no longer has enough stock. Please update your cart.',
                    ]);
                }
            }

            $order = Order::query()->create([
                'user_id' => auth()->id(),
                'number' => 'FS'.now()->format('ymd').Str::upper(Str::random(6)),
                'status' => $initialStatus,
                'payment_method' => $paymentMethod,
                'guest_email' => $data['guest_email'] ?? auth()->user()?->email,
                'guest_name' => $data['guest_name'] ?? auth()->user()?->name,
                'ship_to' => $data['ship_to'].' · '.$data['phone'],
                'subtotal' => $subtotal,
                'shipping_fee' => $shipping,
                'total' => $subtotal + $shipping,
            ]);

            foreach ($lines as $line) {
                $order->items()->create([
                    'product_id' => $line->product->id,
                    'name' => $line->product->name,
                    'qty' => $line->qty,
                    'unit_price' => $line->product->price,
                    'line_total' => $line->line_total,
                ]);

                $line->product->decrement('stock', $line->qty);
            }

            return $order;
        });

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

<?php

namespace App\Http\Controllers\Checkout;

use App\Contracts\DeliveryFeeService;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Orders\StockReservationService;
use App\Support\Cart;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly DeliveryFeeService $deliveryFees,
        private readonly StockReservationService $stock,
    ) {}

    public function create(Request $request): View|RedirectResponse
    {
        if (Cart::count() === 0) {
            return redirect()->route('cart.index')->with('status', 'Your cart is empty.');
        }

        $addresses = $request->user()->addresses()
            ->with(['psgcRegion', 'psgcProvince', 'psgcCityMunicipality', 'psgcBarangay'])
            ->orderByDesc('is_default')
            ->latest()
            ->get();

        if ($addresses->isEmpty()) {
            return redirect()->route('account.addresses.create')
                ->with('status', 'Add a delivery address before checking out.');
        }

        $lines = Cart::hydrated();
        $shipping = $this->deliveryFees->calculate($addresses->first(), $lines);

        return view('checkout.create', [
            'addresses' => $addresses,
            'lines' => $lines,
            'subtotal' => (float) $lines->sum('line_total'),
            'shipping' => $shipping,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (Cart::count() === 0) {
            return redirect()->route('cart.index');
        }

        $data = $request->validate([
            'address_id' => [
                'required',
                'integer',
                Rule::exists('addresses', 'id')->where('user_id', $request->user()->id),
            ],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
        ]);

        $address = $request->user()->addresses()
            ->with(['psgcRegion', 'psgcProvince', 'psgcCityMunicipality', 'psgcBarangay'])
            ->findOrFail($data['address_id']);
        $paymentMethod = PaymentMethod::from($data['payment_method']);
        $initialStatus = $paymentMethod === PaymentMethod::Cod
            ? OrderStatus::PendingPayment
            : OrderStatus::Paid;
        $lines = Cart::hydrated();

        $order = DB::transaction(function () use ($lines, $address, $paymentMethod, $initialStatus): Order {
            $lockedLines = $this->stock->lockAndPrepare($lines);
            $subtotal = (float) $lockedLines->sum('line_total');
            $shipping = $this->deliveryFees->calculate($address, $lockedLines);

            $order = Order::query()->create([
                'user_id' => auth()->id(),
                'address_id' => $address->id,
                'number' => 'FS'.now()->format('ymd').Str::upper(Str::random(6)),
                'status' => $initialStatus,
                'payment_method' => $paymentMethod,
                'guest_email' => auth()->user()->email,
                'guest_name' => auth()->user()->name,
                'ship_to' => $address->formatted().($address->phone ? ' · '.$address->phone : ''),
                'subtotal' => $subtotal,
                'shipping_fee' => $shipping,
                'total' => $subtotal + $shipping,
                'stock_reserved_at' => now(),
            ]);

            foreach ($lockedLines as $line) {
                $order->items()->create([
                    'product_id' => $line->product->id,
                    'name' => $line->product->name,
                    'qty' => $line->qty,
                    'unit_price' => $line->unit_price,
                    'line_total' => $line->line_total,
                ]);
            }

            $this->stock->reserve($lockedLines);

            return $order;
        });

        Cart::clear();

        return redirect()->route('orders.show', $order)->with('status', 'Order placed.');
    }

    public function show(Order $order): View
    {
        $this->authorize('view', $order);
        $order->load('items');

        return view('orders.show', compact('order'));
    }
}

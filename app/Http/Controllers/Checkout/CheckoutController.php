<?php

namespace App\Http\Controllers\Checkout;

use App\Contracts\DeliveryFeeService;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Invoices\InvoiceGeneratorApi;
use App\Services\Notifications\OrderNotificationService;
use App\Services\Orders\CheckoutAvailability;
use App\Services\Orders\FulfillmentService;
use App\Services\Orders\ReplacementService;
use App\Services\Orders\StockReservationService;
use App\Support\Cart;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly DeliveryFeeService $deliveryFees,
        private readonly StockReservationService $stock,
    ) {}

    public function create(Request $request): View|RedirectResponse
    {
        app(CheckoutAvailability::class)->ensureOpen();
        if (Cart::checkoutCount() === 0) {
            return redirect()->route('cart.index')->with('status', 'Your cart is empty.');
        }

        $addresses = $request->user()->addresses()
            ->with(['psgcRegion', 'psgcProvince', 'psgcCityMunicipality', 'psgcBarangay'])
            ->orderByDesc('is_default')
            ->latest()
            ->get();

        if ($addresses->isEmpty()) {
            $request->session()->put('checkout.needs_address', true);

            return redirect()->route('account.addresses.create')
                ->with('status', 'Add a delivery address before checking out.');
        }

        $request->session()->forget('checkout.needs_address');

        $lines = Cart::forCheckout();
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
        app(CheckoutAvailability::class)->ensureOpen();
        if (Cart::checkoutCount() === 0) {
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
        if ($paymentMethod === PaymentMethod::GatewayStub && ! config('release.allow_demo_payments')) {
            throw ValidationException::withMessages(['payment_method' => 'This payment method is unavailable. Please use cash on delivery.']);
        }
        $initialStatus = $paymentMethod === PaymentMethod::Cod
            ? OrderStatus::PendingPayment
            : OrderStatus::Paid;
        $lines = Cart::forCheckout();

        $order = DB::transaction(function () use ($lines, $address, $paymentMethod, $initialStatus): Order {
            $replacement = session()->has(ReplacementService::SESSION_KEY)
                ? app(ReplacementService::class)->forCheckout((int) session(ReplacementService::SESSION_KEY), auth()->user(), $lines)
                : null;
            if ($replacement && $paymentMethod !== PaymentMethod::Cod) {
                throw ValidationException::withMessages(['payment_method' => 'Replacement orders use cash on delivery.']);
            }
            $lockedLines = $this->stock->lockAndPrepare($lines);
            app(CheckoutAvailability::class)->ensureServiceable($address, $lockedLines);
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
                    'product_variant_id' => $line->variant?->id,
                    'name' => $line->product->name,
                    'product_sku' => $line->variant?->sku ?? $line->product->sku,
                    'variant_options' => $line->variant_options,
                    'qty' => $line->qty,
                    'unit_price' => $line->unit_price,
                    'line_total' => $line->line_total,
                ]);
            }

            $this->stock->reserve($lockedLines);
            app(FulfillmentService::class)->createForOrder($order);
            app(OrderNotificationService::class)->orderPlaced($order);
            if ($replacement) {
                $replacement->forceFill(['status' => 'ordered', 'new_order_id' => $order->id])->save();
            }

            return $order;
        });

        Cart::clearCheckout();

        return redirect()->route('orders.show', $order)->with('status', 'Order placed.');
    }

    public function show(Order $order): View
    {
        $this->authorize('view', $order);
        $order->load(['replacementSource.item.order', 'items.returnRequest', 'items.replacementRequest', 'fulfillments.items.review', 'fulfillments.courierBooking', 'fulfillments.attempts', 'fulfillments.events', 'fulfillments.rider.user', 'fulfillments.riderRating', 'fulfillments.feedback']);

        return view('orders.show', compact('order'));
    }

    public function print(Order $order, string $document, InvoiceGeneratorApi $invoiceGenerator): View|Response
    {
        abort_unless(in_array($document, ['invoice', 'packing-slip'], true), 404);
        $this->authorize('view', $order);
        $order->load('items');

        if ($document === 'invoice' && $order->billableItems()->isNotEmpty() && filled(config('services.invoice_generator.api_key'))) {
            try {
                $pdf = $invoiceGenerator->generate($order);

                return response($pdf, 200, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'inline; filename="invoice-'.$order->number.'.pdf"',
                    'Cache-Control' => 'private, no-store',
                ]);
            } catch (\Throwable $exception) {
                Log::warning('Invoice Generator API failed; using the printable invoice fallback.', [
                    'order_id' => $order->id,
                    'exception' => $exception::class,
                ]);

                return view('orders.print', [
                    'order' => $order,
                    'document' => $document,
                    'invoiceGenerationFailed' => true,
                ]);
            }
        }

        return view('orders.print', compact('order', 'document'));
    }
}

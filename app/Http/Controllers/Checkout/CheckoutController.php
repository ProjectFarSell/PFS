<?php

namespace App\Http\Controllers\Checkout;

use App\Contracts\DeliveryFeeService;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Invoices\InvoiceGeneratorApi;
use App\Services\Orders\CheckoutAvailability;
use App\Services\Orders\PlaceOrder;
use App\Services\Orders\ReplacementService;
use App\Services\Orders\StockReservationService;
use App\Services\Orders\VoucherService;
use App\Support\Cart;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
        $voucher = null;
        $voucherError = null;
        try {
            $voucher = app(VoucherService::class)->quote($request->session()->get('checkout.voucher_code'), $request->user(), $lines, Money::cents((string) $shipping));
        } catch (ValidationException $exception) {
            $voucherError = $exception->errors()['voucher_code'][0];
            $request->session()->forget('checkout.voucher_code');
        }

        return view('checkout.create', [
            'addresses' => $addresses,
            'lines' => $lines,
            'subtotal' => (float) $lines->sum('line_total'),
            'shipping' => $shipping,
            'voucher' => $voucher,
            'voucherError' => $voucherError,
            'totalCents' => $lines->sum(fn ($line) => Money::cents((string) $line->unit_price) * (int) $line->qty) + Money::cents((string) $shipping) - ($voucher['discount_cents'] ?? 0) - ($voucher['shipping_discount_cents'] ?? 0),
        ]);
    }

    public function voucher(Request $request): RedirectResponse
    {
        $data = $request->validate(['voucher_code' => ['nullable', 'string', 'max:32']]);
        $code = strtoupper(trim($data['voucher_code'] ?? ''));
        if ($code !== '') {
            $address = $request->user()->addresses()->orderByDesc('is_default')->latest()->firstOrFail();
            $lines = Cart::forCheckout();
            app(VoucherService::class)->quote($code, $request->user(), $lines, Money::cents((string) $this->deliveryFees->calculate($address, $lines)));
        }
        $request->session()->put('checkout.voucher_code', $code);

        return redirect()->route('checkout.create')->with('status', $code === '' ? 'Voucher removed.' : 'Voucher applied. One voucher per checkout.');
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
            'voucher_code' => ['nullable', 'string', 'max:32'],
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

        $order = DB::transaction(function () use ($request, $address, $lines, $paymentMethod, $data) {
            $replacement = session()->has(ReplacementService::SESSION_KEY)
                ? app(ReplacementService::class)->forCheckout((int) session(ReplacementService::SESSION_KEY), auth()->user(), $lines)
                : null;

            return app(PlaceOrder::class)->handle($request->user(), $address, $lines, $paymentMethod, $replacement, voucherCode: $data['voucher_code'] ?? null);
        }, 3);

        Cart::clearCheckout();
        $request->session()->forget('checkout.voucher_code');

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
        abort_unless($document === 'invoice', 404);
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

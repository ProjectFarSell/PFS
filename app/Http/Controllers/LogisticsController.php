<?php

namespace App\Http\Controllers;

use App\Enums\DeliveryMethod;
use App\Enums\FulfillmentStatus;
use App\Models\Fulfillment;
use App\Services\Logistics\CourierLogistics;
use App\Services\Logistics\LalamoveClient;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LogisticsController extends Controller
{
    public function index(Request $request, LalamoveClient $client)
    {
        $data = $request->validate(['method' => ['nullable', Rule::enum(DeliveryMethod::class)], 'status' => ['nullable', Rule::enum(FulfillmentStatus::class)], 'q' => ['nullable', 'string', 'max:60']]);
        $shipments = Fulfillment::query()->with(['order:id,number,payment_method,ship_to,guest_name', 'items', 'rider.user', 'courierBooking'])
            ->when($data['method'] ?? null, fn ($q, $method) => $q->where('delivery_method', $method))
            ->when($data['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($data['q'] ?? null, fn ($q, $search) => $q->whereHas('order', fn ($q) => $q->where('number', 'like', '%'.$search.'%')))
            ->latest('id')->paginate(10)->withQueryString();

        return view('admin.logistics.index', ['shipments' => $shipments, 'apiConfigured' => $client->configured(), 'apiEnvironment' => config('logistics.lalamove.environment')]);
    }

    public function update(Request $request, Fulfillment $fulfillment, CourierLogistics $service)
    {
        $action = $request->validate(['action' => ['required', 'in:select,manual,quote,book,sync,cancel_record']])['action'];
        try {
            match ($action) {
                'select' => $service->select($fulfillment, $request->user(), DeliveryMethod::from($request->validate(['delivery_method' => ['required', Rule::enum(DeliveryMethod::class)]])['delivery_method'])),
                'manual' => $service->manual($fulfillment, $request->user(), $request->validate([
                    'reference' => ['required', 'string', 'min:3', 'max:100'], 'courier_cost' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'],
                    'tracking_url' => ['nullable', 'url:https', 'max:2000'], 'verified' => ['accepted'], 'cod_supported' => ['nullable', 'boolean'],
                    'note' => ['required', 'string', 'min:10', 'max:1000'],
                ])),
                'quote' => $service->quote($fulfillment, $request->user(), $request->validate([
                    'service_type' => ['required', 'string', 'regex:/^[A-Z0-9_]{2,50}$/D'],
                    'pickup_address' => ['required', 'string', 'min:5', 'max:1000'], 'dropoff_address' => ['required', 'string', 'min:5', 'max:1000'],
                    'pickup_lat' => ['required', 'numeric', 'between:-90,90'], 'pickup_lng' => ['required', 'numeric', 'between:-180,180'],
                    'dropoff_lat' => ['required', 'numeric', 'between:-90,90'], 'dropoff_lng' => ['required', 'numeric', 'between:-180,180'],
                    'sender_name' => ['required', 'string', 'max:120'], 'recipient_name' => ['required', 'string', 'max:120'],
                    'sender_phone' => ['required', 'regex:/^\+[1-9]\d{7,14}$/D'], 'recipient_phone' => ['required', 'regex:/^\+[1-9]\d{7,14}$/D'],
                ])),
                'book' => $this->book($request, $fulfillment, $service),
                'sync' => $service->sync($fulfillment, $request->user()),
                'cancel_record' => $service->cancelRecord($fulfillment, $request->user(), $request->validate([
                    'verified_cancelled' => ['accepted'], 'note' => ['required', 'string', 'min:10', 'max:1000'],
                ])['note']),
            };
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['logistics' => 'Courier reference is already linked. Check the existing booking.']);
        }

        return back()->with('status', $action === 'cancel_record' ? 'Courier cancellation verification recorded. The purchase order is unchanged.' : 'Logistics record updated.');
    }

    private function book(Request $request, Fulfillment $part, CourierLogistics $service): void
    {
        $request->validate(['confirmed_booking' => ['accepted']]);
        $service->book($part, $request->user());
    }
}

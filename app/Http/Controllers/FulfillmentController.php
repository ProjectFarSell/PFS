<?php

namespace App\Http\Controllers;

use App\Enums\FulfillmentStatus;
use App\Enums\UserRole;
use App\Models\Fulfillment;
use App\Models\RiderProfile;
use App\Services\Orders\FulfillmentService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FulfillmentController extends Controller
{
    public function index(Request $request)
    {
        $role = $request->user()->role;
        abort_unless(in_array($role, [UserRole::Seller, UserRole::Admin, UserRole::Rider], true), 403);
        $query = Fulfillment::query()->with(['items', 'shop', 'order:id,number,payment_method,ship_to,guest_name', 'rider.user']);
        if ($role === UserRole::Seller) {
            $query->whereHas('shop', fn ($q) => $q->where('user_id', $request->user()->id));
        } elseif ($role === UserRole::Rider) {
            $profile = $request->user()->riderProfile;
            abort_unless($profile?->isApproved(), 403);
            $query->where('rider_id', $profile->id)->whereIn('status', ['assigned', 'in_transit']);
        }
        $data = $request->validate(['status' => ['nullable', Rule::enum(FulfillmentStatus::class)]]);
        $query->when($data['status'] ?? null, fn ($q, $status) => $q->where('status', $status));
        $fulfillments = $query->latest('id')->paginate(15)->withQueryString();
        $riders = $role === UserRole::Admin
            ? RiderProfile::query()->where('status', 'approved')->whereHas('user', fn ($q) => $q->where('role', 'rider'))->with('user')->get()
            : collect();

        return view('fulfillments.index', compact('fulfillments', 'role', 'riders'));
    }

    public function update(Request $request, Fulfillment $fulfillment, FulfillmentService $service)
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['accept', 'reject', 'ready', 'request_rider', 'assign', 'claim', 'decline', 'pickup', 'deliver', 'complete'])],
            'reason' => ['required_if:action,reject', 'nullable', 'string', 'max:1000'],
            'rider_id' => ['required_if:action,assign', 'nullable', 'integer'],
            'delivery_note' => ['required_if:action,deliver', 'nullable', 'string', 'max:1000'],
            'cod_collected' => ['nullable', 'boolean'],
            'pickup_address' => ['required_if:action,ready,request_rider', 'nullable', 'string', 'max:1000'],
            'pickup_contact' => ['required_if:action,ready,request_rider', 'nullable', 'string', 'max:255'],
        ]);
        $service->act($fulfillment, $request->user(), $data['action'], $data);

        return back()->with('status', 'Shipment updated.');
    }
}

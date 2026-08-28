<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Address;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AddressController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Address::class);

        $addresses = $request->user()->addresses()->latest()->get();

        return view('account.addresses.index', compact('addresses'));
    }

    public function create(): View
    {
        $this->authorize('create', Address::class);

        return view('account.addresses.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Address::class);

        $data = $request->validate([
            'label' => ['nullable', 'string', 'max:60'],
            'line1' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:120'],
            'region' => ['nullable', 'string', 'max:120'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'phone' => ['nullable', 'string', 'max:40'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        $address = $request->user()->addresses()->create([
            ...$data,
            'label' => $data['label'] ?? 'Home',
            'is_default' => $request->boolean('is_default'),
        ]);

        if ($address->is_default) {
            $request->user()->addresses()
                ->where('id', '!=', $address->id)
                ->update(['is_default' => false]);
        }

        return redirect()->route('account.addresses.index')->with('status', 'Address saved.');
    }

    public function edit(Address $address): View
    {
        $this->authorize('update', $address);

        return view('account.addresses.edit', compact('address'));
    }

    public function update(Request $request, Address $address): RedirectResponse
    {
        $this->authorize('update', $address);

        $data = $request->validate([
            'label' => ['nullable', 'string', 'max:60'],
            'line1' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:120'],
            'region' => ['nullable', 'string', 'max:120'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'phone' => ['nullable', 'string', 'max:40'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        $address->update([
            ...$data,
            'label' => $data['label'] ?? 'Home',
            'is_default' => $request->boolean('is_default'),
        ]);

        if ($address->is_default) {
            $address->user->addresses()
                ->where('id', '!=', $address->id)
                ->update(['is_default' => false]);
        }

        return redirect()->route('account.addresses.index')->with('status', 'Address updated.');
    }

    public function destroy(Address $address): RedirectResponse
    {
        $this->authorize('delete', $address);

        $address->delete();

        return redirect()->route('account.addresses.index')->with('status', 'Address removed.');
    }
}

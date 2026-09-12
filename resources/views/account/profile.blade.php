@extends('layouts.app')

@section('title', 'My Profile · FarSell')

@section('content')
    <div class="max-w-lg mx-auto">
        <h1 class="text-lg font-semibold mb-4">My Profile</h1>

        <section aria-labelledby="account-details" class="rounded-xl border border-stone-200 bg-white p-4">
            <h2 id="account-details" class="font-semibold mb-3">Account details</h2>
            <dl class="space-y-3 text-sm">
                <div>
                    <dt class="text-stone-500">Name</dt>
                    <dd class="break-words">{{ $user->name }}</dd>
                </div>
                <div>
                    <dt class="text-stone-500">Email</dt>
                    <dd class="break-words">{{ $user->email }}</dd>
                </div>
                <div>
                    <dt class="text-stone-500">Phone</dt>
                    <dd>{{ $user->phone ?: 'Not provided' }}</dd>
                </div>
            </dl>
        </section>

        <div class="mt-4 space-y-3">
            <a href="{{ route('orders.index') }}" class="block rounded-xl border border-stone-200 bg-white p-4 hover:border-orange-500">
                <span class="font-semibold text-orange-600">My Orders</span>
                <span class="block mt-1 text-sm text-stone-500">View your purchases, order status, and details.</span>
            </a>
            <a href="{{ route('account.addresses.index') }}" class="block rounded-xl border border-stone-200 bg-white p-4 hover:border-orange-500">
                <span class="font-semibold text-orange-600">My Addresses</span>
                <span class="block mt-1 text-sm text-stone-500">Manage your saved delivery addresses.</span>
            </a>
        </div>
    </div>
@endsection

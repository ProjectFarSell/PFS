@extends('layouts.app')

@section('title', ($isSellerProfile ? 'Seller Profile' : ($isRiderProfile ? 'Rider Profile' : 'My Profile')).' · FarSell')

@section('content')
    <div class="max-w-lg mx-auto">
        <h1 class="text-lg font-semibold mb-4">{{ $isSellerProfile ? 'Seller Profile' : ($isRiderProfile ? 'Rider Profile' : 'My Profile') }}</h1>

        <section aria-labelledby="account-details" class="rounded-xl border border-surface-border bg-surface p-4">
            <h2 id="account-details" class="font-semibold mb-3">Account details</h2>
            <dl class="space-y-3 text-sm">
                <div>
                    <dt class="text-text-muted">Name</dt>
                    <dd class="break-words">{{ $user->name }}</dd>
                </div>
                <div>
                    <dt class="text-text-muted">Email</dt>
                    <dd class="break-words">{{ $user->email }}</dd>
                </div>
                <div>
                    <dt class="text-text-muted">Phone</dt>
                    <dd>{{ $user->phone ?: 'Not provided' }}</dd>
                </div>
            </dl>
            <a href="{{ route('account.profile.edit') }}" class="btn-accent mt-4">Edit profile &amp; security</a>
        </section>

        <div class="mt-4 space-y-3">
            @if($user->role === \App\Enums\UserRole::Buyer)
                <a href="{{ route('seller.apply') }}" class="block fs-card p-4 hover:border-accent">
                    <span class="font-semibold text-accent">{{ $user->sellerApplication ? 'Seller Application' : 'Open a shop' }}</span>
                    <span class="mt-1 block text-sm text-text-muted">{{ $user->sellerApplication ? 'Status: '.ucfirst($user->sellerApplication->status).'. View or update your application.' : 'Apply for admin approval to list and sell your products.' }}</span>
                </a>
            @endif
            @if($user->isRider())
                <a href="{{ route('rider.dashboard') }}" class="block fs-card p-4 hover:border-accent">
                    <span class="font-semibold text-accent">Rider Dashboard</span>
                    <span class="block mt-1 text-sm text-text-muted">View your application status and assigned deliveries.</span>
                </a>
            @endif
            @if($isSellerProfile)
                <a href="{{ route('seller.dashboard') }}" class="block fs-card p-4 hover:border-accent">
                    <span class="font-semibold text-accent">Seller Dashboard</span>
                    <span class="block mt-1 text-sm text-text-muted">View your shop's products, stock, and order items.</span>
                </a>
            @endif
            @if($user->role === \App\Enums\UserRole::Admin)
                <a href="{{ route('admin.dashboard') }}" class="block fs-card p-4 hover:border-accent">
                    <span class="font-semibold text-accent">Admin Dashboard</span>
                    <span class="block mt-1 text-sm text-text-muted">View marketplace activity, orders, and inventory alerts.</span>
                </a>
            @endif
            @if($isSellerProfile)
                @if($user->shop?->is_active)
                    <a href="{{ route('shops.show', $user->shop) }}" class="block fs-card p-4 hover:border-accent">
                        <span class="font-semibold text-accent">My Storefront</span>
                        <span class="block mt-1 text-sm text-text-muted">View {{ $user->shop->name }} as customers see it.</span>
                    </a>
                @else
                    <div class="fs-card p-4">
                        <p class="font-semibold">{{ $user->shop ? 'Shop inactive' : 'No shop linked yet' }}</p>
                        <p class="mt-1 text-sm text-text-muted">{{ $user->shop ? 'Your storefront is not publicly visible. You can still review inventory in Seller Dashboard.' : 'Contact the project administrator to set up your shop.' }}</p>
                    </div>
                @endif
            @elseif($isRiderProfile)
                <a href="{{ $user->riderProfile ? route('rider.profile') : route('rider.register') }}" class="block fs-card p-4 hover:border-accent">
                    <span class="font-semibold text-accent">Rider Application</span>
                    <span class="block mt-1 text-sm text-text-muted">View your rider details or complete your application.</span>
                </a>
            @elseif($user->role === \App\Enums\UserRole::Buyer)
            <a href="{{ route('orders.index') }}" class="block rounded-xl border border-surface-border bg-surface p-4 hover:border-accent">
                <span class="font-semibold text-accent">My Orders</span>
                <span class="block mt-1 text-sm text-text-muted">View your purchases, order status, and details.</span>
            </a>
            <a href="{{ route('account.addresses.index') }}" class="block rounded-xl border border-surface-border bg-surface p-4 hover:border-accent">
                <span class="font-semibold text-accent">My Addresses</span>
                <span class="block mt-1 text-sm text-text-muted">Manage your saved delivery addresses.</span>
            </a>
            @endif
        </div>
    </div>
@endsection

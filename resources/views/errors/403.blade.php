<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Access unavailable | FarSell</title>
    <x-primary-font />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="{{ asset('js/theme.js') }}"></script>
</head>
<body class="min-h-screen bg-surface-muted font-sans text-text-base">
    <main class="mx-auto flex min-h-screen max-w-lg items-center px-5 py-12">
        <section class="fs-card w-full p-6 sm:p-8">
            <p class="text-sm font-semibold text-text-muted">403 · Access unavailable</p>
            <h1 class="mt-3 text-2xl font-bold">You can't open this page</h1>
            @if(request()->routeIs('chat.*'))
                <p class="mt-3 text-sm leading-6 text-text-muted">Chat is available to buyer and seller accounts. Sign in with one of those accounts to open your conversations.</p>
            @else
                <p class="mt-3 text-sm leading-6 text-text-muted">Your account does not have access to this page.</p>
            @endif
            <a class="btn btn-accent mt-6" href="{{ auth()->check() ? match(auth()->user()->role) {
                \App\Enums\UserRole::Admin => route('admin.dashboard'),
                \App\Enums\UserRole::Seller => route('seller.dashboard'),
                \App\Enums\UserRole::Rider => route('rider.dashboard'),
                default => route('home'),
            } : route('home') }}">Return to your dashboard</a>
        </section>
    </main>
</body>
</html>

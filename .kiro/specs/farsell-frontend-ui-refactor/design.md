# Design Document

## Overview

This document describes the frontend refactor of the FarSell Laravel application. The goal is to elevate the UI to a premium e-commerce level by: hardening the global design token and card system, introducing a right slide-over profile drawer, converting the static home hero to an auto-sliding carousel, building a Shopee-style account dashboard, adding a filter sidebar to the catalog, and polishing the home page, rider form, cart empty state, and shops directory. Every change is confined to Blade templates, Alpine.js components, and Tailwind/CSS — no backend business logic, database schema, or route contracts are altered, except for three narrowly scoped backend additions (product shop-filter, profile data enrichment, and passing orders/addresses to the profile view).

---

## Architecture

### Files Modified

| File Path | Change Type | Requirement(s) |
|-----------|-------------|----------------|
| `resources/css/app.css` | Modify | 1, 6 |
| `resources/js/app.js` | Modify | 3, 7a |
| `tailwind.config.js` | Modify | 1 |
| `resources/views/layouts/app.blade.php` | Modify | 1, 2, 6 |
| `resources/views/layouts/portal.blade.php` | Modify | 6 |
| `resources/views/home.blade.php` | Modify | 3, 7a |
| `resources/views/catalog/index.blade.php` | Modify | 5 |
| `resources/views/catalog/partials/card.blade.php` | Modify | 1 |
| `resources/views/account/profile.blade.php` | Modify | 4 |
| `resources/views/cart/index.blade.php` | Modify | 7c |
| `resources/views/rider/register.blade.php` | Modify | 7b |
| `resources/views/shop/index.blade.php` | Modify | 7d |
| `app/Http/Controllers/Catalog/ProductController.php` | Modify | 5 |
| `app/Http/Controllers/Account/ProfileController.php` | Modify | 4 |

### Files Created

| File Path | Purpose | Requirement(s) |
|-----------|---------|----------------|
| `resources/views/catalog/partials/filter-sidebar.blade.php` | Extracted filter sidebar partial used by `catalog/index.blade.php` | 5 |

---

## Component Designs

### 1. Design Token & CSS Architecture (Requirements 1, 6)

#### Tailwind config additions (`tailwind.config.js`)

The existing `boxShadow` extension already has `card` and `card-md`. Add `card-sm` as an alias for the lighter scale so the requirements terminology maps 1-to-1:

```js
boxShadow: {
    'card-sm': '0 1px 4px 0 rgb(0 0 0 / .06), 0 1px 2px -1px rgb(0 0 0 / .06)',  // alias for current 'card'
    'card':    '0 1px 4px 0 rgb(0 0 0 / .06), 0 1px 2px -1px rgb(0 0 0 / .06)',  // keep for backward compat
    'card-md': '0 4px 14px 0 rgb(0 0 0 / .08)',
    'accent-glow': '0 4px 18px 0 rgb(var(--color-accent) / .35)',
},
```

#### `.fs-card` in `app.css` (updated full definition)

The existing `.fs-card` rule already covers the core, but must be extended to include `transition-all duration-150` and the hover accent border, and the two-layer shadow:

```css
.fs-card {
    background-color: rgb(var(--color-surface));
    border: 1px solid rgb(var(--color-surface-border) / 0.6);
    box-shadow: 0 1px 4px 0 rgb(0 0 0 / .06), 0 1px 2px -1px rgb(0 0 0 / .06);
    @apply rounded-2xl transition-all duration-150;
}
.fs-card:hover {
    border-color: rgb(var(--color-accent));
    box-shadow: 0 4px 14px 0 rgb(0 0 0 / .08);
}
/* Dark-mode surface override for cards */
.dark .fs-card {
    background-color: #23212d;
}
```

Because both `background-color` and `border-color` reference CSS custom properties that are updated on the `<html>` element when the dark class is toggled, theme changes propagate within one animation frame without a reload.

#### `.fs-card-hero` variant (new, for Requirement 1 AC4)

Add to `@layer components`:

```css
.fs-card-hero {
    @apply fs-card relative overflow-hidden;
    /* Hero gradient overlay bottom-to-top */
    /* Applied via ::after in the Blade template using inline style for flexibility */
}
```

The hero variant is handled structurally in the card partial (see §Component Designs 1 below) rather than as a separate CSS class, because the gradient overlay requires an absolutely-positioned `<div>` child and a "Featured" badge.

#### Sticky footer (Requirement 6)

In `layouts/app.blade.php`, change `<body>` class from `min-h-screen antialiased ...` to `min-h-screen flex flex-col antialiased ...` and add `class="flex-1"` to `<main>`. In `layouts/portal.blade.php`, change `<body class="h-full ...">` to `<body class="min-h-screen flex flex-col ...">` and wrap `@yield('content')` in `<main class="flex-1">`.

---

### 2. Profile Pill & Right Slide-Over Drawer (Requirement 2)

#### Alpine.js component — inline on `<header>`

The drawer is powered by a single `x-data` block hoisted onto a new wrapper `<div>` that sits outside the `<header>` element so it can cover the full viewport. The `<header>` itself gains `@click.outside` awareness through the shared parent scope.

```js
// x-data shape (inline in Blade)
{
    drawerOpen: false,
    openDrawer() {
        this.drawerOpen = true;
        document.body.style.overflow = 'hidden';
        this.$nextTick(() => this.$refs.drawerClose.focus());
    },
    closeDrawer() {
        this.drawerOpen = false;
        document.body.style.overflow = '';
        this.$nextTick(() => this.$refs.profilePill.focus());
    }
}
```

#### Blade structure changes in `layouts/app.blade.php`

The outer `<body>` tag gains `x-data="{ drawerOpen: false, ... }"` with the above shape. `@keydown.escape.window="closeDrawer()"` is added to the body element.

**Profile pill markup** — replaces the existing desktop `@auth` dropdown block and the utility bar sign-in/register links. The pill is visible on all breakpoints (the hamburger menu is retained for mobile nav items, the pill replaces only the account affordance):

```html
<!-- Auth: pill with initials + truncated name -->
<button x-ref="profilePill"
        @click="openDrawer()"
        :aria-expanded="drawerOpen"
        aria-haspopup="dialog"
        class="hidden sm:flex items-center gap-2 rounded-full border border-surface-border
               bg-surface px-3 py-1.5 text-sm font-medium transition-colors hover:border-accent
               max-w-[160px]">
    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-[10px]
                 font-bold"
          style="background-color:rgb(var(--color-accent));color:rgb(var(--color-accent-text));">
        {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
    </span>
    <span class="truncate max-w-[160px]">{{ auth()->user()->name }}</span>
</button>

<!-- Guest: pill with Sign In | Register -->
<div class="hidden sm:flex items-center gap-1 rounded-full border border-surface-border
            bg-surface px-3 py-1.5 text-sm">
    <a href="{{ route('login') }}" class="text-accent font-medium hover:underline">Sign In</a>
    <span class="text-surface-border mx-1">|</span>
    <a href="{{ route('register') }}" class="text-accent font-medium hover:underline">Register</a>
</div>
```

**Drawer markup** — placed immediately before `</body>`:

```html
<!-- Backdrop -->
<div x-show="drawerOpen"
     x-transition:enter="transition-opacity duration-250"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition-opacity duration-200"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     @click="closeDrawer()"
     class="fixed inset-0 z-50 bg-black/60 backdrop-blur-sm"
     aria-hidden="true"
     style="display:none;"></div>

<!-- Drawer panel -->
<div x-show="drawerOpen"
     x-transition:enter="transition ease-out duration-250"
     x-transition:enter-start="translate-x-full"
     x-transition:enter-end="translate-x-0"
     x-transition:leave="transition ease-in duration-200"
     x-transition:leave-start="translate-x-0"
     x-transition:leave-end="translate-x-full"
     role="dialog"
     aria-modal="true"
     aria-label="Account panel"
     class="fixed inset-y-0 right-0 z-50 flex flex-col overflow-y-auto
            w-full sm:w-80 shadow-card-md transform"
     style="background-color:rgb(var(--color-surface)); display:none;">
    ...
</div>
```

The `w-full sm:w-80` class combination implements the responsive width: `100vw` below 640 px, `320 px` at 640 px and above (Requirement 2 AC8).

**Focus trap**: achieved with `tabindex="-1"` on all interactive elements outside the drawer while it is open, toggled via an Alpine `$watch('drawerOpen', ...)` that queries `.js-focus-trap-target` elements. A simpler alternative that avoids querying the DOM is to use a `<div inert>` on the page content wrapper — set `x-bind:inert="drawerOpen"` on the `<main>` element. This is the preferred approach as `inert` is natively supported and handles focus trapping without JavaScript iteration.

**Drawer content sections** (auth state, top to bottom):

```
[Close button — x-ref="drawerClose"]
[Avatar block]
  - 56px initials circle (accent bg)
  - user.name (font-semibold)
  - @{{ email prefix }} (text-muted text-sm)
  - "Member since {{ created_at->year }}" (text-xs text-muted)
  - "0 pts — Loyalty Points" (text-xs badge-accent)
[Divider]
[Quick Links — section heading]
  - Wishlist (link disabled, x-tooltip="Coming soon")
  - Order History → route('orders.index')
  - Saved Addresses → route('account.addresses.index')
[Divider]
[Sign Out POST form → route('logout')]
```

**Guest drawer content**: centred block — "You're not signed in" heading, `.btn-accent` → `route('login')`, secondary `<a>` → `route('register')`. No Quick Links section.

The utility bar desktop sign-in/register links are removed (the pill covers that use case). The mobile hamburger menu links for sign-in/register are retained so mobile guest users can still navigate via the hamburger.

---

### 3. Hero Auto-Sliding Carousel (Requirement 3)

#### `app.js` — extend existing `carousel()` component

The existing function is extended with `paused`, `playing`, swipe tracking, and the required play/pause toggle. The function signature and all existing properties are preserved:

```js
Alpine.data('carousel', (total = 1, autoplay = 5000) => ({
    current: 0,
    total,
    timer: null,
    paused: false,
    touchStartX: 0,

    init() {
        if (this.total === 0) return;   // AC8 — guard against empty carousel
        if (autoplay > 0 && !this.paused) {
            this.timer = setInterval(() => this.next(), autoplay);
        }
    },
    destroy() {
        if (this.timer) clearInterval(this.timer);
    },
    next() {
        if (this.total === 0) return;
        this.current = (this.current + 1) % this.total;
    },
    prev() {
        if (this.total === 0) return;
        this.current = (this.current - 1 + this.total) % this.total;
    },
    goTo(index) {
        if (this.total === 0) return;
        this.current = index;
        if (this.timer) {
            clearInterval(this.timer);
            if (autoplay > 0 && !this.paused) {
                this.timer = setInterval(() => this.next(), autoplay);
            }
        }
    },
    isActive(index) {
        return this.current === index;
    },
    pause() {
        this.paused = true;
        if (this.timer) { clearInterval(this.timer); this.timer = null; }
    },
    play() {
        this.paused = false;
        if (autoplay > 0 && !this.timer) {
            this.timer = setInterval(() => this.next(), autoplay);
        }
    },
    togglePlay() {
        this.paused ? this.play() : this.pause();
    },
    onTouchStart(e) {
        this.touchStartX = e.changedTouches[0].clientX;
    },
    onTouchEnd(e) {
        const delta = this.touchStartX - e.changedTouches[0].clientX;
        if (Math.abs(delta) >= 50) {
            delta > 0 ? this.next() : this.prev();
        }
    },
}));
```

#### `home.blade.php` — carousel markup

The static `<section class="rounded-2xl bg-gradient-to-r ...">` hero is replaced entirely. The slides are defined as a PHP array at the top of the `@section('content')` block:

```php
@php
$slides = [
    [
        'title'    => 'Auction surplus. Everyday prices.',
        'subtitle' => 'Discover Japan lots from local shops. Browse freely, sign in when ready.',
        'cta_text' => 'Browse products',
        'cta_href' => route('catalog.index'),
        'bg'       => 'from-violet-600 to-indigo-600',
    ],
    [
        'title'    => 'Open your shop today',
        'subtitle' => 'Sell surplus inventory to thousands of buyers across the Philippines.',
        'cta_text' => 'Become a Seller',
        'cta_href' => route('seller.apply'),
        'bg'       => 'from-emerald-600 to-teal-600',
    ],
    [
        'title'    => 'Deliver & earn with FarSell',
        'subtitle' => 'Join our rider network and earn on every completed delivery.',
        'cta_text' => 'Become a Rider',
        'cta_href' => route('rider.register'),
        'bg'       => 'from-orange-500 to-pink-600',
    ],
];
@endphp
```

Carousel wrapper:

```html
<div x-data="carousel({{ count($slides) }}, 5000)"
     @mouseenter="pause()"
     @mouseleave="play()"
     @touchstart.passive="onTouchStart($event)"
     @touchend.passive="onTouchEnd($event)"
     class="relative rounded-2xl overflow-hidden mb-4"
     :class="total === 0 ? 'h-0' : ''">

    <!-- Slides -->
    @foreach($slides as $i => $slide)
    <div x-show="isActive({{ $i }})"
         x-transition:enter="transition-opacity duration-500"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         class="min-h-[240px] lg:min-h-[400px] bg-gradient-to-r {{ $slide['bg'] }}
                text-white p-6 lg:p-10 flex flex-col justify-end"
         style="{{ $i > 0 ? 'display:none;' : '' }}">
        <h2 class="text-2xl lg:text-4xl font-bold">{{ $slide['title'] }}</h2>
        <p class="mt-2 text-sm lg:text-base text-white/80 max-w-xl">{{ $slide['subtitle'] }}</p>
        <a href="{{ $slide['cta_href'] }}"
           class="mt-4 self-start rounded-full bg-white/20 border border-white/40
                  text-white text-sm font-medium px-5 py-2 hover:bg-white/30 transition-colors">
            {{ $slide['cta_text'] }}
        </a>
    </div>
    @endforeach

    <!-- Prev / Next buttons (hidden when total === 1) -->
    <button x-show="total > 1"
            @click="prev()"
            aria-label="Previous slide"
            class="absolute left-3 top-1/2 -translate-y-1/2 z-10 flex h-9 w-9 items-center
                   justify-center rounded-full bg-black/40 backdrop-blur-sm text-white
                   hover:bg-black/60 transition-colors"
            style="display:none;">
        <!-- Left chevron SVG -->
    </button>
    <button x-show="total > 1"
            @click="next()"
            aria-label="Next slide"
            class="absolute right-3 top-1/2 -translate-y-1/2 z-10 flex h-9 w-9 items-center
                   justify-center rounded-full bg-black/40 backdrop-blur-sm text-white
                   hover:bg-black/60 transition-colors"
            style="display:none;">
        <!-- Right chevron SVG -->
    </button>

    <!-- Play/Pause toggle -->
    <button @click="togglePlay()"
            :aria-label="paused ? 'Play slideshow' : 'Pause slideshow'"
            class="absolute bottom-3 right-3 z-10 flex h-7 w-7 items-center justify-center
                   rounded-full bg-black/40 backdrop-blur-sm text-white hover:bg-black/60
                   transition-colors">
        <!-- Pause icon when !paused, Play icon when paused -->
    </button>

    <!-- Pagination dots (hidden when total === 1) -->
    <div x-show="total > 1"
         class="absolute bottom-3 left-1/2 -translate-x-1/2 flex gap-1.5 z-10"
         style="display:none;">
        @foreach($slides as $i => $_)
        <button @click="goTo({{ $i }})"
                :class="isActive({{ $i }})
                    ? 'w-6 h-2 rounded-full bg-purple-500 transition-all duration-200'
                    : 'w-2 h-2 rounded-full bg-white/50'"
                :aria-label="'Go to slide {{ $i + 1 }}'">
        </button>
        @endforeach
    </div>
</div>
```

---

### 4. Account Dashboard (Requirement 4)

#### Backend: `ProfileController@show` addition

The controller `show()` method needs to eagerly load orders and addresses so the view can render the My Purchase and Addresses panels without N+1 queries:

```php
// Add inside show() before the return view() call:
$orders = $user->orders()->with('items')->latest()->get();
$addresses = $user->addresses()->get();
return view('account.profile', compact('user', 'isSellerProfile', 'isRiderProfile', 'orders', 'addresses'));
```

No route changes. The existing `route('account.profile')` remains.

#### Tab → OrderStatus mapping

| Tab key (activeTab) | OrderStatus values filtered |
|---------------------|-----------------------------|
| `profile` | n/a — profile panel |
| `addresses` | n/a — address shortcut |
| `all` | all orders |
| `pending_payment` | `pending_payment` |
| `paid_packed` | `paid`, `packed` |
| `assigned_in_transit` | `assigned`, `in_transit` |
| `delivered` | `delivered` |
| `cancelled` | `cancelled` |
| `review` | placeholder |

#### Layout: `account/profile.blade.php`

The existing single-column view is replaced with a two-column layout. The page uses Alpine `x-data` to manage tab state with no full-page reload:

```js
// x-data shape
{
    activeTab: 'profile'  // default
}
```

**Structural skeleton**:

```html
<div x-data="{ activeTab: 'profile' }" class="flex flex-col md:flex-row gap-6">

    <!-- LEFT SIDEBAR: hidden on mobile, replaced by pill tabs -->
    <aside class="hidden md:flex flex-col w-56 shrink-0 gap-1">
        <!-- "My Account" group -->
        <p class="section-title px-3 py-2">My Account</p>
        <button @click="activeTab='profile'"    :class="..." class="...">Profile</button>
        <button disabled class="...">Bank & Cards</button>
        <button @click="activeTab='addresses'"  :class="..." class="...">Addresses</button>
        <button disabled class="...">Privacy Settings</button>
        <button disabled class="...">Notification Settings</button>

        <!-- "My Purchase" group -->
        <p class="section-title px-3 py-2 mt-4">My Purchase</p>
        <button @click="activeTab='all'"                  ...>All</button>
        <button @click="activeTab='pending_payment'"      ...>To Pay</button>
        <button @click="activeTab='paid_packed'"          ...>To Ship</button>
        <button @click="activeTab='assigned_in_transit'"  ...>To Receive</button>
        <button @click="activeTab='delivered'"            ...>Completed</button>
        <button @click="activeTab='cancelled'"            ...>Cancelled</button>
        <button disabled                                  ...>Return/Refund</button>
        <button @click="activeTab='review'"               ...>To Review</button>

        <!-- Sidebar footer: role shortcuts -->
        @if($user->role === \App\Enums\UserRole::Seller)
            <a href="{{ route('seller.dashboard') }}" class="...">Seller Dashboard</a>
        @elseif($user->isRider())
            <a href="{{ route('rider.dashboard') }}" class="...">Rider Dashboard</a>
        @elseif($user->role === \App\Enums\UserRole::Admin)
            <a href="{{ route('admin.dashboard') }}" class="...">Admin Panel</a>
        @endif
    </aside>

    <!-- MOBILE: horizontal pill-tab bar (visible below md) -->
    <div class="scroll-x md:hidden shrink-0">
        <button @click="activeTab='profile'" :class="..." class="shrink-0 ...">Profile</button>
        <button @click="activeTab='addresses'" :class="..." class="shrink-0 ...">Addresses</button>
        <button @click="activeTab='all'" :class="..." class="shrink-0 ...">All Orders</button>
        <!-- ... remaining purchase tabs ... -->
    </div>

    <!-- RIGHT CONTENT PANEL -->
    <div class="flex-1 min-w-0">

        <!-- Profile panel -->
        <div x-show="activeTab === 'profile'" class="fs-card p-5 space-y-3">
            ...
            @if($user->role === \App\Enums\UserRole::Buyer)
                <a href="{{ route('seller.apply') }}" class="fs-card p-4 block hover:border-accent">
                    <span class="font-semibold text-accent">Become a Seller</span>
                    ...
                </a>
            @endif
        </div>

        <!-- Addresses panel -->
        <div x-show="activeTab === 'addresses'" style="display:none;" class="fs-card p-5">
            <a href="{{ route('account.addresses.index') }}" class="...">
                <!-- default address label + count -->
            </a>
        </div>

        <!-- Order panels (all, pending_payment, paid_packed, ...) -->
        @foreach(['all','pending_payment','paid_packed','assigned_in_transit','delivered','cancelled'] as $tabKey)
        <div x-show="activeTab === '{{ $tabKey }}'" style="display:none;" class="space-y-3">
            @php
                $tabOrders = match($tabKey) {
                    'all'                  => $orders,
                    'pending_payment'      => $orders->where('status.value', 'pending_payment'),
                    'paid_packed'          => $orders->whereIn('status.value', ['paid','packed']),
                    'assigned_in_transit'  => $orders->whereIn('status.value', ['assigned','in_transit']),
                    'delivered'            => $orders->where('status.value', 'delivered'),
                    'cancelled'            => $orders->where('status.value', 'cancelled'),
                };
            @endphp
            @forelse($tabOrders as $order)
                <div class="fs-card p-4 flex items-center justify-between">
                    <div>
                        <p class="font-semibold text-sm">#{{ $order->number }}</p>
                        <p class="text-xs text-text-muted">{{ $order->created_at->format('M j, Y') }}</p>
                        <p class="text-xs mt-1"><span class="badge badge-accent">{{ $order->status->label() }}</span></p>
                    </div>
                    <p class="text-sm text-text-muted">{{ $order->items->count() }} item(s)</p>
                </div>
            @empty
                <p class="fs-card p-5 text-sm text-text-muted">No orders in this category.</p>
            @endforelse
        </div>
        @endforeach

        <!-- Review placeholder -->
        <div x-show="activeTab === 'review'" style="display:none;" class="fs-card p-5">
            <p class="text-sm text-text-muted">Seller reviews via Google Reviews are coming soon.</p>
        </div>

    </div>
</div>
```

Active sidebar button style: uses `:class="activeTab === 'profile' ? 'bg-accent-subtle text-accent font-semibold' : 'text-text-muted hover:text-text-base hover:bg-surface-muted'"` pattern applied to each button.

---

### 5. Filter Sidebar & Catalog Grid (Requirement 5)

#### Backend: `ProductController@index` — shop filter addition

Add after the existing category filter block:

```php
$shopIds = array_filter((array) $request->input('shop', []));
if (!empty($shopIds)) {
    $query->whereIn('shop_id', $shopIds);
}
```

Also pass shops to the view for the sidebar brand list:

```php
use App\Models\Shop;

return view('catalog.index', [
    'products'       => $query->latest()->paginate(16)->withQueryString(),
    'categories'     => Category::query()->orderBy('sort_order')->get(),
    'shops'          => Shop::query()->where('is_active', true)
                            ->withCount(['products' => fn($q) => $q->visible()])
                            ->orderBy('name')->get(),
    'q'              => $search ?? '',
    'activeCategory' => $request->integer('category') ?: null,
    'activeShops'    => $shopIds,
    'priceMin'       => $request->integer('price_min') ?: '',
    'priceMax'       => $request->integer('price_max') ?: '',
    'activeRatings'  => $request->input('rating', []),
]);
```

#### `catalog/index.blade.php` — layout restructure

```html
@section('content')
@php $productCount = $products->total(); @endphp

{{-- Category pill strip --}}
<div class="scroll-x mb-4">
    <a href="{{ route('catalog.index') }}"
       class="shrink-0 rounded-full px-4 py-1.5 text-sm font-medium transition-colors
              {{ !$activeCategory ? 'bg-accent text-white' : 'border border-surface-border text-text-muted hover:border-accent' }}">
        All
    </a>
    @foreach($categories as $cat)
    <a href="{{ route('catalog.index', ['category' => $cat->id]) }}"
       class="shrink-0 rounded-full px-4 py-1.5 text-sm font-medium transition-colors
              {{ $activeCategory === $cat->id ? 'bg-accent text-white' : 'border border-surface-border text-text-muted hover:border-accent' }}">
        {{ $cat->name }}
    </a>
    @endforeach
</div>

{{-- Main area: sidebar + grid --}}
<div x-data="{ filtersOpen: false }" class="flex gap-5">

    {{-- Filter sidebar — persistent on lg, bottom-sheet on smaller --}}
    @include('catalog.partials.filter-sidebar')

    {{-- Product area --}}
    <div class="flex-1 min-w-0">

        {{-- Mobile filter toggle --}}
        <div class="flex items-center justify-between mb-3 lg:hidden">
            <p class="text-sm text-text-muted">{{ $products->total() }} products</p>
            <button @click="filtersOpen = true"
                    class="btn-outline text-sm px-3 py-1.5">Filters</button>
        </div>

        {{-- Product grid --}}
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
            @forelse($products as $loop_index => $product)
                @include('catalog.partials.card', [
                    'product'     => $product,
                    'heroVariant' => $productCount >= 6 && $loop->first,
                ])
            @empty
                <p class="col-span-full text-sm text-text-muted fs-card p-5">No lots match that search.</p>
            @endforelse
        </div>

        <div class="mt-4">{{ $products->links() }}</div>
    </div>
</div>
@endsection
```

#### `catalog/partials/filter-sidebar.blade.php`

```html
{{-- Desktop persistent sidebar --}}
<aside class="hidden lg:flex flex-col w-60 shrink-0 gap-5">
    @include('catalog.partials._filter-form-body')
</aside>

{{-- Mobile bottom sheet --}}
<div x-show="filtersOpen"
     class="fixed inset-x-0 bottom-0 z-50 max-h-[80vh] overflow-y-auto rounded-t-2xl
            shadow-card-md lg:hidden"
     style="background-color:rgb(var(--color-surface)); display:none;"
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="translate-y-full"
     x-transition:enter-end="translate-y-0"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="translate-y-0"
     x-transition:leave-end="translate-y-full">
    <div class="flex items-center justify-between p-4 border-b border-surface-border">
        <p class="font-semibold">Filters</p>
        <button @click="filtersOpen = false" class="btn-ghost p-1">✕</button>
    </div>
    @include('catalog.partials._filter-form-body')
</div>
```

Both desktop and mobile share the same `_filter-form-body` include (a second new partial, or the body can be inlined in both). The filter body wraps its contents in a `<form method="get" action="{{ route('catalog.index') }}">` and includes hidden inputs that preserve all active filters on submit:

```html
<form method="get" action="{{ route('catalog.index') }}" class="p-4 space-y-5">
    {{-- Preserve existing query params as hidden inputs --}}
    @if($q)        <input type="hidden" name="q"        value="{{ $q }}"> @endif
    @if($activeCategory) <input type="hidden" name="category" value="{{ $activeCategory }}"> @endif

    {{-- Price range --}}
    <div>
        <p class="fs-label">Price range</p>
        <div class="flex gap-2">
            <input type="number" name="price_min" value="{{ $priceMin }}"
                   placeholder="Min" class="fs-input w-full">
            <input type="number" name="price_max" value="{{ $priceMax }}"
                   placeholder="Max" class="fs-input w-full">
        </div>
    </div>

    {{-- Star rating --}}
    <div>
        <p class="fs-label">Rating</p>
        @foreach([5,4,3,2,1] as $star)
        <label class="flex items-center gap-2 text-sm py-1 cursor-pointer">
            <input type="checkbox" name="rating[]" value="{{ $star }}"
                   @checked(in_array($star, $activeRatings))
                   class="rounded">
            {{ $star }} Star{{ $star < 5 ? ' & up' : '' }}
        </label>
        @endforeach
    </div>

    {{-- Shop / brand filter --}}
    <div x-data="{ shopSearch: '' }">
        <p class="fs-label">Shop</p>
        <input type="text" x-model="shopSearch" placeholder="Search shops..."
               class="fs-input mb-2">
        @foreach($shops as $shop)
        <label x-show="shopSearch === '' || '{{ strtolower($shop->name) }}'.includes(shopSearch.toLowerCase())"
               class="flex items-center justify-between gap-2 text-sm py-1 cursor-pointer">
            <span class="flex items-center gap-2">
                <input type="checkbox" name="shop[]" value="{{ $shop->id }}"
                       @checked(in_array($shop->id, $activeShops))
                       class="rounded">
                {{ $shop->name }}
            </span>
            <span class="text-xs text-text-muted">{{ $shop->products_count }}</span>
        </label>
        @endforeach
    </div>

    {{-- Delivery options (UI-only placeholders) --}}
    <div>
        <p class="fs-label">Delivery</p>
        <label class="flex items-center justify-between py-1"
               x-tooltip="Coming soon">
            <span class="text-sm text-text-muted">Same-day delivery</span>
            <input type="checkbox" disabled class="rounded opacity-40">
        </label>
        <label class="flex items-center justify-between py-1"
               x-tooltip="Coming soon">
            <span class="text-sm text-text-muted">FarSell Guaranteed</span>
            <input type="checkbox" disabled class="rounded opacity-40">
        </label>
    </div>

    <button class="btn-accent w-full">Apply filters</button>
</form>
```

#### Card hero variant — `catalog/partials/card.blade.php`

The card partial receives an optional `$heroVariant` boolean. When `true` and the grid has `col-span-2 row-span-2`, the image area gets a gradient overlay and a "Featured" badge:

```php
@php
    $compact     = $compact     ?? false;
    $heroVariant = $heroVariant ?? false;
@endphp
<a href="{{ route('products.show', $product) }}"
   class="fs-card overflow-hidden block transition-all duration-150
          {{ $compact    ? 'w-36 shrink-0'              : '' }}
          {{ $heroVariant ? 'col-span-2 row-span-2'     : '' }}">

    <div class="relative {{ $heroVariant ? 'aspect-[2/1]' : 'aspect-square' }}
                bg-surface-muted flex items-center justify-center text-text-muted text-xs">
        @if($product->image_path)
            <img src="{{ asset('storage/'.$product->image_path) }}"
                 alt="{{ $product->name }}"
                 loading="{{ $heroVariant ? 'eager' : 'lazy' }}"
                 class="h-full w-full object-cover">
        @else
            {{ $product->category?->name ?? 'Item' }}
        @endif

        {{-- Gradient overlay (hero only) --}}
        @if($heroVariant)
        <div class="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent
                    pointer-events-none"></div>
        <span class="absolute top-2 left-2 badge badge-accent text-[10px]">Featured</span>
        @endif
    </div>

    <div class="p-2 {{ $heroVariant ? 'p-3' : '' }}">
        <p class="text-xs text-text-muted truncate">{{ $product->shop->name }}</p>
        <p class="text-sm font-medium line-clamp-2 min-h-[2.5rem]">{{ $product->name }}</p>
        <p class="text-accent font-semibold text-sm mt-1">{{ $product->formattedPrice() }}</p>
        @if($product->compare_at_price)
            <p class="text-[11px] text-text-muted line-through">
                ₱{{ number_format((float) $product->compare_at_price, 2) }}
            </p>
        @endif
    </div>
</a>
```

The `col-span-2 row-span-2` classes only take effect inside the catalog grid (which uses `grid-cols-2`). On the home page `grid-cols-2 sm:grid-cols-4` grid the hero variant is not triggered (the home page passes no `$heroVariant`).

---

### 6. Home Page Enhancements (Requirement 7a)

#### `countdown()` Alpine component — added to `app.js`

```js
Alpine.data('countdown', (endTime) => ({
    display: '00:00:00',
    timer: null,

    init() {
        this.tick();
        this.timer = setInterval(() => this.tick(), 1000);
    },
    destroy() {
        if (this.timer) clearInterval(this.timer);
    },
    tick() {
        const remaining = Math.max(0, endTime - Math.floor(Date.now() / 1000));
        if (remaining === 0) { this.display = 'Ended'; clearInterval(this.timer); return; }
        const h = String(Math.floor(remaining / 3600)).padStart(2, '0');
        const m = String(Math.floor((remaining % 3600) / 60)).padStart(2, '0');
        const s = String(remaining % 60).padStart(2, '0');
        this.display = `${h}:${m}:${s}`;
    },
}));
```

#### Category SVG icon mapping (PHP array in `home.blade.php`)

```php
@php
$categoryIcons = [
    'fashion'       => '<svg ...><!-- shirt icon --></svg>',
    'electronics'   => '<svg ...><!-- chip icon --></svg>',
    'home-living'   => '<svg ...><!-- home icon --></svg>',
    'sports'        => '<svg ...><!-- dumbbell icon --></svg>',
    'toys'          => '<svg ...><!-- puzzle icon --></svg>',
    'beauty'        => '<svg ...><!-- sparkle icon --></svg>',
];
@endphp
```

The category strip renders:

```html
@foreach ($categories as $category)
<a href="{{ route('catalog.index', ['category' => $category->id]) }}"
   class="shrink-0 w-16 text-center">
    <div class="h-14 w-14 mx-auto rounded-2xl bg-surface border border-surface-border
                flex items-center justify-center text-lg">
        @if(isset($categoryIcons[$category->slug]))
            {!! $categoryIcons[$category->slug] !!}
        @else
            {{ $category->icon }}
        @endif
    </div>
    <p class="mt-1 text-[11px] text-text-muted truncate">{{ $category->name }}</p>
</a>
@endforeach
```

#### Flash deal cards — countdown + stock bar

Each flash deal card is wrapped in an Alpine `x-data="countdown({{ $midnight }})"` where `$midnight` is computed once before the loop:

```php
@php $midnight = strtotime('tomorrow midnight'); @endphp
@foreach($flash as $product)
<div x-data="countdown({{ $midnight }})" class="w-36 shrink-0">
    @include('catalog.partials.card', ['product' => $product, 'compact' => true])
    {{-- Countdown --}}
    <p class="text-center text-[11px] font-mono text-accent mt-1" x-text="display"></p>
    {{-- Stock progress bar --}}
    @php $pct = min(100, ($product->stock / 50) * 100); @endphp
    <div class="mt-1 h-1.5 rounded-full overflow-hidden"
         style="background-color:rgb(var(--color-surface-muted))">
        <div class="h-full rounded-full transition-all"
             style="width:{{ $pct }}%; background-color:rgb(var(--color-accent));"></div>
    </div>
</div>
@endforeach
```

#### Skeleton shimmer cards

Four skeleton cards are shown until Alpine initialises, then hidden. This requires a single controlling `x-data` block on the product grid wrapper:

```html
<div x-data="{ ready: false }" x-init="$nextTick(() => ready = true)"
     class="grid grid-cols-2 sm:grid-cols-4 gap-3">
    {{-- Skeleton cards (visible before Alpine init, hidden after) --}}
    <template x-if="!ready">
        @for($i = 0; $i < 4; $i++)
        <div class="fs-card animate-pulse overflow-hidden">
            <div class="aspect-square bg-surface-muted"></div>
            <div class="p-2 space-y-2">
                <div class="h-2.5 rounded bg-surface-muted w-3/4"></div>
                <div class="h-2.5 rounded bg-surface-muted w-1/2"></div>
            </div>
        </div>
        @endfor
    </template>

    {{-- Real product cards --}}
    @forelse($products as $product)
        @include('catalog.partials.card', ['product' => $product])
    @empty
        <p class="col-span-full rounded-xl border border-surface-border bg-surface p-6
                  text-sm text-text-muted">No products are available yet. Check back soon.</p>
    @endforelse
</div>
```

Note: `<template x-if>` renders/removes DOM nodes, so the skeletons are server-rendered in the HTML but immediately removed once Alpine boots. This ensures no flash of skeleton after SSR.

---

### 7. Rider Multi-Step Form (Requirement 7b)

#### Alpine `x-data` shape

```js
{
    step: {{ ($errors->any() ? 1 : 1) }},  // always start at 1; reset to 1 on server errors
    errors: { phone: '', vehicle_type: '', license_no: '', city: '' },

    validateStep1() {
        this.errors.phone = this.$el.querySelector('[name=phone]').value.trim() === ''
            ? 'Phone is required.' : '';
        return this.errors.phone === '';
    },
    validateStep2() {
        const vt = this.$el.querySelector('[name=vehicle_type]').value;
        const ln = this.$el.querySelector('[name=license_no]').value.trim();
        const ci = this.$el.querySelector('[name=city]').value.trim();
        this.errors.vehicle_type = vt === '' ? 'Vehicle type is required.' : '';
        this.errors.license_no   = ln === '' ? 'License number is required.' : '';
        this.errors.city         = ci === '' ? 'City is required.' : '';
        return !this.errors.vehicle_type && !this.errors.license_no && !this.errors.city;
    },
    nextStep() {
        if (this.step === 1 && this.validateStep1()) this.step++;
        else if (this.step === 2 && this.validateStep2()) this.step++;
    },
    prevStep() { if (this.step > 1) this.step--; },

    // Drag-and-drop state per file field
    files: { license: null, id: null, vehicle_reg: null },
    dragOver: { license: false, id: false, vehicle_reg: false },
}
```

#### Step indicator

Three circles at the top of the form. Each circle uses:
- **Completed** (step > n): filled accent background + white checkmark SVG
- **Current** (step === n): accent border + accent number text
- **Future** (step < n): surface-border + muted number text

```html
<div class="flex items-center mb-6">
    @foreach([1 => 'Personal Info', 2 => 'Vehicle Details', 3 => 'Documents'] as $n => $label)
    <div class="flex items-center {{ $n < 3 ? 'flex-1' : '' }}">
        <div class="flex flex-col items-center">
            <div :class="{
                'bg-accent text-white': step > {{ $n }},
                'border-2 border-accent text-accent': step === {{ $n }},
                'border-2 border-surface-border text-text-muted': step < {{ $n }}
                 }"
                 class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold">
                <template x-if="step > {{ $n }}"><!-- checkmark SVG --></template>
                <template x-if="step <= {{ $n }}">{{ $n }}</template>
            </div>
            <p class="text-[10px] mt-1 text-text-muted">{{ $label }}</p>
        </div>
        @if($n < 3)
        <div class="flex-1 h-px mx-2"
             :style="step > {{ $n }}
                ? 'background-color:rgb(var(--color-accent))'
                : 'background-color:rgb(var(--color-surface-border))'"></div>
        @endif
    </div>
    @endforeach
</div>
```

#### Drag-and-drop file zones (Step 3)

Each file input is replaced with a drop zone `<div>`:

```html
<div x-data="{ filename: null, dragging: false }"
     @dragover.prevent="dragging = true"
     @dragleave.prevent="dragging = false"
     @drop.prevent="dragging = false; filename = $event.dataTransfer.files[0]?.name;
                    $refs.licenseInput.files = $event.dataTransfer.files"
     :class="dragging ? 'border-accent' : 'border-surface-border'"
     class="rounded-xl border-2 border-dashed p-5 text-center cursor-pointer
            transition-colors hover:border-accent"
     @click="$refs.licenseInput.click()">
    <template x-if="!filename">
        <p class="text-sm text-text-muted">
            Drop file here or <span class="text-accent font-medium">browse</span>
        </p>
    </template>
    <template x-if="filename">
        <div class="flex items-center justify-center gap-2">
            <span class="text-sm font-medium" x-text="filename"></span>
            <button type="button" @click.stop="filename = null; $refs.licenseInput.value = ''"
                    class="text-error text-xs hover:underline">Remove</button>
        </div>
    </template>
    <input x-ref="licenseInput" type="file" name="license_document"
           accept="image/*,.pdf" class="sr-only"
           @change="filename = $event.target.files[0]?.name">
</div>
```

The same pattern is repeated for `id_document` and `vehicle_reg_document` with different `x-ref` names.

#### Server error display

When `$errors->any()` is true, an alert block is prepended to the Step 1 panel:

```html
<div x-show="step === 1">
    @if($errors->any())
    <div class="alert-error mb-4" role="alert">
        <ul class="list-disc pl-4 space-y-0.5">
            @foreach($errors->all() as $e)
                <li>{{ $e }}</li>
            @endforeach
        </ul>
    </div>
    @endif
    ...
</div>
```

The form initialises with `step: 1` unconditionally (since server errors always require correction of Step 1 or 2 fields), matching AC 7b.6.

---

### 8. Cart Empty State (Requirement 7c)

`cart/index.blade.php` replaces the existing `@if($lines->isEmpty())` block:

```html
@if($lines->isEmpty())
    {{-- No h1 "Cart" heading rendered when empty per AC 7c.2 --}}
    <div class="flex flex-col items-center justify-center py-20 text-center">
        {{-- Inline SVG cart illustration ~96×96px --}}
        <svg xmlns="http://www.w3.org/2000/svg"
             class="w-24 h-24 text-text-muted mb-4"
             fill="none" viewBox="0 0 96 96"
             stroke="currentColor" stroke-width="1.5">
            <circle cx="34" cy="80" r="6"/>
            <circle cx="72" cy="80" r="6"/>
            <path stroke-linecap="round" stroke-linejoin="round"
                  d="M6 8h12l10 48h44l8-32H26"/>
        </svg>
        <p class="text-xl font-semibold">Your cart is empty</p>
        <p class="text-sm text-text-muted mt-1">Looks like you haven't added anything yet.</p>
        <a href="{{ route('catalog.index') }}" class="btn-accent mt-6">Browse products</a>
    </div>
@else
    <h1 class="text-lg font-semibold mb-3">Cart</h1>
    {{-- existing cart lines ... --}}
@endif
```

---

### 9. Shops Directory Grid (Requirement 7d)

#### `shop/index.blade.php` — full replacement

The existing list-style `<article>` cards are replaced with the Shopee-style banner+avatar card layout. The controller already passes `$shops` with `products_count` loaded — no backend changes needed.

**Banner gradient cycling**: `$shop->id % 6` is used to select from six predefined gradient combinations defined as a PHP array. This produces a deterministic, visually varied colour per shop without any additional database columns:

```php
@php
$gradients = [
    0 => 'from-violet-500 to-indigo-500',
    1 => 'from-emerald-500 to-teal-500',
    2 => 'from-orange-400 to-pink-500',
    3 => 'from-sky-500 to-blue-600',
    4 => 'from-rose-500 to-red-500',
    5 => 'from-amber-400 to-orange-500',
];
@endphp
```

**Card markup** (per shop):

```html
<article class="fs-card overflow-hidden flex flex-col">
    {{-- 80px banner strip --}}
    <div class="relative h-20 bg-gradient-to-r {{ $gradients[$shop->id % 6] }}">
        {{-- 48px avatar overlapping banner by 24px --}}
        <div class="absolute -bottom-6 left-4 flex h-12 w-12 items-center justify-center
                    rounded-full text-lg font-bold border-2 shadow-card"
             style="background-color:rgb(var(--color-accent));
                    color:rgb(var(--color-accent-text));
                    border-color:rgb(var(--color-surface));">
            {{ strtoupper(substr($shop->name, 0, 1)) }}
        </div>
    </div>

    {{-- Card body — padding-top accounts for avatar overlap --}}
    <div class="pt-8 px-4 pb-4 flex flex-col flex-1">
        <h3 class="text-base font-semibold">{{ $shop->name }}</h3>
        @if($shop->city)
            <p class="text-xs text-text-muted">{{ $shop->city }}</p>
        @endif
        @if($shop->tagline)
            <p class="text-sm text-text-muted mt-1 line-clamp-2">{{ $shop->tagline }}</p>
        @endif
        <p class="text-xs text-text-muted mt-2">{{ $shop->products_count }} products</p>
        <a href="{{ route('shops.show', $shop) }}" class="btn-accent mt-auto pt-3 self-start">
            Visit Store
        </a>
    </div>
</article>
```

The avatar overlap is achieved with `absolute -bottom-6` on the avatar div inside the banner, and `pt-8` on the card body to clear it. No negative margin is needed.

**Empty state**:

```html
@empty
    <div role="status" class="col-span-full fs-card p-8 text-center text-text-muted text-sm">
        No shops are available yet.
    </div>
@endforelse
```

**Grid**: `grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5` (replacing current `gap-4 sm:grid-cols-2 lg:grid-cols-3`).

---

## Data Flow

| View | Data Needed | Source |
|------|-------------|--------|
| `layouts/app.blade.php` | `$cartCount`, `$navCategories` | `AppServiceProvider` View::composer (existing) |
| `layouts/app.blade.php` (drawer) | `auth()->user()->name`, `email`, `created_at` | `auth()` helper inline in Blade |
| `home.blade.php` | `$categories`, `$flash`, `$products` | `HomeController` (existing, unchanged) |
| `catalog/index.blade.php` | `$products`, `$categories`, `$shops`, `$q`, `$activeCategory`, `$activeShops`, `$priceMin`, `$priceMax`, `$activeRatings` | `ProductController@index` (extended) |
| `account/profile.blade.php` | `$user`, `$isSellerProfile`, `$isRiderProfile`, `$orders`, `$addresses` | `ProfileController@show` (extended) |
| `cart/index.blade.php` | `$lines`, `$subtotal` | `CartController` (existing, unchanged) |
| `rider/register.blade.php` | `$profile`, `$errors` | `RiderRegistrationController` (existing, unchanged) |
| `shop/index.blade.php` | `$shops` (with `products_count`) | `ShopController@index` (existing, unchanged) |

---

## Components and Interfaces

### Alpine.js Components

| Component | File | Props/Args | State | Events |
|-----------|------|------------|-------|--------|
| `carousel(total, autoplay)` | `resources/js/app.js` | `total: number`, `autoplay: ms (default 5000)` | `current`, `total`, `timer`, `paused`, `touchStartX` | `next()`, `prev()`, `goTo(i)`, `pause()`, `play()`, `togglePlay()`, `onTouchStart(e)`, `onTouchEnd(e)` |
| `countdown(endTime)` | `resources/js/app.js` | `endTime: Unix timestamp` | `display: string`, `timer: interval` | `tick()` |
| Drawer (inline x-data) | `resources/views/layouts/app.blade.php` | — | `drawerOpen: bool` | `openDrawer()`, `closeDrawer()` |
| Account Dashboard (inline x-data) | `resources/views/account/profile.blade.php` | — | `activeTab: string` | tab button clicks |
| Rider Form (inline x-data) | `resources/views/rider/register.blade.php` | — | `step: 1\|2\|3`, `errors: object` | `nextStep()`, `prevStep()`, `validateStep1()`, `validateStep2()` |
| Filter Sidebar (inline x-data) | `resources/views/catalog/index.blade.php` | — | `filtersOpen: bool`, `shopSearch: string` | toggle button, shop search input |

### Blade Partials Interface

| Partial | Accepted Variables | Required | Notes |
|---------|--------------------|----------|-------|
| `catalog/partials/card.blade.php` | `$product`, `$compact`, `$heroVariant` | `$product` | `$compact` defaults to `false`; `$heroVariant` defaults to `false` |
| `catalog/partials/filter-sidebar.blade.php` | `$shops`, `$q`, `$activeCategory`, `$activeShops`, `$priceMin`, `$priceMax`, `$activeRatings` | all | Shared between desktop persistent panel and mobile bottom sheet |

### Controller Interface Changes

| Controller | Method | New Parameters Accepted | New Data Passed to View |
|------------|--------|-------------------------|-------------------------|
| `ProductController` | `index()` | `shop[]` (array of shop IDs) | `$shops`, `$activeShops`, `$priceMin`, `$priceMax`, `$activeRatings` |
| `ProfileController` | `show()` | — | `$orders` (with items), `$addresses` |

---

## Data Models

This refactor is entirely frontend — no new database migrations or Eloquent model changes are introduced. The following existing model attributes are read by the new/modified views:

### User (existing)
- `name` — displayed in profile pill, drawer avatar block, account dashboard
- `email` — email prefix used as `@handle` in drawer avatar block
- `created_at` — year extracted as "Member since YYYY"
- `role` — `UserRole` enum; used for role badge and sidebar shortcuts
- `phone` — displayed in profile panel (fallback: "Not provided")

### Product (existing)
- `name`, `price`, `compare_at_price`, `stock` — displayed in cards
- `is_flash` — determines inclusion in flash deals section
- `category` relation — used for category name in card placeholder
- `shop` relation — shop name displayed under product name

### Order (existing)
- `number`, `created_at`, `total` — displayed in My Purchase order cards
- `status` — `OrderStatus` enum; `label()` method used for status badge
- `items` relation — `count()` used for item count display

### Shop (existing)
- `name`, `city`, `tagline`, `id` — displayed in shop directory cards
- `is_active` — used to filter shop list in filter sidebar
- `products_count` — virtual count loaded via `withCount()` in `ProductController`

### RiderProfile (existing)
- `vehicle_type`, `plate_number`, `license_no`, `city`, `bio` — pre-filled into multi-step form
- `status` — displayed in current status indicator

---

## Error Handling

### Form Validation Errors
- **Login/Register (portal)**: Named error bags (`login`, `register`) are already used. The portal tab auto-opens to the correct form based on `$defaultTab` computed from `$errors->login->isNotEmpty()` or `old('_form') === 'register'`.
- **Rider multi-step form**: Client-side validation prevents step advancement for empty required fields and displays inline errors. Server-side validation errors from a failed POST are displayed in a `.alert-error` block at the top of Step 1 on the re-rendered page.
- **Checkout / Addresses**: Existing error patterns (Laravel `$errors` bag rendered inline) remain unchanged.

### Empty States
- **Cart empty**: Illustrated empty state (Requirement 7c) replaces bare text.
- **Shops directory empty**: `role="status"` block spanning all columns.
- **No products in catalog**: Existing `@empty` block preserved, styled with `.fs-card`.
- **No orders in tab**: `@empty` fallback text per tab panel in account dashboard.

### JavaScript Guard Rails
- `carousel()` guards against `total === 0` at the top of `init()`, `next()`, and `prev()` — no JS errors on an empty carousel.
- `countdown()` uses `Math.max(0, ...)` so a past `endTime` never produces a negative display value; it immediately shows "Ended".
- Drag-and-drop file zones use `@dragover.prevent` and `@drop.prevent` to stop browser default file-open behaviour.

### Theme Flash Prevention
Both `layouts/app.blade.php` and `layouts/portal.blade.php` include an inline `<script>` in `<head>` that reads `localStorage.getItem('farsell_theme')` and applies the `dark` class to `<html>` synchronously before any paint, eliminating the white-flash-then-dark flicker on page load.

---

## Testing Strategy

### Property-Based Testing
Each of the 8 Correctness Properties defined below maps to a testable assertion:

1. **Card theme** — `@darkMode` browser test: toggle `dark` class on `<html>`, assert `.fs-card` computed `background-color` changes from `rgb(255,255,255)` to `rgb(35,33,45)`.
2. **Drawer state** — Alpine component unit test: assert `drawerOpen` starts `false`, becomes `true` after `openDrawer()`, returns to `false` after `closeDrawer()` or Escape keydown.
3. **Carousel bounds** — Property test: for `total` in 1–20 and 1000 random `next()`/`prev()` calls, assert `0 ≤ current < total` always holds.
4. **activeTab domain** — Unit test: for each sidebar button click, assert `activeTab` equals the expected string literal and is never `undefined`.
5. **Filter persistence** — Snapshot test: submit the filter form with `q=foo&category=1` active, assert hidden inputs for both are present in the form HTML.
6. **Rider step bounds** — Unit test: attempt `nextStep()` when `step === 3`, assert `step` remains `3`; attempt `prevStep()` when `step === 1`, assert `step` remains `1`.
7. **Hero cardinality** — Feature test: render catalog with 5 products, assert zero `.col-span-2.row-span-2` elements; render with 6, assert exactly one.
8. **Countdown terminal** — Unit test: initialise `countdown()` with `endTime = Date.now()/1000 - 1` (already past), assert `display === 'Ended'` after first `tick()` and no further changes.

### Laravel Feature Tests
- `GET /account/profile` while authenticated returns HTTP 200 and contains the user's name.
- `GET /search?shop[]=1` returns only products from shop 1.
- `GET /cart` with an empty cart renders the empty-state SVG block and does not contain the "Cart" `<h1>`.

---

## Correctness Properties

### Property 1: Card Surface Theme Reactivity
`.fs-card` background-color equals `rgb(255 255 255)` (light) or `#23212d` (dark) depending solely on whether the `dark` class is present on `<html>`. Toggling the class propagates within one paint frame without a page reload because both values reference CSS custom properties updated on `<html>`.

**Validates: Requirements 1.2, 1.7**

### Property 2: Drawer Transform Invariant
The drawer panel has `translateX(0)` when `drawerOpen === true` and `translateX(100%)` when `drawerOpen === false`. Alpine's `x-transition` enter/leave classes enforce this; no intermediate state is reachable from user interaction.

**Validates: Requirements 2.4, 2.5**

### Property 3: Carousel Index Bounds
For any `carousel()` instance with `total > 0`, `0 ≤ current < total` at all times. `next()` applies `% total` and `prev()` applies `(current − 1 + total) % total`. When `total === 0`, all navigation methods return immediately.

**Validates: Requirements 3.2, 3.7, 3.8**

### Property 4: Account Dashboard Tab Domain
`activeTab` is always a member of `{ 'profile', 'addresses', 'all', 'pending_payment', 'paid_packed', 'assigned_in_transit', 'delivered', 'cancelled', 'review' }`. Every button that sets `activeTab` is hardcoded to one of these literals; no user input path can produce an arbitrary string.

**Validates: Requirements 4.4, 4.7**

### Property 5: Filter Form Completeness
When the catalog filter form is submitted, every currently active query parameter (`q`, `category`, `price_min`, `price_max`, `rating[]`, `shop[]`) is present in the request payload. Active values are written as hidden inputs before the filter controls in the Blade template.

**Validates: Requirements 5.10**

### Property 6: Rider Form Step Bounds
`step` is always in `{1, 2, 3}`. `nextStep()` only increments when `step < 3` and only after the current step's validation passes. `prevStep()` only decrements when `step > 1`. No code path sets `step` outside this range.

**Validates: Requirements 7.1, 7.3**

### Property 7: Hero Card Cardinality
At most one card receives `$heroVariant = true` in any product grid, and only when `$products->total() >= 6`. The flag is assigned exclusively to `$loop->first`. When total < 6, zero hero cards are rendered.

**Validates: Requirements 1.4, 1.5**

### Property 8: Countdown Terminal State
Once `countdown()` reaches `remaining === 0`, `display` is set to `'Ended'` and the interval is cleared. No subsequent `tick()` calls occur, so `display` never reverts to a time string within the same page lifetime.

**Validates: Requirements 7.2**

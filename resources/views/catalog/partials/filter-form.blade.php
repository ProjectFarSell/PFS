{{--
    Filter form body — rendered inside both the desktop aside and the mobile bottom-sheet.
    All outer variables (q, activeCategory, activeShops, shopSearch, priceMin, priceMax, shops,
    cityId, activeCityId, cities, sameDay) must be in scope.
--}}
<form method="get" action="{{ route('catalog.index') }}" class="py-4 space-y-6">

    {{-- ── Hidden inputs: preserve active filters so combining never drops one ── --}}
    @if ($q)
        <input type="hidden" name="q" value="{{ $q }}">
    @endif
    @if ($activeCategory)
        <input type="hidden" name="category" value="{{ $activeCategory }}">
    @endif
    <input type="hidden" name="sort" value="{{ request('sort', 'newest') }}">

    {{-- ── City filter ──────────────────────────────────────────────────────── --}}
    @if (!empty($cities) && $cities->isNotEmpty())
    <div>
        <p class="fs-label mb-2">City / Municipality</p>
        <select name="city_id" aria-label="City or municipality" class="fs-input w-full">
            <option value="">All cities</option>
            @foreach ($cities as $city)
                <option value="{{ $city->id }}" @selected((string) $city->id === (string) ($activeCityId ?? ''))>
                    {{ $city->name }}
                </option>
            @endforeach
        </select>
        @error('city_id')<p class="mt-1 text-xs text-error">{{ $message }}</p>@enderror
    </div>
    @endif

    {{-- ── Price range ─────────────────────────────────────────────────────── --}}
    <div x-data="{
            ceiling: @js((int) $priceCeiling),
            min: @js((float) ($priceMin !== '' ? $priceMin : 0)),
            max: @js((float) ($priceMax !== '' ? $priceMax : $priceCeiling)),
            minText: @js((string) $priceMin),
            maxText: @js((string) $priceMax),
            setMin(value) {
                this.min = Math.min(Math.max(Number(value) || 0, 0), this.max);
                this.minText = this.min > 0 ? this.min : '';
            },
            setMax(value) {
                this.max = value === '' ? this.ceiling : Math.max(Math.min(Number(value) || 0, this.ceiling), this.min);
                this.maxText = this.max < this.ceiling ? this.max : '';
            }
        }">
        <p class="fs-label mb-2">Price range</p>
        <div class="flex gap-2">
            <input type="number" name="price_min" x-model="minText" @input="setMin($event.target.value)" value="{{ $priceMin }}"
                   placeholder="Min" aria-label="Minimum price" min="0" step="0.01"
                   class="fs-input w-full">
            <input type="number" name="price_max" x-model="maxText" @input="setMax($event.target.value)" value="{{ $priceMax }}"
                   placeholder="Max" aria-label="Maximum price" min="0" step="0.01"
                   class="fs-input w-full">
        </div>
        <div class="fs-range-control mt-3" aria-label="Adjust price range">
            <div class="fs-range-track"><span :style="'left:' + (min / ceiling * 100) + '%;width:' + ((max - min) / ceiling * 100) + '%'" class="fs-range-selected"></span></div>
            <input type="range" min="0" max="{{ $priceCeiling }}" step="100" x-model.number="min" @input="setMin($event.target.value)" aria-label="Minimum price slider" class="fs-range fs-range-min">
            <input type="range" min="0" max="{{ $priceCeiling }}" step="100" x-model.number="max" @input="setMax($event.target.value)" aria-label="Maximum price slider" class="fs-range fs-range-max">
        </div>
        <div class="mt-1 flex justify-between text-[11px] text-text-muted">
            <span>PHP <span x-text="Number(min).toLocaleString()"></span></span>
            <span><span x-show="max < ceiling">PHP </span><span x-text="max < ceiling ? Number(max).toLocaleString() : 'No max'"></span></span>
        </div>
        @error('price_min')<p class="mt-1 text-xs text-error">{{ $message }}</p>@enderror
        @error('price_max')<p class="mt-1 text-xs text-error">{{ $message }}</p>@enderror
    </div>

    {{-- ── Shop / brand filter ─────────────────────────────────────────────── --}}
    @if ($shops->isNotEmpty())
    <div x-data="{ shopSearch: @js($shopSearch ?? '') }">
        <p class="fs-label mb-2">Shop</p>
        <input type="search" name="shop_search" x-model="shopSearch" value="{{ $shopSearch ?? '' }}" placeholder="Search shops…"
               class="fs-input mb-2" autocomplete="off">
        <div class="space-y-0.5 max-h-40 overflow-y-auto">
            @foreach ($shops as $shop)
            <label x-show="shopSearch === '' || @js(mb_strtolower($shop->name)).includes(shopSearch.toLowerCase())"
                   class="flex items-center justify-between gap-2 py-1 cursor-pointer select-none text-sm"
                   style="color:rgb(var(--color-text-base));">
                <span class="flex items-center gap-2 min-w-0">
                    <input type="checkbox" name="shop[]" value="{{ $shop->id }}"
                           @checked(in_array((string) $shop->id, (array) $activeShops))
                           class="rounded shrink-0"
                           style="accent-color:rgb(var(--color-accent));">
                    <span class="truncate">{{ $shop->name }}</span>
                </span>
                <span class="shrink-0 text-xs" style="color:rgb(var(--color-text-muted));">
                    {{ $shop->products_count ?? 0 }}
                </span>
            </label>
            @endforeach
        </div>
    </div>
    @endif

    {{-- ── Delivery options ────────────────────────────────────────────────── --}}
    <div>
        <p class="fs-label mb-2">Delivery</p>

        {{-- Same-day delivery: enabled, filters by buyer's city --}}
        <label class="flex items-center justify-between py-1 cursor-pointer select-none"
               style="color:rgb(var(--color-text-base));">
            <span class="text-sm">Same-day delivery</span>
            <input type="checkbox" name="same_day" value="1"
                   @checked($sameDay ?? false)
                   class="rounded"
                   style="accent-color:rgb(var(--color-accent));">
        </label>

        {{-- FarSell Guaranteed: coming soon, remains disabled --}}
        <label class="flex items-center justify-between py-1 opacity-50 cursor-not-allowed select-none"
               title="Coming soon">
            <span class="text-sm" style="color:rgb(var(--color-text-muted));">FarSell Guaranteed</span>
            <input type="checkbox" disabled
                   class="rounded"
                   style="accent-color:rgb(var(--color-accent));">
        </label>
    </div>

    <button type="submit" class="btn-accent w-full">Apply filters</button>

    @if ($q || $activeCategory || $priceMin !== '' || $priceMax !== '' || !empty($activeShops) || $shopSearch || $activeCityId)
        <a href="{{ route('catalog.index') }}{{ $activeCityId ? '?clear_city=1' : '' }}"
           class="block text-center text-sm transition-colors hover:underline"
           style="color:rgb(var(--color-text-muted));">
            Clear all filters
        </a>
    @endif
</form>

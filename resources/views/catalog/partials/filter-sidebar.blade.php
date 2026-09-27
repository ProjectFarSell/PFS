{{--
    Filter sidebar partial — shared between desktop (persistent aside) and mobile (bottom sheet).
    Parent view must provide x-data="{ filtersOpen: false }".
    All filter variables must exist: $q, $activeCategory, $activeShops, $priceMin, $priceMax, $activeRatings, $shops.
--}}

{{-- Desktop: persistent left panel --}}
<aside class="hidden lg:block w-60 shrink-0">
    @include('catalog.partials.filter-form')
</aside>

{{-- Mobile: bottom-sheet overlay --}}
<div x-show="filtersOpen"
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="translate-y-full"
     x-transition:enter-end="translate-y-0"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="translate-y-0"
     x-transition:leave-end="translate-y-full"
     class="fixed inset-x-0 bottom-0 z-50 rounded-t-2xl shadow-card-md lg:hidden
            overflow-y-auto"
     style="max-height:80vh; background-color:rgb(var(--color-surface)); display:none;">

    <div class="sticky top-0 flex items-center justify-between px-5 py-3.5 border-b"
         style="background-color:rgb(var(--color-surface)); border-color:rgb(var(--color-surface-border)/0.5);">
        <p class="font-semibold text-sm" style="color:rgb(var(--color-text-base));">Filters</p>
        <button @click="filtersOpen = false" class="btn-ghost p-1.5" aria-label="Close filters">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>

    <div class="px-5 pb-8">
        @include('catalog.partials.filter-form')
    </div>
</div>

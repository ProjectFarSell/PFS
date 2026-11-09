{{--
    One filter form serves the persistent desktop sidebar and the mobile bottom sheet.
    The parent owns the mobile open and close state.
    All filter variables must exist: $q, $activeCategory, $activeShops, $priceMin, $priceMax, $shops.
--}}

<div x-show="filtersOpen" x-cloak @click="closeFilters()" class="fixed inset-0 z-50 bg-black/50 backdrop-blur-sm lg:hidden" style="display:none;"></div>
<aside id="mobile-filters" aria-labelledby="mobile-filters-title"
       :class="{ hidden: !filtersOpen }"
       :role="filtersOpen ? 'dialog' : null"
       :aria-modal="filtersOpen ? 'true' : null"
       @keydown.tab="trapFilterFocus($event)"
       class="fixed inset-x-0 bottom-0 z-[60] hidden max-h-[80vh] overflow-y-auto rounded-t-2xl bg-surface shadow-card-md
              lg:static lg:z-auto lg:block lg:max-h-none lg:w-60 lg:shrink-0 lg:overflow-visible lg:rounded-none lg:bg-transparent lg:shadow-none">

    <div class="sticky top-0 flex items-center justify-between border-b border-surface-border/50 bg-surface px-5 py-3.5 lg:sr-only">
        <p id="mobile-filters-title" class="font-semibold text-sm" style="color:rgb(var(--color-text-base));">Filters</p>
        <x-ui.icon-button variant="tertiary" label="Close filters" type="button" x-ref="closeFilters" @click="closeFilters()" class="min-h-11 min-w-11 lg:hidden">
            <x-ui.icon name="close" size="sm" />
        </x-ui.icon-button>
    </div>

    <div class="px-5 pb-8 lg:p-0">
        @include('catalog.partials.filter-form')
    </div>
</aside>

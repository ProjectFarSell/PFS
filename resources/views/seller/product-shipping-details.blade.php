<fieldset class="rounded-xl border border-surface-border p-4" x-data="{ setPreset(values) { for (const [key, value] of Object.entries(values)) { this.$refs[key].value = value; this.$refs[key].dispatchEvent(new Event('input', { bubbles: true })); } } }">
    <legend class="px-1 text-sm font-semibold">Shipping details (optional)</legend>
    <p class="mb-3 text-xs text-text-muted">Enter the packaged measurements for one item. Units are shown for you; enter numbers only. These details do not change delivery prices or guarantee courier acceptance.</p>
    <input type="hidden" name="shipping_details_present" value="1">
    <div class="mb-3 flex flex-wrap gap-2" aria-label="Package presets">
        @foreach(\App\Enums\PackagePreset::cases() as $preset)
            <x-ui.button type="button" variant="secondary" size="sm" @click='setPreset(@json($preset->measurements()))'>{{ $preset->label() }}</x-ui.button>
        @endforeach
    </div>
    <p class="mb-3 text-xs text-text-muted">Presets are editable estimates. Check the actual packed measurements and weight; handling flags stay unchanged.</p>
    <div class="grid gap-4 sm:grid-cols-2">
        @foreach(['length_cm' => 'Length (cm)', 'width_cm' => 'Width (cm)', 'height_cm' => 'Height (cm)', 'weight_kg' => 'Weight (kg)'] as $field => $label)
            <label class="block text-sm">{{ $label }} <span class="text-xs text-text-muted">optional</span>
                <input type="number" name="{{ $field }}" x-ref="{{ $field }}" inputmode="decimal"
                    min="{{ $field === 'weight_kg' ? '0.001' : '0.01' }}"
                    max="{{ $field === 'weight_kg' ? \App\Support\ProductShippingDetails::MAX_WEIGHT_KG : \App\Support\ProductShippingDetails::MAX_DIMENSION_CM }}"
                    step="{{ $field === 'weight_kg' ? '0.001' : '0.01' }}"
                    value="{{ old($field, $product->{$field}) }}" class="mt-1 w-full rounded-lg" @error($field) aria-invalid="true" @enderror>
            </label>
        @endforeach
    </div>
    <fieldset class="mt-4">
        <legend class="text-sm font-medium">Handling instructions (optional)</legend>
        @php($selectedFlags = old('handling_flags', session()->hasOldInput('shipping_details_present') ? [] : ($product->handling_flags ?? [])))
        <div class="mt-2 grid gap-2 sm:grid-cols-2">
            @foreach(\App\Enums\HandlingFlag::cases() as $flag)
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="handling_flags[]" value="{{ $flag->value }}" @checked(is_array($selectedFlags) && in_array($flag->value, $selectedFlags, true)) class="rounded">{{ $flag->label() }}</label>
            @endforeach
        </div>
    </fieldset>
</fieldset>

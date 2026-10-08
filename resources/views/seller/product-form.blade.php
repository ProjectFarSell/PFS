@extends('layouts.portal')
@section('title', ($product->exists ? 'Edit listing' : 'Add product').' · FarSell')
@section('content')
    @php
        $initialVariants = collect(old('variants', $product->variants->map(fn($variant) => [
            'id' => $variant->id,
            'options_text' => collect($variant->options ?: array_filter([$variant->option_name => $variant->option_value]))->map(fn($value, $name) => $name.': '.$value)->join(', '),
            'price_override' => $variant->price_override,
            'stock' => $variant->stock,
            'is_active' => $variant->is_active,
        ])->values()->all()))->map(function ($variant) {
            $variant['is_active'] = filter_var($variant['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN);
            return $variant;
        })->values()->all();
        $hasVariants = (bool) old('has_variants', $product->has_variants);
    @endphp
    <div class="mx-auto max-w-2xl">
        <a href="{{ route('seller.dashboard') }}" class="text-sm text-accent underline">Back to Seller Dashboard</a>
        <section class="fs-card mt-4 p-5 sm:p-7">
            <h1 class="text-2xl font-semibold">{{ $product->exists ? 'Edit listing' : 'Add product' }}</h1>
            <p class="mt-2 text-sm text-text-muted">Create a listing with optional combinations such as size and color. Stock and optional pricing are managed for each combination.</p>
            @if($errors->any())<ul role="alert" class="mt-4 list-disc pl-5 text-sm text-error">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif
            @if($errors->has('version'))<p class="mt-3 text-sm font-semibold">Current listing values have been reloaded below. Review stock before trying again.</p>@endif
            <form method="post" action="{{ $product->exists ? route('seller.products.update', $product) : route('seller.products.store') }}" enctype="multipart/form-data" class="mt-5 space-y-4" x-data="{ hasVariants: @js($hasVariants), variantRows: @js($initialVariants) }">
                @csrf
                @if($product->exists)
                    @method('PUT')
                    <input type="hidden" name="version" value="{{ old('version', $product->editVersion()) }}">
                @endif
                <label class="block text-sm">Product name<input name="name" required maxlength="150" value="{{ old('name', $product->name) }}" class="mt-1 w-full rounded-lg"></label>
                <label class="block text-sm">Category<select name="category_id" required class="mt-1 w-full rounded-lg"><option value="">Choose a category</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected((string)old('category_id', $product->category_id) === (string)$category->id)>{{ $category->name }}</option>@endforeach</select></label>
                @if($categories->isEmpty())<p class="text-sm text-error">No categories are configured. Ask an administrator to set up categories first.</p>@endif
                <label class="block text-sm">Description<textarea name="description" required maxlength="5000" rows="5" class="mt-1 w-full rounded-lg">{{ old('description', $product->description) }}</textarea></label>
                <input type="hidden" name="has_variants" :value="hasVariants ? 1 : 0">
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" x-model="hasVariants" class="rounded"> This product has options (size, color, etc.)</label>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <label class="block text-sm">Price (PHP)<input type="number" name="price" min="0.01" max="99999999.99" step="0.01" required value="{{ old('price', $product->price) }}" class="mt-1 w-full rounded-lg"></label>
                    <label class="block text-sm" x-show="!hasVariants">Available units<input type="number" name="stock" min="0" max="1000000" step="1" required value="{{ old('stock', $product->stock ?? 0) }}" x-bind:disabled="hasVariants" class="mt-1 w-full rounded-lg"></label>
                    <input type="hidden" name="stock" value="0" x-bind:disabled="!hasVariants">
                </div>
                <p class="text-xs text-text-muted" x-show="!hasVariants">Available units exclude stock already reserved for orders. Enter only units still available to sell.</p>
                <section x-show="hasVariants" class="rounded-xl border border-surface-border p-4">
                    <div class="flex items-center justify-between gap-3"><div><h2 class="font-semibold">Options and stock</h2><p class="mt-1 text-xs text-text-muted">One row per combination. Example: Size: M, Color: Red</p></div><button type="button" class="btn-outline" @click="variantRows.push({ options_text: '', price_override: '', stock: 0, is_active: true })">Add option</button></div>
                    <div class="mt-3 space-y-3">
                        <template x-for="(variant, index) in variantRows" :key="variant.id ?? index">
                            <div class="grid gap-2 rounded-lg bg-surface-muted p-3 sm:grid-cols-2">
                                <input type="hidden" :name="`variants[${index}][id]`" x-model="variant.id" x-bind:disabled="!hasVariants">
                                <label class="text-xs sm:col-span-2">Option combination<input required :name="`variants[${index}][options_text]`" x-model="variant.options_text" placeholder="Size: M, Color: Red" class="mt-1 w-full rounded-lg"></label>
                                <label class="text-xs">Price override (optional)<input type="number" min="0.01" step="0.01" :name="`variants[${index}][price_override]`" x-model="variant.price_override" class="mt-1 w-full rounded-lg" placeholder="Use base price"></label>
                                <label class="text-xs">Available stock<input type="number" min="0" max="1000000" required :name="`variants[${index}][stock]`" x-model="variant.stock" class="mt-1 w-full rounded-lg"></label>
                                <input type="hidden" value="0" :name="`variants[${index}][is_active]`">
                                <label class="flex items-center gap-2 text-xs"><input type="checkbox" value="1" :name="`variants[${index}][is_active]`" x-model="variant.is_active" class="rounded"> Available to buyers</label>
                                <button type="button" class="justify-self-end text-xs text-error underline" @click="variantRows.splice(index, 1)">Remove option</button>
                            </div>
                        </template>
                    </div>
                </section>
                @if($product->image_path)<img src="{{ asset('storage/'.$product->image_path) }}" alt="{{ $product->name }}" class="h-40 w-40 rounded-lg object-cover">@endif
                <label class="block text-sm">Product photo (optional)<input type="file" name="image" accept="image/jpeg,image/png,image/webp" class="mt-2 block w-full text-sm"></label>
                <p class="text-xs text-text-muted">JPG, PNG or WebP, up to 4 MB. Uploading a new photo replaces the displayed image.</p>
                <label class="block text-sm">Listing visibility<select name="is_active" class="mt-1 w-full rounded-lg"><option value="0" @selected((string)old('is_active', (int)$product->is_active) === '0')>Unpublished / draft</option><option value="1" @selected((string)old('is_active', (int)$product->is_active) === '1')>Published — available to buyers</option></select></label>
                <p class="text-xs text-text-muted">Unpublish to stop new purchases. Listings are kept rather than permanently deleted so existing orders remain linked to your shop.</p>
                <div class="flex flex-wrap gap-3"><button class="btn-accent" @disabled($categories->isEmpty())>{{ $product->exists ? 'Save listing' : 'Create listing' }}</button><a href="{{ route('seller.dashboard') }}" class="btn-outline">Cancel</a></div>
            </form>
        </section>
    </div>
@endsection

@extends('layouts.app')
@section('title', ($product->exists ? 'Edit listing' : 'Add product').' · FarSell')
@section('content')
    <div class="mx-auto max-w-2xl">
        <a href="{{ route('seller.dashboard') }}" class="text-sm text-accent underline">Back to Seller Dashboard</a>
        <section class="fs-card mt-4 p-5 sm:p-7">
            <h1 class="text-2xl font-semibold">{{ $product->exists ? 'Edit listing' : 'Add product' }}</h1>
            <p class="mt-2 text-sm text-text-muted">List a single product with product-level stock. Publish it to appear in your storefront and the marketplace. Product variants and delivery fulfillment are not managed here.</p>
            @if($errors->any())<ul role="alert" class="mt-4 list-disc pl-5 text-sm text-error">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif
            @if($errors->has('version'))<p class="mt-3 text-sm font-semibold">Current listing values have been reloaded below. Review stock before trying again.</p>@endif
            <form method="post" action="{{ $product->exists ? route('seller.products.update', $product) : route('seller.products.store') }}" enctype="multipart/form-data" class="mt-5 space-y-4">
                @csrf
                @if($product->exists)
                    @method('PUT')
                    <input type="hidden" name="version" value="{{ old('version', $product->editVersion()) }}">
                @endif
                <label class="block text-sm">Product name<input name="name" required maxlength="150" value="{{ old('name', $product->name) }}" class="mt-1 w-full rounded-lg"></label>
                <label class="block text-sm">Category<select name="category_id" required class="mt-1 w-full rounded-lg"><option value="">Choose a category</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected((string)old('category_id', $product->category_id) === (string)$category->id)>{{ $category->name }}</option>@endforeach</select></label>
                @if($categories->isEmpty())<p class="text-sm text-error">No categories are configured. Ask an administrator to set up categories first.</p>@endif
                <label class="block text-sm">Description<textarea name="description" required maxlength="5000" rows="5" class="mt-1 w-full rounded-lg">{{ old('description', $product->description) }}</textarea></label>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <label class="block text-sm">Price (PHP)<input type="number" name="price" min="0.01" max="99999999.99" step="0.01" required value="{{ old('price', $product->price) }}" class="mt-1 w-full rounded-lg"></label>
                    <label class="block text-sm">Available units<input type="number" name="stock" min="0" max="1000000" step="1" required value="{{ old('stock', $product->stock ?? 0) }}" class="mt-1 w-full rounded-lg"></label>
                </div>
                <p class="text-xs text-text-muted">Available units exclude stock already reserved for orders. Enter only units still available to sell. If a purchase changes stock while you edit, reload and review before saving.</p>
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

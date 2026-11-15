<section class="fs-card mx-auto mt-5 max-w-2xl space-y-4 p-5">
    <h2 class="text-lg font-semibold">Product photos</h2>
    <p class="text-sm text-text-muted">Up to five photos in total. Choose the primary image shown on product cards.</p>
    @if($product->admin_hidden_at)<p role="status" class="text-sm text-error">An administrator has hidden this listing: {{ $product->admin_hide_reason }}</p>@endif
    <div class="grid gap-4 sm:grid-cols-2">
        @foreach($product->galleryImages() as $image)
            <article class="rounded-lg border p-3">
                <x-product-image :src="$image['url']" :alt="$image['alt_text']" width="160" height="160" class="h-40 w-full rounded-lg object-cover" />
                @if($image['is_primary'])<p class="mt-2 text-sm font-semibold">Primary photo</p>@endif
                @if($image['id'] && !$image['is_primary'])
                    <form class="mt-2" method="post" action="{{ route('seller.products.images.primary', [$product, $image['id']]) }}">@csrf @method('PUT')<button class="text-sm underline" type="submit">Set as primary</button></form>
                @endif
                <form class="mt-2" method="post" action="{{ $image['id'] ? route('seller.products.images.destroy', [$product, $image['id']]) : route('seller.products.images.destroy-primary', $product) }}">@csrf @method('DELETE')<button class="text-sm text-error underline" type="submit">Remove photo</button></form>
            </article>
        @endforeach
    </div>
    <form class="space-y-3" method="post" enctype="multipart/form-data" action="{{ route('seller.products.images.store', $product) }}">@csrf
        <label class="block text-sm">Add photos<input class="mt-2 block w-full" type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple required></label>
        <p class="text-xs text-text-muted">JPG, PNG or WebP, up to 4 MB per file.</p>
        <button class="rounded-lg bg-accent px-4 py-2 text-white" type="submit">Upload photos</button>
    </form>
</section>

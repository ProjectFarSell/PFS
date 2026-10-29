<section id="product-reviews" class="fs-card mt-8 p-5 sm:p-6" aria-labelledby="product-reviews-title">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-surface-border pb-4">
        <h2 id="product-reviews-title" class="text-lg font-semibold">Buyer reviews</h2>
        <p class="text-sm"><span class="font-semibold text-amber-500">{{ $reviewAverage ? number_format((float) $reviewAverage, 1).' / 5' : 'No ratings yet' }}</span><span class="ml-2 text-text-muted">{{ $reviews->total() }} {{ $reviews->total() === 1 ? 'review' : 'reviews' }}</span></p>
    </div>
    <div class="divide-y divide-surface-border">
        @forelse($reviews as $review)
            <article class="py-5">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h3 class="text-sm font-semibold">{{ $review->authorName() }}</h3>
                    <span class="badge badge-neutral">Verified purchase</span>
                </div>
                <p class="mt-2 text-amber-500" aria-label="{{ $review->rating }} out of 5 stars"><span aria-hidden="true">{{ str_repeat('★', $review->rating) }}{{ str_repeat('☆', 5 - $review->rating) }}</span></p>
                <p class="mt-1 text-xs text-text-muted"><time datetime="{{ $review->created_at->toIso8601String() }}">{{ $review->created_at->format('M j, Y') }}</time>@if($review->orderItem?->variant_options) · {{ collect($review->orderItem->variant_options)->map(fn ($value, $name) => $name.': '.$value)->join(' · ') }}@endif</p>
                @if($review->feedback)<p class="mt-3 whitespace-pre-line break-words text-sm">{{ $review->feedback }}</p>@endif
                <x-review-media :media="$review->media" />
            </article>
        @empty
            <p class="py-6 text-sm text-text-muted">No reviews yet. Buyers can share their experience after confirming receipt of their order.</p>
        @endforelse
    </div>
    @if($reviews->hasPages())<div class="mt-3">{{ $reviews->fragment('product-reviews')->links() }}</div>@endif
</section>

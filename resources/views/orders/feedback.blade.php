@extends('layouts.app')

@section('title', 'Rate your products · FarSell')

@section('content')
    <div class="mx-auto max-w-3xl">
        <a href="{{ route('orders.show', $fulfillment->order_id) }}" class="text-sm text-accent underline">Back to order {{ $fulfillment->order->number }}</a>
        <div class="my-5">
            <p class="text-xs font-semibold uppercase tracking-widest text-accent">Verified purchase</p>
            <h1 class="mt-2 text-2xl font-bold">Rate your products</h1>
            <p class="mt-2 text-sm text-text-muted">Share your experience with items from {{ $fulfillment->shop_name }}.</p>
            <p class="mt-1 text-xs text-text-muted">Attachments across all items: up to {{ $mediaLimits['totalLabel'] }} and {{ $mediaLimits['maxFiles'] }} files per submission.</p>
        </div>

        @if($errors->any())
            <div class="alert-error mb-5" role="alert"><p class="font-semibold">Please check your feedback.</p><ul class="mt-2 list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif

        <form method="post" action="{{ route('orders.feedback.store', $fulfillment) }}" enctype="multipart/form-data" class="space-y-5" x-data="{ submitting: false }" @submit="submitting = true">
            @csrf
            @foreach($fulfillment->items as $item)
                <section class="fs-card overflow-hidden">
                    <div class="flex items-center gap-4 border-b border-surface-border p-5">
                        @if($item->product?->imageUrl())<img src="{{ $item->product->imageUrl() }}" alt="" class="h-16 w-16 rounded-lg border border-surface-border bg-surface object-contain">@endif
                        <div class="min-w-0">
                            <h2 class="font-semibold">{{ $item->name }}</h2>
                            @if($item->variant_options)<p class="mt-1 text-sm text-text-muted">{{ collect($item->variant_options)->map(fn ($value, $name) => $name.': '.$value)->join(' · ') }}</p>@endif
                            <p class="mt-1 text-xs text-text-muted">SKU: {{ $item->product_sku ?? 'Unavailable' }} · Quantity {{ $item->qty }}</p>
                        </div>
                    </div>
                    @if($item->review)
                        <div class="p-5">
                            <p class="font-semibold text-amber-500">{{ $item->review->rating }} / 5 stars</p>
                            @if($item->review->feedback)<p class="mt-3 whitespace-pre-line text-sm">{{ $item->review->feedback }}</p>@endif
                            <p class="mt-2 text-xs text-text-muted">{{ $item->review->is_anonymous ? 'Posted anonymously' : 'Posted with your name' }} · {{ $item->review->created_at->format('M j, Y') }}</p>
                            <x-review-media :media="$item->review->media" />
                        </div>
                    @else
                        <div class="space-y-5 p-5" x-data="productReviewForm(@js(old('reviews.'.$item->id.'.feedback', '')), @js($mediaLimits))">
                            <x-star-rating :name="'reviews['.$item->id.'][rating]'" label="Rate this product" :value="old('reviews.'.$item->id.'.rating', 0)" />
                            <div>
                                <p class="mb-2 text-sm font-semibold">Add photos and a video <span class="font-normal text-text-muted">(optional)</span></p>
                                <label class="flex cursor-pointer flex-col items-center gap-2 rounded-xl border-2 border-dashed border-surface-border px-4 py-7 text-center hover:border-accent focus-within:border-accent">
                                    <svg class="h-8 w-8 text-accent" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M4 6h4l2-3h4l2 3h4v14H4Z"/><circle cx="12" cy="12" r="4"/></svg>
                                    <span class="text-sm font-medium">Choose photos or video</span>
                                    <span class="text-xs text-text-muted">Up to 3 photos ({{ $mediaLimits['photoLabel'] }} each) and 1 video ({{ $mediaLimits['videoLabel'] }}). JPG, PNG, WebP, MP4 or WebM.</span>
                                    <input type="file" name="reviews[{{ $item->id }}][media][]" accept="image/jpeg,image/png,image/webp,video/mp4,video/webm" multiple x-ref="media" @change="addFiles($event)" class="sr-only">
                                </label>
                                <p x-show="mediaError" x-text="mediaError" x-cloak role="alert" class="mt-2 text-sm text-error"></p>
                                <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4" x-show="attachments.length" x-cloak>
                                    <template x-for="(attachment, index) in attachments" :key="attachment.url">
                                        <div class="rounded-lg border border-surface-border p-2">
                                            <template x-if="attachment.video"><video :src="attachment.url" controls preload="metadata" class="h-24 w-full rounded object-contain" aria-label="Selected review video"></video></template>
                                            <template x-if="!attachment.video"><img :src="attachment.url" alt="Selected review photo" class="h-24 w-full rounded object-cover"></template>
                                            <p class="mt-1 truncate text-xs text-text-muted" x-text="attachment.file.name"></p>
                                            <button type="button" @click="removeFile(index)" class="mt-1 text-xs text-error underline">Remove</button>
                                        </div>
                                    </template>
                                </div>
                            </div>
                            <div>
                                <label for="product-feedback-{{ $item->id }}" class="fs-label">Your review <span class="font-normal text-text-muted">(optional)</span></label>
                                <textarea id="product-feedback-{{ $item->id }}" name="reviews[{{ $item->id }}][feedback]" x-model="text" maxlength="2000" rows="5" class="fs-input" placeholder="How was the quality, condition, and value? Did the product match the listing?">{{ old('reviews.'.$item->id.'.feedback') }}</textarea>
                                <p class="mt-1 text-right text-xs text-text-muted" x-text="text.length + ' / 2000 characters'"></p>
                            </div>
                            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="reviews[{{ $item->id }}][is_anonymous]" value="1" @checked(old('reviews.'.$item->id.'.is_anonymous')) class="rounded border-surface-border text-accent focus:ring-accent"> Review anonymously</label>
                            <p class="text-xs text-text-muted">Your product review and attachments will be visible to other shoppers. Anonymous reviews hide your name from shoppers.</p>
                        </div>
                    @endif
                </section>
            @endforeach

            <section class="fs-card space-y-6 p-5">
                <h2 class="text-lg font-semibold">Your shopping and delivery experience</h2>
                @if($fulfillment->feedback)
                    <p class="text-sm">Seller service: <strong>{{ $fulfillment->feedback->seller_service_rating }} / 5</strong></p>
                    <p class="text-sm">Delivery speed: <strong>{{ $fulfillment->feedback->delivery_speed_rating }} / 5</strong></p>
                @else
                    <x-star-rating name="seller_service_rating" label="Seller service" :value="old('seller_service_rating', 0)" />
                    <x-star-rating name="delivery_speed_rating" label="Delivery speed" :value="old('delivery_speed_rating', 0)" />
                @endif

                @if($fulfillment->rider_id)
                    <div class="space-y-4 border-t border-surface-border pt-5">
                        <p class="text-sm text-text-muted">Rider: {{ $fulfillment->rider?->user?->name ?? 'Your delivery rider' }}</p>
                        @if($fulfillment->riderRating)
                            <p class="text-sm">Rider service: <strong>{{ $fulfillment->riderRating->rating }} / 5</strong></p>
                            @if($fulfillment->riderRating->tags)<p class="text-sm text-text-muted">{{ implode(' · ', $fulfillment->riderRating->tags) }}</p>@endif
                            @if($fulfillment->riderRating->feedback)<p class="whitespace-pre-line text-sm">{{ $fulfillment->riderRating->feedback }}</p>@endif
                        @else
                            <x-star-rating name="rider_rating" label="Rider service" :value="old('rider_rating', 0)" />
                            <fieldset><legend class="mb-3 text-sm font-medium">What went well? <span class="text-text-muted">(optional)</span></legend>
                                <div class="flex flex-wrap gap-2">
                                    @foreach($riderTags as $tag)
                                        <label class="cursor-pointer"><input type="checkbox" name="rider_tags[]" value="{{ $tag }}" @checked(in_array($tag, (array) old('rider_tags', []))) class="peer sr-only"><span class="inline-block rounded-lg border border-surface-border bg-surface-muted px-3 py-2 text-sm peer-checked:border-accent peer-checked:bg-accent/10 peer-checked:text-accent peer-focus-visible:outline peer-focus-visible:outline-2 peer-focus-visible:outline-accent">{{ $tag }}</span></label>
                                    @endforeach
                                </div>
                            </fieldset>
                            <div><label for="rider-feedback" class="fs-label">Tell us more about the delivery (optional)</label><textarea id="rider-feedback" name="rider_feedback" maxlength="1000" rows="3" class="fs-input" placeholder="Share any other delivery feedback.">{{ old('rider_feedback') }}</textarea></div>
                            <p class="text-xs text-text-muted">Your delivery feedback helps your rider and our team improve the service.</p>
                        @endif
                    </div>
                @endif
            </section>
            @if($hasPendingFeedback)
                <div class="sticky bottom-0 z-30 rounded-xl border border-surface-border bg-surface p-4 shadow-lg">
                    <button type="submit" class="btn-accent w-full justify-center disabled:opacity-50" :disabled="submitting" x-text="submitting ? 'Saving your feedback…' : 'Submit feedback'">Submit feedback</button>
                </div>
            @else
                <p class="rounded-xl border border-success/30 bg-success/10 p-4 text-sm text-success">Thank you! You have reviewed all items in this shipment.</p>
            @endif
        </form>
    </div>
@endsection

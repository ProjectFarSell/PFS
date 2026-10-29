@props(['media'])

@if($media->isNotEmpty())
    <div class="mt-3 flex flex-wrap gap-3">
        @foreach($media as $attachment)
            @if($attachment->isVideo())
                <video controls preload="metadata" class="h-36 w-48 rounded-xl border border-surface-border bg-black object-contain" aria-label="Buyer review video"><source src="{{ route('reviews.media', $attachment) }}" type="{{ $attachment->mime_type }}">Your browser does not support this video.</video>
            @else
                <a href="{{ route('reviews.media', $attachment) }}" target="_blank" rel="noopener" aria-label="Open buyer review photo">
                    <img src="{{ route('reviews.media', $attachment) }}" alt="Photo attached to a verified purchase review" loading="lazy" class="h-28 w-28 rounded-xl border border-surface-border object-cover">
                </a>
            @endif
        @endforeach
    </div>
@endif

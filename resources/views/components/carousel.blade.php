@props(['slides' => [], 'autoplay' => 5000, 'label' => 'Featured offers'])

@if (count($slides) > 0)
    <section
        {{ $attributes->class(['relative rounded-2xl']) }}
        x-data="carousel({{ count($slides) }}, {{ (int) $autoplay }})"
        role="region"
        aria-roledescription="carousel"
        aria-label="{{ $label }}"
        tabindex="0"
        @mouseenter="pause()"
        @mouseleave="play()"
        @focusin="pause('focused')"
        @focusout="onFocusOut()"
        @keydown="onKeydown($event)"
        @click.capture="onClick($event)"
        style="background-color: rgb(var(--color-surface-muted)); color: rgb(var(--color-text-base));"
    >
        <div
            @class(['overflow-hidden rounded-t-2xl', 'rounded-b-2xl' => count($slides) === 1])
            style="touch-action: pan-y pinch-zoom;"
            @touchstart.passive="onTouchStart($event)"
            @touchmove.passive="onTouchMove($event)"
            @touchend.passive="onTouchEnd($event)"
            @touchcancel.passive="onTouchCancel()"
        >
            <div
                x-ref="track"
                class="flex transition-transform duration-500 motion-reduce:transition-none"
                style="transition-timing-function: cubic-bezier(0.16, 1, 0.3, 1);"
                :style="{ transform: 'translate3d(-' + (current * 100) + '%, 0, 0)', transitionDuration: reducedMotion ? '0ms' : '500ms' }"
            >
                @foreach ($slides as $slide)
                    <article
                        class="relative flex min-h-[340px] w-full shrink-0 flex-col justify-center p-6 sm:p-10 lg:min-h-[400px] lg:p-12"
                        style="background: linear-gradient(120deg, rgb(var(--color-accent) / 0.15), rgb(var(--color-surface-muted)));"
                        role="group"
                        aria-roledescription="slide"
                        aria-label="{{ $loop->iteration }} of {{ count($slides) }}: {{ $slide['title'] }}"
                        aria-hidden="{{ $loop->first ? 'false' : 'true' }}"
                        :aria-hidden="!isActive({{ $loop->index }})"
                        @if (! $loop->first) inert @endif
                        :inert="!isActive({{ $loop->index }})"
                    >
                        <div class="pointer-events-none absolute right-8 top-1/2 hidden h-60 w-80 -translate-y-1/2 text-accent/60 xl:block" aria-hidden="true"><x-discovery-art :kind="$slide['art'] ?? 'cards'" /></div>
                        @if (! empty($slide['eyebrow']))
                            <p class="relative mb-3 text-xs font-semibold uppercase tracking-widest">{{ $slide['eyebrow'] }}</p>
                        @endif
                        @if ($loop->first)
                            <h1 class="relative max-w-2xl text-3xl font-bold leading-tight tracking-tight sm:text-4xl lg:text-5xl">{{ $slide['title'] }}</h1>
                        @else
                            <h2 class="relative max-w-2xl text-3xl font-bold leading-tight tracking-tight sm:text-4xl lg:text-5xl">{{ $slide['title'] }}</h2>
                        @endif
                        <p class="mt-4 max-w-xl text-base sm:text-lg" style="color: rgb(var(--color-text-muted));">{{ $slide['subtitle'] }}</p>
                        <div class="mt-6">
                            <a
                                href="{{ $slide['cta_href'] }}"
                                class="inline-flex min-h-[44px] items-center justify-center rounded-full px-6 py-3 text-sm font-semibold transition-opacity hover:opacity-90 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-accent"
                                style="background-color: rgb(var(--color-accent)); color: rgb(var(--color-accent-text));"
                                tabindex="{{ $loop->first ? '0' : '-1' }}"
                                :tabindex="isActive({{ $loop->index }}) ? 0 : -1"
                            >{{ $slide['cta_text'] }}</a>
                        </div>
                    </article>
                @endforeach
            </div>
        </div>

        @if (count($slides) > 1)
            <div class="flex flex-wrap items-center justify-between gap-2 rounded-b-2xl border-t p-3 sm:px-6" style="background-color: rgb(var(--color-surface)); border-color: rgb(var(--color-surface-border));">
                <x-ui.button variant="tertiary"
                    type="button"
                    @click="togglePlay()"
                    ::aria-pressed="userPaused"
                    ::disabled="reducedMotion || {{ (int) $autoplay }} <= 0"
                    class="inline-flex h-11 w-11 items-center justify-center rounded-full text-sm font-semibold hover:bg-surface-muted disabled:opacity-60"
                    ::aria-label="userPaused ? 'Resume slideshow' : 'Pause slideshow'"
                >
                    <x-ui.icon name="play" size="sm" x-show="userPaused || reducedMotion" x-cloak /><x-ui.icon name="pause" size="sm" x-show="!userPaused && !reducedMotion" />
                </x-ui.button>
                <p class="mr-auto text-sm text-text-muted" aria-hidden="true" x-text="(current + 1) + ' / ' + total"></p>
                <x-ui.button variant="secondary" type="button" @click="next()" aria-label="Next slide">Next <x-ui.icon name="next" size="sm" /></x-ui.button>
                <x-action-menu context="slideshow actions">
                    <x-ui.button variant="tertiary" type="button" class="dropdown-item text-left justify-start"
                            @click="prev(); open = false; $nextTick(() => $refs.trigger.focus())">Previous slide</x-ui.button>
                    <p class="border-t border-surface-border px-4 pt-2 text-xs font-semibold text-text-muted">Choose a slide</p>
                    @foreach ($slides as $slide)
                        <x-ui.button variant="tertiary"
                            type="button"
                            @click="goTo({{ $loop->index }}); open = false; $nextTick(() => $refs.trigger.focus())"
                            aria-label="Go to slide {{ $loop->iteration }}: {{ $slide['title'] }}"
                            ::aria-current="isActive({{ $loop->index }}) ? 'true' : 'false'"
                            class="dropdown-item text-left justify-start"
                        >
                            {{ $loop->iteration }}. {{ $slide['title'] }}
                        </x-ui.button>
                    @endforeach
                </x-action-menu>
            </div>
            <p class="sr-only" aria-live="polite" aria-atomic="true" x-text="paused ? 'Slide ' + (current + 1) + ' of ' + total : ''"></p>
        @endif
    </section>
@endif

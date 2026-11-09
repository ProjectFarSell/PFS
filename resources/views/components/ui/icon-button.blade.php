@props([
    'label', 'tooltip' => null, 'icon' => null, 'variant' => 'tertiary', 'size' => 'md',
    'iconVariant' => 'outline', 'href' => null, 'type' => 'button', 'as' => null,
    'loading' => false, 'disabled' => false, 'wireTarget' => null,
])

<x-ui.button :variant="$variant" :size="$size" :icon="$icon" :icon-variant="$iconVariant" :icon-only="true"
    :href="$href" :type="$type" :as="$as" :loading="$loading" :disabled="$disabled" :wire-target="$wireTarget"
    {{ $attributes->merge(['aria-label' => $label, 'title' => $tooltip ?? $label]) }}>
    {{ $slot }}
</x-ui.button>

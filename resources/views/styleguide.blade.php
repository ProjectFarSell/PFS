@extends('layouts.auth')

@section('title', 'UI styleguide · FarSell')

@section('content')
<div class="mx-auto max-w-6xl space-y-6 p-4 sm:p-6">
    <header class="flex items-center justify-between gap-3">
        <div>
            <p class="section-eyebrow">Local development</p>
            <h1 class="text-2xl font-bold">UI styleguide</h1>
        </div>
        <x-theme-toggle />
    </header>

    <section class="fs-card space-y-4 p-4" aria-labelledby="styleguide-buttons">
        <h2 id="styleguide-buttons" class="text-lg font-semibold">Buttons</h2>
        <p class="text-sm text-text-muted">Tab through enabled controls to inspect the focus ring. Hover and press each variant to inspect pointer states.</p>
        @foreach (['primary', 'secondary', 'tertiary', 'danger', 'link'] as $variant)
            <div class="space-y-2 border-t border-surface-border pt-3">
                <h3 class="text-sm font-semibold">{{ ucfirst($variant) }}</h3>
                <div class="flex flex-wrap items-center gap-2">
                    <x-ui.button :variant="$variant" size="sm">Small</x-ui.button>
                    <x-ui.button :variant="$variant" icon="add">Add item</x-ui.button>
                    <x-ui.button :variant="$variant" size="lg" icon="next" icon-position="right">Continue</x-ui.button>
                    <x-ui.button :variant="$variant" disabled>Disabled</x-ui.button>
                    <x-ui.button :variant="$variant" loading>Saving</x-ui.button>
                    <x-ui.icon-button :variant="$variant" icon="edit" label="Edit item" />
                </div>
            </div>
        @endforeach
        <div class="flex flex-wrap gap-2">
            <x-ui.button variant="link" href="#styleguide-icons">Jump to icons</x-ui.button>
            <x-ui.button variant="link" href="#styleguide-icons" disabled>Unavailable link</x-ui.button>
        </div>
    </section>

    <section id="styleguide-icons" class="fs-card space-y-3 p-4" aria-labelledby="styleguide-icon-heading">
        <h2 id="styleguide-icon-heading" class="text-lg font-semibold">Heroicons</h2>
        <div class="flex flex-wrap gap-4">
            @foreach (['edit', 'delete', 'view', 'add', 'search', 'filter', 'export', 'cart', 'order', 'delivery', 'payout', 'settings'] as $name)
                <span class="inline-flex items-center gap-2 text-sm"><x-ui.icon :name="$name" />{{ ucfirst($name) }}</span>
            @endforeach
        </div>
        <div class="flex items-center gap-4">
            <x-ui.icon name="save" size="sm" /><x-ui.icon name="save" size="md" /><x-ui.icon name="save" size="lg" />
            <x-ui.icon name="save" variant="solid" size="lg" />
            <span class="text-sm text-text-muted">16 px, 20 px, 24 px, filled</span>
        </div>
    </section>

    <section class="fs-card space-y-4 p-4" aria-labelledby="styleguide-fields">
        <h2 id="styleguide-fields" class="text-lg font-semibold">Status and form primitives</h2>
        <div class="flex flex-wrap gap-2">
            @foreach (['pending', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded'] as $status)
                <x-ui.status-pill :status="$status" />
            @endforeach
        </div>
        <div class="grid gap-3 sm:grid-cols-2">
            <label class="fs-label">Text input <input class="fs-input mt-1" placeholder="Example"></label>
            <label class="fs-label">Select <select class="fs-input mt-1"><option>Example option</option></select></label>
        </div>
        <div class="alert-success" role="status">Sample success notification</div>
        <div class="alert-error" role="status">Sample error notification</div>
        <form @submit.prevent x-data>
            <x-destructive-confirmation name="Delete example" description="This styleguide action is a visual demonstration." />
        </form>
    </section>
</div>
@endsection

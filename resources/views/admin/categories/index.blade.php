@extends('layouts.portal')

@section('title', 'Categories · FarSell')

@section('content')
    <a href="{{ route('admin.dashboard') }}" class="text-sm text-accent underline">Back to Admin Dashboard</a>
    <section class="fs-card mx-auto mt-4 max-w-5xl p-5 sm:p-7">
        <h1 class="text-xl font-semibold">Product categories</h1>
        <p class="mt-2 text-sm text-text-muted">Create a category for seller listings and seller applications. Existing categories stay available.</p>

        @if($errors->any())
            <ul role="alert" class="mt-4 list-disc pl-5 text-sm text-error">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        @endif

        <form method="post" action="{{ route('admin.categories.store') }}" class="mt-5 grid gap-3 rounded-xl border border-surface-border p-4 sm:grid-cols-[1fr_7rem_7rem_auto] sm:items-end">
            @csrf
            <label class="block text-sm">Category name<input name="name" required maxlength="80" value="{{ old('name') }}" class="mt-1 w-full rounded-lg border-surface-border text-sm focus:border-accent focus:ring-accent"></label>
            <label class="block text-sm">Icon (optional)<input name="icon" maxlength="8" value="{{ old('icon') }}" placeholder="e.g. H" class="mt-1 w-full rounded-lg border-surface-border text-sm focus:border-accent focus:ring-accent"></label>
            <label class="block text-sm">Display order<input type="number" min="0" max="9999" name="sort_order" value="{{ old('sort_order', 0) }}" class="mt-1 w-full rounded-lg border-surface-border text-sm focus:border-accent focus:ring-accent"></label>
            <button class="btn-accent min-h-10">Create category</button>
        </form>

        <h2 class="mt-7 font-semibold">Current categories</h2>
        <div class="mt-3 divide-y divide-surface-border">
            @forelse($categories as $category)
                <article class="space-y-3 py-4">
                    <div class="flex items-center gap-3">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-accent-subtle font-semibold text-accent">{{ $category->icon ?: mb_substr($category->name, 0, 1) }}</span>
                        <div class="min-w-0"><h3 class="break-words font-semibold">{{ $category->name }}</h3><p class="text-xs text-text-muted">{{ $category->products_count }} products · {{ $category->applications_count }} seller applications · order {{ $category->sort_order }}</p></div>
                    </div>
                    <form method="post" action="{{ route('admin.categories.update', $category) }}" class="grid gap-3 rounded-xl border border-surface-border p-3 sm:grid-cols-[1fr_7rem_7rem_auto] sm:items-end">
                        @csrf @method('put')
                        <label class="block text-sm">Name<input name="name" required maxlength="80" value="{{ $category->name }}" class="mt-1 w-full rounded-lg border-surface-border text-sm focus:border-accent focus:ring-accent"></label>
                        <label class="block text-sm">Icon<input name="icon" maxlength="8" value="{{ $category->icon }}" class="mt-1 w-full rounded-lg border-surface-border text-sm focus:border-accent focus:ring-accent"></label>
                        <label class="block text-sm">Display order<input type="number" min="0" max="9999" name="sort_order" value="{{ $category->sort_order }}" class="mt-1 w-full rounded-lg border-surface-border text-sm focus:border-accent focus:ring-accent"></label>
                        <button class="btn-outline min-h-10">Save changes</button>
                    </form>
                    <form method="post" action="{{ route('admin.categories.destroy', $category) }}" class="flex flex-wrap items-end gap-3 rounded-xl border border-surface-border p-3" onsubmit="return confirm('Delete this category? Linked listings and seller applications will be moved to the replacement category.')">
                        @csrf @method('delete')
                        @if($category->products_count > 0 || $category->applications_count > 0)
                            <label class="min-w-56 flex-1 text-sm">Move linked records to
                                <select name="replacement_category_id" required class="mt-1 w-full rounded-lg border-surface-border text-sm focus:border-accent focus:ring-accent">
                                    <option value="">Choose replacement category</option>
                                    @foreach($replacementCategories->where('id', '!=', $category->id) as $replacement)
                                        <option value="{{ $replacement->id }}">{{ $replacement->name }}</option>
                                    @endforeach
                                </select>
                            </label>
                        @endif
                        <button class="min-h-10 rounded-lg border border-error px-4 py-2 text-sm font-semibold text-error hover:bg-error/10">Delete category</button>
                    </form>
                </article>
            @empty
                <p class="py-5 text-sm text-text-muted">No categories exist yet.</p>
            @endforelse
        </div>
        <div class="mt-4">{{ $categories->links() }}</div>
    </section>
@endsection

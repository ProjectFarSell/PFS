---
name: farsell-frontend
description: >-
  Implements and restyles FarSell UI with Laravel Blade, Alpine.js v3, Tailwind CSS v3,
  and CSS design tokens. Use when editing resources/views, resources/css, resources/js,
  public/js, Vite/Tailwind config, layouts, product cards, auth/portal pages, cart,
  checkout, addresses, or any marketplace frontend work. Do not use React, Vue, Livewire,
  or Inertia unless the user explicitly asks.
---

# FarSell frontend

FarSell is a mobile-first surplus marketplace (Doorzo lots, Shopee-fast checkout). Server-rendered Blade plus light Alpine. No SPA.

## Stack (do not replace)

| Layer | Use |
|---|---|
| Templates | Blade in `resources/views/` |
| CSS | Tailwind v3 + tokens in `resources/css/app.css` |
| JS | Alpine v3 in `resources/js/app.js`; axios in `bootstrap.js` |
| Build | Vite (`npm run dev` / `npm run build`) |
| Font | Figtree via Google Fonts in layouts |

Do **not** add React, Vue, Livewire, Inertia, or a second CSS framework.

## Layouts

- `layouts.app` — storefront shell: sticky header, category nav, search, cart badge, theme toggle, guest banner, flash `session('status')`, footer. Composer injects `cartCount`, `isGuestBrowse`, `navCategories`.
- `layouts.portal` — chrome-free (split login/register in `welcome.blade.php`).

New storefront pages: `@extends('layouts.app')`, `@section('title')`, `@section('content')`. Extra JS: `@push('scripts')`.

Named routes only (`route('catalog.index')`). CSRF on every POST (`@csrf`). `@method('put')` / `delete` where needed.

## Design system (prefer this)

Tokens live on `:root` / `.dark` as space-separated RGB (no commas), consumed as `rgb(var(--color-*) / <alpha>)`. Tailwind maps them as `accent`, `surface`, `on-surface`, `surface-muted`, `surface-border`, `text-base`, `text-muted`, `success`, `error`.

**When adding or restyling UI, use tokens and component classes — not raw `violet-*` / `stone-*`.** Older pages (`home`, `catalog/*`, `cart`, `checkout`, `addresses`) still use hardcoded stone/violet; match nearby code for tiny one-line fixes, but any new block or full-page restyle must use the token system.

### Component classes (`@layer components` in `app.css`)

| Class | Use |
|---|---|
| `fs-card` / `fs-card-raised` | Panels |
| `fs-input` / `fs-label` | Fields |
| `btn` `btn-accent` `btn-outline` `btn-ghost` | Buttons (`rounded-full` for CTAs is OK) |
| `badge` `badge-accent` `badge-success` `badge-error` `badge-neutral` | Chips |
| `alert-success` `alert-error` | Flash / validation |
| `nav-link` `dropdown-panel` `dropdown-item` | Nav |
| `theme-toggle` `carousel-btn` `scroll-x` `section-title` | Chrome / strips |

Inline `style="… rgb(var(--color-…))"` is used in layouts for properties Tailwind cannot see. Prefer classes when possible.

Dark mode is `class` on `<html>`. Theme: `localStorage.farsell_theme`, FOUC script in both layouts, Alpine magic `$theme.toggle()` / `$theme.isDark`. Keep both.

## Alpine

- Interactivity stays in Blade (`x-data`, `x-show`, `x-model`). Keep state tiny.
- Hidden `x-show` panels: `style="display:none;"` so they are closed before Alpine boots.
- Dropdowns: `@click.outside` and/or hover; `x-transition` like the header.
- Theme buttons need `x-data` so `$theme` works.
- Reuse `Alpine.data('carousel', total, autoplayMs)` from `app.js` for carousels — do not duplicate.
- Page-specific vanilla JS (PSGC address cascade) lives in `public/js/address-location.js`, loaded via `@push('scripts')` + `asset()`, not bundled unless you are consolidating on purpose.

## Page patterns

- **Product tiles:** always `@include('catalog.partials.card', ['product' => $product])` (optional `compact` for horizontal strips). Do not fork card markup.
- **Grids:** `grid grid-cols-2 sm:grid-cols-4 gap-3`. Horizontal rails: `flex gap-3 overflow-x-auto` or `scroll-x`.
- **Auth:** login/register pages on `layouts.app`; marketing split on `welcome` + `layouts.portal`. Guest browse: POST `guest.start`.
- **Forms:** `old()`, `@error` / `$errors`, `alert-error` lists. Disable submit until valid when existing JS already does (address save).
- **Money:** `formattedPrice()`; compare-at with `₱` + `number_format`.
- **Copy:** Japan auction surplus, Philippine delivery, COD/GCash/Maya. Roles: buyer, seller, rider, admin — do not invent new storefront IA.

## Workflow

1. Read the layout plus the closest similar view before editing.
2. Reuse classes, partials, and Alpine snippets; do not invent a parallel visual language.
3. After UI changes: `npm run dev` (or `build`), then check **light and dark**, **mobile (~375) and desktop**, and any other page that shares the partial/layout you touched (home, catalog, cart, checkout, auth).
4. Hunt regressions: header cart badge, guest banner, dropdowns, theme toggle, forms.

## Do not

- Hardcode `#7C3AED` or `bg-white text-stone-*` in new work.
- Put business rules in Alpine that belong in controllers/Form Requests.
- Change Filament/admin PHP unless the task is admin UI.
- Skip CSRF, named routes, or `@vite` on new layouts.

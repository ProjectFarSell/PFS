# Requirements Document

## Introduction

This feature refactors the FarSell frontend UI across the entire Laravel + Blade + Alpine.js + Tailwind CSS v3 application. The goals are to elevate visual quality to a premium e-commerce level, improve usability on both desktop and mobile, and introduce several new interaction patterns (slide-over profile drawer, advanced filter sidebar, multi-step rider form, etc.) — all without altering any backend business logic, database schemas, or existing route/controller contracts.

The application already ships with:
- Tailwind design tokens (`--color-*` CSS custom properties) for light/dark themes
- An Alpine.js `carousel()` data component and a `$theme` magic
- A `layouts/app.blade.php` shell used by the majority of views
- Role-based navigation (Buyer, Seller, Rider, Admin)

All requirements below operate within that existing system unless explicitly noted otherwise.

---

## Glossary

- **App_Layout**: The `layouts/app.blade.php` Blade template that wraps most pages.
- **Portal_Layout**: The `layouts/portal.blade.php` Blade template used for auth-only screens.
- **Card**: The `catalog/partials/card.blade.php` product card partial.
- **Carousel**: The Alpine.js `carousel()` component registered in `resources/js/app.js`.
- **Drawer**: The right-slide-over account panel described in Requirement 2.
- **FilterSidebar**: The advanced product-filter panel described in Requirement 5.
- **Account_Dashboard**: The `/account/profile` page described in Requirement 4.
- **Rider_Form**: The multi-step rider application form described in Requirement 7b.
- **Design_Token**: A CSS custom property defined in `resources/css/app.css` (e.g., `--color-surface`).

---

## Requirements

---

### Requirement 1: Global Visual System & Product Card Refactor

**User Story:** As a shopper, I want product cards and surfaces to feel premium and visually distinct, so that the marketplace looks polished and trustworthy.

#### Acceptance Criteria

1. THE App_Layout SHALL wrap the entire page body in a flex column with `min-h-screen`, ensuring the footer always sits at the bottom of the viewport when page content is shorter than the viewport height.

2. IF the user's browser has the `dark` class applied to `<html>`, THEN THE Card SHALL render with `background-color: #23212d`; otherwise THE Card SHALL render with `background-color: rgb(var(--color-surface))`. In both cases THE Card SHALL have a single-pixel border using `--color-surface-border` and a two-layer box-shadow (`shadow-card-sm`).

3. WHEN a pointer device hovers over a Card, THE Card SHALL transition its border color to `rgb(var(--color-accent))` within 150 ms using CSS `transition-colors` and increase the shadow to the `shadow-card-md` scale.

4. WHERE six or more products are present in the grid AND the grid has at least two columns, THE first product Card SHALL render in a `col-span-2 row-span-2` hero variant with a gradient overlay applied from bottom to top and a "Featured" badge positioned at the top-left corner of the image area.

5. WHERE fewer than six products are present, ALL Cards SHALL render in the standard (non-hero) variant regardless of grid size.

6. THE App_Layout SHALL expose a `.fs-card` component class in `resources/css/app.css` that sets background-color via `--color-surface`, border via `--color-surface-border`, box-shadow, and a `transition-all duration-150` rule, such that applying `.fs-card` to any element produces the Card visual state without additional CSS overrides at the page level.

7. WHEN the Theme_Store toggles between dark and light modes, THE Card background-color, border-color, and shadow SHALL update within one animation frame without a page reload, because they reference CSS custom properties that update on the `<html>` element.

---

### Requirement 2: Adaptive Profile Button & Right Slide-Over Drawer

**User Story:** As a user, I want a persistent, identity-aware header button that opens a quick-access account panel, so that I can navigate my account without leaving the current page.

#### Acceptance Criteria

1. THE App_Layout SHALL render a pill-shaped profile button in the right icon cluster of the header on all breakpoints (replacing the existing desktop-only avatar dropdown).

2. WHEN the authenticated user views the pill button, THE App_Layout SHALL display the user's initials avatar and truncated display name inside the pill, constrained to a maximum width of 160 px using `max-w-[160px] truncate`.

3. WHEN a guest views the pill button, THE App_Layout SHALL display "Sign In" and "Register" as two separate inline-text links inside the pill, separated by a `|` divider character.

4. WHEN the user clicks or activates the profile pill button, THE Drawer SHALL slide in from the right edge of the viewport with a 250 ms `transform: translateX()` CSS transition from `translateX(100%)` to `translateX(0)`, and a semi-transparent backdrop (`bg-black/60 backdrop-blur-sm`) SHALL cover the rest of the page. WHEN the user clicks the backdrop, THE Drawer SHALL close.

5. WHILE the Drawer is open, THE Drawer SHALL trap keyboard focus within itself and apply `overflow: hidden` to `<body>` to prevent page scroll. Pressing the Escape key SHALL close the Drawer and return focus to the profile pill button.

6. THE Drawer SHALL display, from top to bottom: (a) an avatar block — a 56 px initials circle with accent background, the user's `name`, the email prefix as a handle prefixed with `@`, the year from `created_at` as "Member since YYYY", and the static text "0 pts" labelled "Loyalty Points"; (b) a divider; (c) a "Quick Links" group containing a "Wishlist" placeholder item (coming-soon tooltip), an "Order History" item linking to `route('orders.index')`, and a "Saved Addresses" item linking to `route('account.addresses.index')`; (d) a divider; (e) a full-width "Sign Out" POST form button submitting to `route('logout')`.

7. WHEN the user is a guest and the Drawer is opened, THE Drawer SHALL display a centred block with the text "You're not signed in", a primary "Sign In" button linking to `route('login')`, and a secondary "Create account" link to `route('register')`, with no account-specific links visible.

8. IF the viewport width is less than 640 px, THEN THE Drawer SHALL have `width: 100vw`; otherwise THE Drawer SHALL have a fixed `width: 320px`.

9. THE App_Layout SHALL retain the existing mobile hamburger menu, category dropdown, shop link, and rider link; the Drawer replaces only the desktop account dropdown and the desktop utility-bar sign-in/register links.

---

### Requirement 3: Hero Auto-Sliding Carousel

**User Story:** As a shopper, I want an animated full-width hero banner on the home page that automatically cycles through featured promotions, so that I am engaged and informed of current offers without manual interaction.

#### Acceptance Criteria

1. THE Carousel SHALL replace the static hero `<section>` element on `home.blade.php`, using the existing `carousel()` Alpine.js data component initialised with the slide count and `autoplay: 5000`.

2. THE Carousel SHALL auto-advance slides at a 5 000 ms interval, started in the Alpine `init()` hook and cleared in the Alpine `destroy()` hook.

3. WHEN a pointer device enters the Carousel container, THE Carousel SHALL clear the auto-advance interval; WHEN the pointer leaves, THE Carousel SHALL restart the interval at 5 000 ms.

4. THE Carousel SHALL render a left chevron button with `aria-label="Previous slide"` and a right chevron button with `aria-label="Next slide"`, both positioned absolutely at vertical centre with `bg-black/40 backdrop-blur-sm`, hidden when `total === 1`.

5. THE Carousel SHALL render a pagination row below the slide area; the active dot SHALL have `class="w-6 h-2 rounded-full bg-purple-500"` with `transition-all duration-200`; inactive dots SHALL have `class="w-2 h-2 rounded-full bg-white/50"`; dots SHALL be hidden when `total === 1`.

6. THE Carousel SHALL render a play/pause toggle button. Initial state SHALL be "playing". `aria-label` SHALL read "Pause slideshow" when playing and "Play slideshow" when paused. Clicking SHALL toggle auto-advance.

7. WHEN the user performs a horizontal swipe gesture (touchstart → touchend, horizontal delta ≥ 50 px), THE Carousel SHALL call `next()` for a left-swipe and `prev()` for a right-swipe.

8. IF the `carousel()` component is initialised with `total = 0`, THEN THE Carousel SHALL render an empty container with zero height without throwing a JavaScript error.

9. EACH slide SHALL render as a full-width panel with `min-h-[240px] lg:min-h-[400px]`, containing a `<h2>` title, a `<p>` subtitle, and a CTA `<a>` button; slide content SHALL be defined as a PHP array in the Blade template.

---

### Requirement 4: Shopee-Style Account Dashboard

**User Story:** As a buyer, I want a structured account hub with clear sub-navigation, so that I can quickly access any part of my account without scrolling through a long single-column page.

#### Acceptance Criteria

1. THE Account_Dashboard SHALL render in a two-column layout on screens ≥ 768 px: a left sidebar with `w-56` (224 px) and a flex-1 right content panel.

2. IF the viewport width is less than 768 px, THEN THE Account_Dashboard SHALL replace the sidebar with a horizontally scrollable pill-tab bar above the content panel, with no sidebar visible.

3. THE Account_Dashboard sidebar SHALL group navigation items into two sections: **"My Account"** — Profile (default active), Bank & Cards (placeholder), Addresses, Privacy Settings (placeholder), Notification Settings (placeholder); **"My Purchase"** — All, To Pay, To Ship, To Receive, Completed, Cancelled, Return/Refund (placeholder), To Review.

4. WHEN the user clicks an active (non-placeholder) sidebar item, THE Account_Dashboard SHALL update the right content panel using Alpine.js `activeTab` state without a full-page reload.

5. THE Account_Dashboard SHALL render the Profile panel as default (`activeTab = 'profile'`), displaying: the user's `name`, `email`, `phone` (or "Not provided"), a role badge, and a link to `route('account.profile')` labelled "Edit profile & security".

6. THE Account_Dashboard Addresses panel SHALL render a shortcut card linking to `route('account.addresses.index')` with the user's default address label and a count of saved addresses.

7. THE Account_Dashboard My Purchase panels SHALL map to `OrderStatus` values: "All" → all; "To Pay" → `pending_payment`; "To Ship" → `paid` or `packed`; "To Receive" → `assigned` or `in_transit`; "Completed" → `delivered`; "Cancelled" → `cancelled`. Each order card SHALL display: order `number`, `created_at` formatted as "M j, Y", status label from `$order->status->label()`, and the item count.

8. IF the authenticated user has the `buyer` role, THEN the Profile panel SHALL include a "Become a Seller" card with a link to the seller application route.

9. WHERE the "To Review" tab is active, THE Account_Dashboard SHALL render a placeholder card with the text "Seller reviews via Google Reviews are coming soon." with no external API call.

10. THE Account_Dashboard SHALL render role-specific shortcut links in the sidebar footer: "Seller Dashboard" for Seller role, "Rider Dashboard" for Rider role, "Admin Panel" for Admin role.

---

### Requirement 5: Advanced Shopping Filter Sidebar

**User Story:** As a shopper, I want powerful filter controls on the catalog page, so that I can narrow products by price, rating, brand, and delivery option efficiently.

#### Acceptance Criteria

1. THE FilterSidebar SHALL be rendered as a persistent left panel with `w-60` (240 px) on screens ≥ 1024 px alongside the product grid.

2. IF the viewport width is less than 1024 px, THEN THE FilterSidebar SHALL be hidden by default and openable via a "Filters" button as a bottom sheet overlay (`position: fixed; bottom: 0; max-height: 80vh; overflow-y: auto`).

3. AT the top of the catalog page, a horizontally scrollable pill row SHALL render one pill per category. The active category pill (matching `request('category')`) SHALL use accent background and white text. Each pill SHALL link to `route('catalog.index', ['category' => $cat->id])`.

4. THE FilterSidebar price range section SHALL contain two `<input type="number">` fields named `price_min` and `price_max`, pre-populated from the current query string.

5. THE FilterSidebar star-rating section SHALL render five checkboxes labelled "5 Stars" through "1 Star & up" with `name="rating[]"` and values 5–1, pre-checked from `request('rating', [])`. UI-only in this iteration (no backend filtering).

6. THE FilterSidebar shop/brand section SHALL render a text input with Alpine.js `x-model="shopSearch"` that client-side filters a list of shop checkboxes (`name="shop[]"`), each pre-checked from `request('shop', [])`, with shop name and product count displayed.

7. WHERE the `shop[]` query parameter contains shop IDs, THE catalog controller SHALL filter products to only those shops.

8. THE FilterSidebar delivery-options section SHALL render two toggle switches ("Same-day delivery", "FarSell Guaranteed") as UI-only placeholders with "Coming soon" tooltips.

9. THE product grid SHALL use `grid-cols-2 sm:grid-cols-3 lg:grid-cols-4`. WHEN six or more products are returned, THE first product SHALL render as a `col-span-2` spotlight card with gradient background.

10. WHEN any filter is submitted, ALL active filter values (category, price_min, price_max, rating[], shop[], q) SHALL be preserved as hidden inputs so combining filters does not discard prior selections.

---

### Requirement 6: Sticky Footer

**User Story:** As a user, I want the footer to always appear at the bottom of the viewport when the page content is short, so that the layout does not look broken on sparse pages.

#### Acceptance Criteria

1. THE App_Layout `<body>` element SHALL have `class="min-h-screen flex flex-col"` applied.

2. THE App_Layout `<main>` element SHALL have `class="flex-1"` applied.

3. THE Portal_Layout `<body>` element SHALL have `min-h-screen flex flex-col` applied, and any main content wrapper SHALL have `flex-1`.

4. IF the combined height of header + main content + footer exceeds the viewport height, THEN the body SHALL overflow vertically with default browser scroll behavior, and no content SHALL be clipped.

---

### Requirement 7: UI Audit Fixes

**User Story:** As a user, I want refined, polished interactions on the home page, rider registration form, cart empty state, and shops directory, so that the application feels complete and professional.

#### Acceptance Criteria

#### Acceptance Criteria — 7a: Home Page Enhancements

1. THE home page category strip SHALL attempt to render an SVG icon mapped from the category `slug`. IF no SVG mapping exists for a slug, THE strip SHALL render the category's `icon` emoji as a fallback. The mapping SHALL be defined as a PHP array in the Blade template.

2. THE home page SHALL render an Alpine.js `countdown()` component on each flash-deal card targeting a configurable `endTime` Unix timestamp (defaulting to midnight of the current server day). The component SHALL display HH:MM:SS updated every 1 000 ms. WHEN the countdown reaches zero, it SHALL display "Ended".

3. THE home page SHALL render a stock progress bar beneath each flash-deal card using `min(100, ($product->stock / 50) * 100)%` width (configurable max of 50), styled as `h-1.5` with `--color-accent` fill.

4. THE home page product grid SHALL render four skeleton shimmer Cards (`animate-pulse`) wrapped in an Alpine `x-show` block that hides them after Alpine initialises, indicating server render is complete.

#### Acceptance Criteria — 7b: Rider Multi-Step Registration Form

1. THE Rider_Form SHALL be restructured into three sequential steps via Alpine.js `x-data="{ step: 1 }"`:
   - Step 1 "Personal Info": `name` (read-only, pre-filled), `phone` (required).
   - Step 2 "Vehicle Details": `vehicle_type` (required), `plate_number`, `license_no` (required), `city` (required), `bio`.
   - Step 3 "Document Verification": `license_document`, `id_document`, `vehicle_reg_document`.

2. THE Rider_Form SHALL render a three-step indicator at the top with: circle badge (accent-filled for completed, outlined for future, solid for current), step label, and a checkmark replacing the number for completed steps.

3. WHEN "Next" is clicked on Step 1, THE form SHALL validate `phone` is non-empty. WHEN "Next" is clicked on Step 2, THE form SHALL validate `vehicle_type`, `license_no`, and `city` are non-empty. IF a field is empty, an inline error SHALL appear and the step SHALL NOT advance.

4. EACH file input in Step 3 SHALL be a drag-and-drop zone. WHEN a file is dragged over the zone, the border SHALL change to `rgb(var(--color-accent))`. WHEN a file is dropped or selected, the zone SHALL display the filename and a remove button.

5. ALL three steps SHALL be inside a single `<form method="post" action="{{ route('rider.register') }}" enctype="multipart/form-data">` with `@csrf`.

6. IF `$errors->any()` is true, THE form SHALL initialise with `step: 1` and display all server errors in an alert block at the top of Step 1.

#### Acceptance Criteria — 7c: Cart Empty State

1. WHEN `$lines->isEmpty()` is true, THE cart page SHALL render only a centred empty-state block containing: an inline SVG cart illustration (~96 × 96 px using `currentColor`), a `<p>` with `text-xl font-semibold` reading "Your cart is empty", a `<p>` with `text-sm` reading "Looks like you haven't added anything yet.", and a `.btn-accent` link to `route('catalog.index')` labelled "Browse products".

2. THE `<h1>` "Cart" heading SHALL NOT be rendered when the cart is empty.

#### Acceptance Criteria — 7d: Shops Directory Grid

1. THE shops index view SHALL render a `grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5` grid.

2. EACH shop card SHALL display: (a) an 80 px banner strip using a CSS gradient cycled from the shop `id`, (b) a 48 px circular avatar with the first letter of `$shop->name` on accent background, overlapping the banner by 24 px, (c) shop `name` as `<h3 class="text-base font-semibold">`, (d) shop `city` as `<p class="text-xs text-muted">`, (e) `tagline` truncated to 2 lines, (f) active product count as "N products", (g) a `.btn-accent` link to `route('shops.show', $shop)` labelled "Visit Store".

3. THE shops index page SHALL display an empty-state block with `role="status"` spanning `col-span-full` when no shops are found.

# Frontend audit

Static source audit of Buyer, Seller, Admin and Rider, 9 November 2026. Verified registered GET routes against controller responses and Blade files. The installed stack is Laravel 13, Tailwind 3 and Alpine 3. No application Livewire component directory, installed Livewire package, or Blade Livewire directives were found. The only file written for this audit is this report.

Browser checks at 360, 768 and 1280 px, assistive technology checks, network measurements and SQL profiling remain open. Counts refer to source files. A shared partial counts once regardless of how many pages render it.

## 1. Route and page map

Shared entry and account routes are listed once. Buyer storefront pages also serve guests and can be browsed by sellers. Admin and Rider redirect from storefront routes to their dashboards.

### Buyer and public storefront

| URL | Route name | Page or section | Blade view |
| --- | --- | --- | --- |
| `/`, `/home` | `welcome`, `home` | Marketplace home | [home.blade.php](../resources/views/home.blade.php) |
| `/search` | `catalog.index` | Search and category results · mobile filter drawer | [catalog/index.blade.php](../resources/views/catalog/index.blade.php) |
| `/p/{product}` | `products.show` | Product details, options, reviews and related products | [catalog/show.blade.php](../resources/views/catalog/show.blade.php) |
| `/shops` | `shops.index` | Shop directory | [shop/index.blade.php](../resources/views/shop/index.blade.php) |
| `/shop/{shop}` | `shops.show` | Shop storefront | [shop/show.blade.php](../resources/views/shop/show.blade.php) |
| `/cart` | `cart.index` | Cart (guest or buyer) | [cart/index.blade.php](../resources/views/cart/index.blade.php) |
| `/checkout` | `checkout.create` | Checkout, including replacement checkout context | [checkout/create.blade.php](../resources/views/checkout/create.blade.php) |
| `/orders` | `orders.index` | Order history | [orders/index.blade.php](../resources/views/orders/index.blade.php) |
| `/orders/{order}` | `orders.show` | Order detail, shipment tracking, returns/replacements and cancellation | [orders/show.blade.php](../resources/views/orders/show.blade.php) |
| `/orders/{order}/print/{document}` | `orders.print` | Invoice and packing slip | [orders/print.blade.php](../resources/views/orders/print.blade.php) |
| `/fulfillments/{fulfillment}/feedback` | `orders.feedback.create` | Product and delivery feedback | [orders/feedback.blade.php](../resources/views/orders/feedback.blade.php) |
| `/account/addresses` | `account.addresses.index` | Saved addresses | [account/addresses/index.blade.php](../resources/views/account/addresses/index.blade.php) |
| `/account/addresses/create` | `account.addresses.create` | Add address | [account/addresses/create.blade.php](../resources/views/account/addresses/create.blade.php) |
| `/account/addresses/{address}/edit` | `account.addresses.edit` | Edit address | [account/addresses/edit.blade.php](../resources/views/account/addresses/edit.blade.php) |
| `/account/saved` | `account.saved.index` | Wishlist | [account/saved/index.blade.php](../resources/views/account/saved/index.blade.php) |
| `/account/notifications` | `account.notifications.index` | Notifications | [account/notifications.blade.php](../resources/views/account/notifications.blade.php) |
| `/chat` | `chat.index` | Inbox (also Seller) | [chat/index.blade.php](../resources/views/chat/index.blade.php) |
| `/chat/{conversation}` | `chat.show` | Conversation (also Seller) | [chat/show.blade.php](../resources/views/chat/show.blade.php) |
| `/returns` | `returns.index` | Returns and refunds (also Seller/Admin) | [returns/index.blade.php](../resources/views/returns/index.blade.php) |
| `/replacements` | `replacements.index` | Replacement requests (also Seller/Admin) | [replacements/index.blade.php](../resources/views/replacements/index.blade.php) |
| `/order-items/{item}/replacement` | `replacements.create` | Request a replacement | [replacements/create.blade.php](../resources/views/replacements/create.blade.php) |
| `/seller/apply` | `seller.apply` | Apply to sell or review application (also Seller) | [seller/application.blade.php](../resources/views/seller/application.blade.php) |
| `/rider/apply` | `rider.register` | Rider application wizard (Buyer entry) | [rider/register.blade.php](../resources/views/rider/register.blade.php) |
| `/rider/profile` | `rider.profile` | Rider application status (also Rider) | [rider/profile.blade.php](../resources/views/rider/profile.blade.php) |

### Seller

| URL | Route name | Page or section | Blade view |
| --- | --- | --- | --- |
| `/seller` | `seller.dashboard` | Dashboard, inventory and order items | [seller/dashboard.blade.php](../resources/views/seller/dashboard.blade.php) |
| `/seller/products/create` | `seller.products.create` | Create listing | [seller/product-form.blade.php](../resources/views/seller/product-form.blade.php) |
| `/seller/products/{product}/edit` | `seller.products.edit` | Edit listing | [seller/product-form.blade.php](../resources/views/seller/product-form.blade.php) |
| `/seller/sales` | `seller.sales` | Sales summary with date filters | [seller/sales.blade.php](../resources/views/seller/sales.blade.php) |
| `/seller/apply` | `seller.apply` | Seller application | [seller/application.blade.php](../resources/views/seller/application.blade.php) |
| `/fulfillments` | `fulfillments.index` | Shop orders, preparation, pickup and returned shipments | [fulfillments/index.blade.php](../resources/views/fulfillments/index.blade.php) |
| `/chat` | `chat.index` | Inbox | [chat/index.blade.php](../resources/views/chat/index.blade.php) |
| `/chat/{conversation}` | `chat.show` | Conversation | [chat/show.blade.php](../resources/views/chat/show.blade.php) |
| `/returns` | `returns.index` | Return cases | [returns/index.blade.php](../resources/views/returns/index.blade.php) |
| `/replacements` | `replacements.index` | Replacement cases | [replacements/index.blade.php](../resources/views/replacements/index.blade.php) |
| `/settlements` | `settlements.index` | Commissions and settlements | [settlements/index.blade.php](../resources/views/settlements/index.blade.php) |

### Admin

| URL | Route name | Page or section | Blade view |
| --- | --- | --- | --- |
| `/admin` | `admin.dashboard` | Dashboard, recent orders and low stock | [admin/dashboard.blade.php](../resources/views/admin/dashboard.blade.php) |
| `/admin/operations` | `admin.operations` | Release and operational checks | [admin/operations.blade.php](../resources/views/admin/operations.blade.php) |
| `/admin/buyers` | `admin.buyers.index` | Buyer accounts | [admin/buyers/index.blade.php](../resources/views/admin/buyers/index.blade.php) |
| `/admin/products` | `admin.products.index` | Product inventory | [admin/products/index.blade.php](../resources/views/admin/products/index.blade.php) |
| `/admin/categories` | `admin.categories.index` | Category management | [admin/categories/index.blade.php](../resources/views/admin/categories/index.blade.php) |
| `/admin/logistics` | `admin.logistics.index` | Courier booking, reconciliation and tracking | [admin/logistics/index.blade.php](../resources/views/admin/logistics/index.blade.php) |
| `/admin/notifications` | `admin.notifications.index` | Delivery outcomes and retry | [admin/notifications/index.blade.php](../resources/views/admin/notifications/index.blade.php) |
| `/admin/partners` | `admin.partners.index` | Riders / Sellers tabs and status filters | [admin/partners/index.blade.php](../resources/views/admin/partners/index.blade.php) |
| `/admin/privacy-requests` | `admin.privacy-requests.index` | Privacy requests | [admin/privacy-requests/index.blade.php](../resources/views/admin/privacy-requests/index.blade.php) |
| `/admin/reviews` | `admin.reviews.index` | Product reviews / seller and delivery ratings | [admin/reviews/index.blade.php](../resources/views/admin/reviews/index.blade.php) |
| `/admin/rider-records` | `admin.rider-records.index` | Delivery history / earnings / buyer feedback | [admin/rider-records/index.blade.php](../resources/views/admin/rider-records/index.blade.php) |
| `/admin/riders` | `admin.riders.index` | Rider application queue | [admin/riders/index.blade.php](../resources/views/admin/riders/index.blade.php) |
| `/admin/riders/barangays` | `admin.riders.barangays.index` | Barangay assignments | [admin/riders/barangays.blade.php](../resources/views/admin/riders/barangays.blade.php) |
| `/admin/riders/{riderProfile}` | `admin.riders.show` | Application detail, documents, coverage and decision | [admin/riders/show.blade.php](../resources/views/admin/riders/show.blade.php) |
| `/admin/sellers` | `admin.sellers.index` | Seller application queue | [admin/sellers/index.blade.php](../resources/views/admin/sellers/index.blade.php) |
| `/admin/sellers/{application}` | `admin.sellers.show` | Seller application detail and decision | [admin/sellers/show.blade.php](../resources/views/admin/sellers/show.blade.php) |
| `/admin/security/accounts` | `admin.security.accounts` | Active accounts | [admin/security/accounts.blade.php](../resources/views/admin/security/accounts.blade.php) |
| `/admin/security/accounts/suspended` | `admin.security.accounts.suspended` | Suspended accounts | [admin/security/accounts.blade.php](../resources/views/admin/security/accounts.blade.php) |
| `/admin/security/accounts/restore` | `admin.security.accounts.restore` | Account restoration | [admin/security/accounts.blade.php](../resources/views/admin/security/accounts.blade.php) |
| `/admin/security/audit` | `admin.security.audit` | Audit trail | [admin/security/audit.blade.php](../resources/views/admin/security/audit.blade.php) |
| `/fulfillments` | `fulfillments.index` | Dispatch and exception queues by status | [fulfillments/index.blade.php](../resources/views/fulfillments/index.blade.php) |
| `/orders/{order}` | `orders.show` | Order and shipment history | [orders/show.blade.php](../resources/views/orders/show.blade.php) |
| `/orders/{order}/print/{document}` | `orders.print` | Invoice and packing slip | [orders/print.blade.php](../resources/views/orders/print.blade.php) |
| `/returns` | `returns.index` | Return and refund cases | [returns/index.blade.php](../resources/views/returns/index.blade.php) |
| `/replacements` | `replacements.index` | Replacement cases | [replacements/index.blade.php](../resources/views/replacements/index.blade.php) |
| `/settlements` | `settlements.index` | Commissions and settlements | [settlements/index.blade.php](../resources/views/settlements/index.blade.php) |

### Rider

| URL | Route name | Page or section | Blade view |
| --- | --- | --- | --- |
| `/rider/dashboard` | `rider.dashboard` | Availability, offers, deliveries, ratings and profile shortcut | [rider/dashboard.blade.php](../resources/views/rider/dashboard.blade.php) |
| `/rider/dashboard#delivery-requests` | `rider.dashboard` | Delivery Requests section · GET /rider/delivery-requests refreshes a JSON-wrapped fragment | [rider/delivery-requests.blade.php](../resources/views/rider/delivery-requests.blade.php) |
| `/rider/earnings` | `rider.earnings.index` | Earnings | [rider/earnings.blade.php](../resources/views/rider/earnings.blade.php) |
| `/rider/history` | `rider.history.index` | Delivery history | [rider/history.blade.php](../resources/views/rider/history.blade.php) |
| `/rider/profile` | `rider.profile` | Rider profile and application status | [rider/profile.blade.php](../resources/views/rider/profile.blade.php) |
| `/fulfillments` | `fulfillments.index` | Assigned pickups, delivery confirmation and failed attempts | [fulfillments/index.blade.php](../resources/views/fulfillments/index.blade.php) |

### Shared entry, account and legal

| URL | Route name | Page or section | Blade view |
| --- | --- | --- | --- |
| `/login` | `login` | Log in tab | [welcome.blade.php](../resources/views/welcome.blade.php) |
| `/register` | `register` | Create account tab | [welcome.blade.php](../resources/views/welcome.blade.php) |
| `/forgot-password` | `password.request` | Request reset link | [auth/forgot-password.blade.php](../resources/views/auth/forgot-password.blade.php) |
| `/reset-password/{token}` | `password.reset` | Reset password | [auth/reset-password.blade.php](../resources/views/auth/reset-password.blade.php) |
| `/auth/google/redirect` | `auth.google.redirect` | Google sign-in consent | [auth/google-consent.blade.php](../resources/views/auth/google-consent.blade.php) |
| `/account/verify` | `contact-verification.show` | Contact verification and channel steps | [auth/verify-contact.blade.php](../resources/views/auth/verify-contact.blade.php) |
| `/account/security/sign-in-code` | `security.otp.show` | Sign-in challenge | [auth/login-otp.blade.php](../resources/views/auth/login-otp.blade.php) |
| `/account/security` | `security.index` | Security, authenticator and devices | [account/security.blade.php](../resources/views/account/security.blade.php) |
| `/account/profile` | `account.profile` | Role-specific profile · buyer address preview and order tabs | [account/profile.blade.php](../resources/views/account/profile.blade.php) |
| `/account/profile/edit` | `account.profile.edit` | Profile, password and privacy settings | [account/profile-edit.blade.php](../resources/views/account/profile-edit.blade.php) |
| `/privacy` | `privacy` | Privacy notice and acceptance | [privacy.blade.php](../resources/views/privacy.blade.php) |

| Related surface | Mapping |
| --- | --- |
| Shared page sections | Search and filters use `catalog/partials/filter-sidebar` and `filter-form`. Product grids use `catalog/partials/card`. Product feedback uses `catalog/partials/reviews` and `components/review-media`. Address create/edit use `account/addresses/_form`. Order details include `orders/fulfillments` and `orders/return-actions`. Dispatch uses `fulfillments/courier` and `fulfillments/exceptions`. Chat uses `chat/partials/sidebar` and a shared `chat/partials/dock`. |
| Same-page sections and filters | Buyer profile switches address and order views in Alpine. Admin Partners uses `tab=riders` / `sellers`, Reviews uses `type=products` / `service`, Rider Records uses `section=history` / `earnings` / `feedback`. Admin dispatch status filters share one template. Seller dashboard uses separate inventory and order-item paginators. |
| Page-related responses | Stock, dashboard summary, chat messages/widget inbox, unread counts and location options return data. Delivery requests returns JSON containing HTML. Sales CSV (`/seller/sales?export=csv`), evidence, proof photos, review media and rider documents return downloads or media. Google callback redirects. |
| Unmapped Blade pages | `auth/login.blade.php` and `auth/register.blade.php` are present and included in the markup inventory. Registered login and register routes both render `welcome.blade.php`. Emails and mail templates are outside the panel inventory. |

## 2. Button inventory

Includes native buttons, button-like links, styled text actions, navigation controls and disclosure triggers. Grouping uses base class, typography/dimensions, base or selected-state color, radius, padding and icon placement. Hover, margins, alignment and semantic state attributes are omitted as grouping dimensions. Conditional colors listed together belong to one control. Counts use distinct defining Blade files, including templates with no active route. Source examples are relative to `resources/views` and omit the `.blade.php` suffix.

| Shared base | Default size | Default color | Radius | Padding | Default icon placement |
| --- | --- | --- | --- | --- | --- |
| `btn-accent`, `btn-outline`, `btn-ghost` | `text-sm`, semibold, inline flex, `gap-2` | Accent fill with `accent-text` and shadow / transparent with 1.5 px accent border and accent text / transparent muted text | `rounded-xl` | `px-5 py-2.5` | Slot placement varies |
| `theme-toggle` | 44×44 px | Muted foreground, surface-muted on hover | Full | No explicit padding | Center |
| `product-save` | 44×44 px | Accent on 90% surface with shadow and blur | Full | No explicit padding | Center |
| `portal-nav-link` | Min 44 px high, `text-sm font-medium` | Muted, active accent on muted surface | XL | `px-3` | Text, or responsive icon for My Account |
| `nav-link` / `dropdown-item` | `text-sm` | Muted / base text, accent active or hover | None unless overridden | `px-1 py-0.5` / `px-4 py-2.5` | Optional leading or trailing |
| `admin-stat` / `fs-card` / `product-card-link` | Content-sized | Card or inherited surface and text | Card or template radius | Stat card `p-4 sm:p-5`, others per template | Header icon or media above when present |

| Base | Size or type override | Color override | Radius override | Padding override | Icon | Files | Source examples |
| --- | --- | --- | --- | --- | --- | ---: | --- |
| `btn-accent` | — | — | — | — | — | 31 | `privacy:26` · `account/profile-edit:58` |
| `btn-outline` | — | — | — | — | — | 27 | `home:91` · `account/notifications:7` |
| `btn-accent` | `w-full` | — | — | — | — | 4 | `auth/google-consent:12` · `layouts/app:666` |
| `btn-accent` | `min-h-11 w-full` | — | — | — | — | 3 | `auth/forgot-password:14` · `auth/reset-password:17` |
| `btn-accent` | `w-full` | — | `rounded-full` | `py-2.5` | — | 2 | `auth/login:34` · `auth/register:45` |
| `btn-outline` | — | — | — | — | Trailing | 2 | `home:113` · `admin/dashboard:48` |
| `btn-accent btn-outline` | — | — | — | — | — | 3 | `admin/security/accounts:7` · `admin/reviews/index:10` · `admin/rider-records/index:12` |
| `btn-accent` | — | — | — | — | Trailing | 1 | `rider/register:261` |
| `btn-accent` | — | — | — | `px-3 py-2` | — | 1 | `chat/partials/dock:60` |
| `btn-accent` | `min-h-10 text-sm` | — | — | `px-4` | — | 1 | `auth/verify-contact:45` |
| `btn-accent` | `min-h-10` | — | — | — | — | 1 | `admin/categories/index:20` |
| `btn-accent` | `min-h-11` | — | — | — | Trailing | 1 | `admin/dashboard:24` |
| `btn-accent` | `min-h-12 w-full` | — | — | — | Trailing | 1 | `welcome:60` |
| `btn-ghost` | `min-h-11 min-w-11` | — | — | — | Only | 1 | `catalog/partials/filter-sidebar:29` |
| `btn-outline` | — | — | — | — | Leading | 1 | `rider/register:254` |
| `btn-outline` | `min-h-10 text-sm` | — | — | `px-4` | — | 1 | `auth/verify-contact:40` |
| `btn-outline` | `min-h-10` | — | — | — | — | 1 | `admin/categories/index:36` |
| `btn-outline` | `min-h-11` | — | — | — | — | 1 | `admin/dashboard:13` |
| `btn-outline` | `min-h-11` | — | — | `px-3 py-2` | — | 1 | `layouts/portal:69` |
| `btn-outline` | `min-h-11` | — | — | `px-4` | — | 1 | `admin/dashboard:73` |
| `btn-outline` | `text-sm` | — | — | `px-4 py-2` | Leading | 1 | `catalog/index:59` |
| `btn-outline` | `text-xs` | — | — | — | — | 1 | `admin/notifications/index:13` |
| `btn-outline` | `w-full` | — | — | — | Leading G | 1 | `auth/partials/google-access:3` |
| `btn-outline` | `w-full` | — | `rounded-full` | — | — | 1 | `auth/login:38` |
| `admin-stat` | — | — | `rounded-2xl` | — | Trailing header | 1 | `admin/dashboard:36` |
| `dropdown-item` | — | — | — | — | — | 1 | `layouts/app:102` |
| `dropdown-item` | — | — | `rounded-xl` | — | — | 1 | `layouts/app:258` |
| `dropdown-item` | `text-sm` | — | `rounded-xl` | — | — | 1 | `layouts/app:277` |
| `dropdown-item` | `w-full` | — | `rounded-xl` | — | — | 1 | `layouts/app:143` |
| `dropdown-item` | `w-full` | — | `rounded-xl` | — | Trailing | 1 | `layouts/app:267` |
| `dropdown-item` | `w-full` | `inline:error` | `rounded-xl` | — | — | 1 | `layouts/app:312` |
| `fs-card` | — | — | — | `p-4` | — | 1 | `account/profile:193` |
| `nav-link` | — | — | — | `px-3 py-1` | — | 1 | `layouts/app:72` |
| `nav-link` | — | — | — | `px-3 py-1` | Trailing | 1 | `layouts/app:81` |
| `portal-nav-link` | — | — | — | — | — | 1 | `layouts/portal:34` |
| `portal-nav-link` | — | — | — | — | Icon / text at `sm` | 1 | `layouts/portal:65` |
| `product-card-link` | — | — | — | — | Media above | 1 | `catalog/partials/card:6` |
| `theme-toggle` | — | — | — | — | Leading | 1 | `layouts/app:177` |
| `theme-toggle` | — | — | — | — | Only + badge | 1 | `components/buyer-notification-bell:3` |
| `theme-toggle` | — | — | — | — | Trailing | 1 | `layouts/app:210` |
| `theme-toggle` | — | `text-white/40` | — | — | Only | 1 | `layouts/app:465` |
| — | `text-sm` | `text-accent` | — | — | — | 38 | `welcome:64` · `account/profile-edit:6` |
| — | — | `text-accent` | — | — | — | 16 | `privacy:5` · `welcome:88` |
| — | `text-sm` | `text-accent` | — | — | Leading | 4 | `orders/index:8` · `rider/profile:6` |
| — | `font-semibold` | — | — | — | — | 3 | `fulfillments/exceptions:10` · `orders/show:16` |
| — | `font-medium text-sm` | `border text-text-muted text-white inline:accent inline:surface-border` | `rounded-full` | `px-4 py-1.5` | — | 2 | `account/profile:52` · `catalog/index:21` |
| — | `font-medium text-sm` | `inline:accent` | — | — | — | 2 | `account/profile:250` · `layouts/app:668` |
| — | `font-medium text-sm` | `text-accent` | — | — | — | 2 | `admin/dashboard:128` · `rider/profile:52` |
| — | `font-medium text-xs` | `text-accent` | — | — | — | 2 | `catalog/show:22` · `checkout/create:27` |
| — | `font-medium` | `text-accent` | — | — | — | 2 | `auth/login:43` · `auth/register:50` |
| — | `min-h-11 text-sm` | `text-accent` | — | — | — | 2 | `auth/forgot-password:5` · `auth/reset-password:5` |
| — | `text-sm` | `text-error` | — | — | — | 2 | `admin/riders/barangays:47` · `admin/riders/show:44` |
| — | `text-xs` | `text-error` | — | — | — | 2 | `orders/feedback:56` · `seller/product-form:64` |
| — | — | `inline:text-base` | `rounded-xl` | `px-3 py-2.5` | Leading | 1 | `layouts/app:572` |
| — | — | `text-error` | — | — | — | 1 | `fulfillments/index:56` |
| — | — | `text-text-muted` | — | — | — | 1 | `account/addresses/index:34` |
| — | — | `text-text-muted` | `rounded-full` | `p-1` | Only | 1 | `chat/partials/dock:9` |
| — | `font-bold text-xl` | `inline:accent` | — | — | — | 1 | `layouts/app:60` |
| — | `font-bold` | `inline:accent` | — | — | — | 1 | `layouts/portal:21` |
| — | `font-bold` | `text-accent` | — | — | — | 1 | `welcome:34` |
| — | `font-medium max-w-[160px] min-h-11 text-sm` | `border inline:surface inline:surface-border` | `rounded-full` | `px-3 py-1.5` | — | 1 | `layouts/app:160` |
| — | `font-medium min-h-14 text-[11px]` | `bg-surface-muted text-accent text-text-muted` | `rounded-xl` | — | Above | 1 | `layouts/partials/mobile-navigation:11` |
| — | `font-medium text-sm w-full` | `bg-violet-600 text-white` | `rounded-full` | `py-2.5` | — | 1 | `checkout/create:46` |
| — | `font-medium text-sm` | `inline:accent` | — | — | Trailing | 1 | `account/profile:281` |
| — | `font-medium text-sm` | `inline:accent` | — | `py-2` | Trailing | 1 | `account/profile:322` |
| — | `font-medium text-sm` | `inline:accent` | `rounded-xl` | `px-3 py-2.5` | — | 1 | `account/profile:130` |
| — | `font-medium text-xs` | `inline:accent` | — | — | — | 1 | `account/profile:271` |
| — | `font-medium text-xs` | `inline:error` | — | — | — | 1 | `rider/register:234` |
| — | `font-medium text-xs` | `inline:text-muted` | — | — | — | 1 | `cart/index:62` |
| — | `font-medium` | `inline:accent` | — | — | — | 1 | `layouts/app:153` |
| — | `font-semibold h-11 text-sm w-11` | — | `rounded-full` | — | Only | 1 | `components/carousel:71` |
| — | `font-semibold h-12 text-sm` | `bg-accent text-white` | `rounded-full` | `px-5` | Leading | 1 | `chat/partials/dock:77` |
| — | `font-semibold min-h-[44px] text-sm` | `inline:accent inline:accent-text` | `rounded-full` | `px-6 py-3` | — | 1 | `components/carousel:56` |
| — | `font-semibold min-h-10 text-sm` | `border border-error text-error` | `rounded-lg` | `px-4 py-2` | — | 1 | `admin/categories/index:50` |
| — | `font-semibold min-h-10 text-sm` | `text-accent` | — | — | — | 1 | `auth/verify-contact:66` |
| — | `font-semibold min-h-11 text-sm` | `text-accent` | — | — | — | 1 | `welcome:58` |
| — | `font-semibold min-h-11` | `text-accent` | — | — | — | 1 | `welcome:63` |
| — | `font-semibold min-h-11` | `text-accent` | — | — | Trailing | 1 | `admin/dashboard:104` |
| — | `font-semibold text-sm w-full` | `inline:accent inline:accent-subtle inline:text-muted` | `rounded-xl` | `px-3 py-2.5` | — | 1 | `account/profile:77` |
| — | `font-semibold text-sm w-full` | `text-white inline:accent` | `rounded-xl` | `py-2.5` | — | 1 | `layouts/app:644` |
| — | `font-semibold text-sm` | — | — | — | — | 1 | `orders/return-actions:15` |
| — | `font-semibold text-sm` | `bg-red-700 text-white` | `rounded-lg` | `px-4 py-2` | — | 1 | `account/profile-edit:123` |
| — | `font-semibold text-sm` | `border border-surface-border` | `rounded-lg` | `px-4 py-2` | — | 1 | `admin/riders/show:89` |
| — | `font-semibold text-sm` | `border-accent border-b-2 border-transparent text-accent text-text-muted` | — | `px-4 py-2` | — | 1 | `admin/partners/index:12` |
| — | `font-semibold text-sm` | `text-accent` | — | — | — | 1 | `account/notifications:15` |
| — | `font-semibold` | `text-accent` | — | — | — | 1 | `catalog/show:70` |
| — | `h-11 text-xl w-11` | — | `rounded-full` | — | Only | 1 | `components/carousel:95` |
| — | `h-11 w-11` | — | `rounded-full` | — | Dot only | 1 | `components/carousel:83` |
| — | `leading-none text-lg` | `text-text-muted` | `rounded-full` | `px-2 py-1` | Only | 1 | `chat/partials/dock:15` |
| — | `min-h-[76px]` | `bg-surface-muted` | — | `px-4 py-3` | — | 1 | `chat/partials/sidebar:18` |
| — | `min-h-11 text-sm` | `text-text-muted` | — | — | Leading | 1 | `welcome:30` |
| — | `min-h-11 text-xs` | `text-accent` | — | `px-2` | — | 1 | `layouts/app:142` |
| — | `text-sm w-full` | `bg-violet-600 text-white` | `rounded-full` | `py-2.5` | — | 1 | `account/addresses/_form:51` |
| — | `text-sm w-full` | `inline:text-muted` | `rounded-xl` | `px-3 py-2.5` | — | 1 | `account/profile:88` |
| — | `text-sm` | — | — | — | — | 1 | `admin/logistics/index:109` |
| — | `text-sm` | `bg-rose-50 border border-rose-300 border-surface-border text-rose-500 text-text-muted` | `rounded-full` | `px-3 py-1.5` | Leading | 1 | `catalog/show:43` |
| — | `text-sm` | `bg-violet-600 text-white` | `rounded-full` | `px-5 py-2` | — | 1 | `orders/index:39` |
| — | `text-sm` | `border border-violet-600 text-accent` | `rounded-full` | `px-4 py-2` | — | 1 | `orders/index:32` |
| — | `text-sm` | `inline:text-muted` | — | — | — | 1 | `catalog/partials/filter-form:125` |
| — | `text-sm` | `text-accent` | — | — | Only | 1 | `chat/show:14` |
| — | `text-sm` | `text-text-muted` | — | — | — | 1 | `auth/login:39` |
| — | `text-xs` | `text-text-muted` | `rounded-full` | `px-2 py-1` | — | 1 | `chat/partials/dock:14` |
| — | `w-10` | `border-l border-surface-border text-text-muted` | — | — | — | 1 | `catalog/show:99` |
| — | `w-10` | `border-r border-surface-border text-text-muted` | — | — | Only | 1 | `catalog/show:97` |
| — | `w-11` | `text-text-muted` | `rounded-xl` | — | Only | 1 | `components/password-input:4` |
| — | `w-9` | `border-l border-surface-border text-text-muted` | — | — | — | 1 | `cart/index:60` |
| — | `w-9` | `border-r border-surface-border text-text-muted` | — | — | Only | 1 | `cart/index:57` |
| — | `w-fit` | — | — | `py-2` | — | 1 | `home:33` |
| — | `w-full` | — | — | `p-3` | — | 1 | `chat/partials/dock:26` |
| `theme-toggle` | — | — | — | — | Only | 2 | `layouts/app:139` · `components/theme-toggle:1` |
| `product-save` | — | — | — | — | Only | 1 | `catalog/partials/card:43` |
| — | `text-sm font-medium` | `bg-violet-600 text-white` | `rounded-full` | `px-5 py-2` | — | 1 | `catalog/show:105` |
| — | `text-sm font-medium` | `border border-accent text-accent` | `rounded-full` | `px-5 py-2` | — | 1 | `catalog/show:106` |
| Auth switch tab | `min-h-11 text-sm font-semibold` | Muted / accent on surface when active | `rounded-lg` | `px-3` | — | 1 | `welcome:36` |
| Star rating radio tile | `h-9 w-9 sm:h-10 sm:w-10` glyph | Amber selected / muted inactive | `rounded-lg` | `p-1` | Only | 1 | `components/star-rating:7` |
| Media link | 112×112 px thumbnail | Surface border | `rounded-xl` | — | Thumbnail only | 1 | `components/review-media:9` |

The CSS still defines `carousel-btn` (36 px round surface button) with zero Blade call sites. The active carousel uses the 44 px controls above. Laravel paginator links and native browser widgets (selects, file pickers, unstyled radios and video controls) sit outside the authored button counts.

## 3. Icon inventory

| Library or format | Where and how used | Size and stroke findings |
| --- | --- | --- |
| Inline SVG glyphs | 42 SVG source tags across 20 Blade files, including the illustration tags below. Storefront and portal chrome, cart, catalog, chat, reviews, password visibility, rider wizard and dashboard stats. Some paths resemble Heroicons, notably the catalog heart. | Common glyph boxes range from 12–20 px, with 14 px chevrons and some 32 px glyphs. Outline widths include 1.5, 1.6, 1.7, 1.8, 2 and 2.5. Product tile heart is 20 px / 1.7 versus 16 px / 1.5 for detail outline. Filled hearts, stars and play/pause have no stroke. |
| Inline SVG illustrations | Cart empty state, chat empty state, product fallback and discovery art. | Mixed 64, 96 and 176×128 viewBoxes or custom illustration coordinates. Keep illustration sizing separate from the glyph scale. |
| Heroicons, Font Awesome, icon fonts and sprites | No dependency, stylesheet, font class, sprite or component import identified. The SVGs are embedded paths. | Embedded paths have no enforced default box or stroke. |
| Emoji and Unicode | Truck in `catalog/show`, pin in `shop/index`, stars in `catalog/partials/reviews`, arrows, ×, − and + in navigation and steppers. Categories use editable short text and seeded letter glyphs. | Font and emoji fallback affect optical size, line height and color. Review stars use text glyphs while the feedback form uses filled SVG stars. |
| Lettermarks and initials | FarSell F, Google G, chat/account avatars and category initials. | Sizes and shapes vary by template. Google is a text glyph. |
| Raster / image elements | Browser favicon, product photos, review attachments and the authenticator QR. | No raster UI action icon found. The favicon and content images have their own sizing requirements. |

## 4. Action areas with more than four visible controls

Counts reflect controls emitted for the stated role or state. Closed disclosure summaries count as controls. Actions hidden inside them, mutually exclusive branches, disabled placeholders, form fields and individual inventory rows do not contribute to toolbar counts. Scrollable rows can expose fewer controls at once on narrow screens.

| Page or shared area | Actions | Proposed grouping |
| --- | --- | --- |
| Admin Dashboard header · 12 | Operations, Logistics, Returns & refunds, Replacement requests, Commissions & settlements, Failed deliveries, Returns to seller, Account protection, Audit trail, Privacy requests, Manage categories, Dispatch shipments. | Keep Dispatch shipments primary. Group courier and exception queues under Delivery, returns and settlements under After-sales, and account/privacy/audit under Governance. Use the existing Categories and Operations destinations for the remaining links. |
| Admin portal navigation · 10 | Dashboard, Logistics, Products, Categories, Partners, Barangay Assignments, Rider Records, Reviews, Notifications, Dispatch. | Surface Dashboard and Dispatch. Group catalog, rider operations and oversight in labeled disclosures. |
| Seller portal navigation · 5 | Dashboard, Add Product, Orders, Chat, View Storefront. | Keep Orders and Chat reachable. Pair View Storefront and Add Product under shop/listing controls, keeping Add Product prominent on the inventory page. |
| Rider portal navigation · 6 | Dashboard, Delivery Requests, Earnings, History, Deliveries, Rider Profile. | Keep Requests and Deliveries visible. Group Earnings and History under activity and move Rider Profile into account navigation. |
| Buyer storefront desktop header · over 4 | Home/logo, Browse, Categories, Shops, conditional rider application link, search, profile or sign-in/register, cart, theme and buyer notification bell where applicable. | Distinguish browse/search, purchase tasks and account utilities. Put role application and theme under account or secondary navigation. |
| Buyer My Account mobile tab row · 9 | My Profile, My Addresses, All, To Pay, To Ship, To Receive, Completed, Cancelled, To Review. | Separate account destinations from order filters. Put the order filters under a single Orders heading. |
| Admin Notification Deliveries status toolbar · 7 | Pending, Queued, Sending, Retrying, Sent, Failed, Skipped. | Keep Failed and Retrying as triage shortcuts. Group remaining statuses into the existing status filter and keep counts available. |
| Home hero carousel · above 4 when at least 2 slides | Pause/resume, each slide dot, previous and next. | Treat the dots as one named slide selector. Consider replacing dots with a position indicator at small widths while preserving keyboard-operable Previous/Next and Pause. |
| Admin Logistics shipment action area · up to 6 in the booked API scenario with a tracking link | Track, Check provider status, Record verified pickup/delivery, Record cancellation (assigned) or Record courier delivery failure (in transit), Buyer order and history, Shipment/exception controls. | Put tracking and provider sync together. Keep the verified next action primary, move exception actions into a disclosure and pair the two history links. |

Seller dashboard's top shortcut strip has four actions for an active shop. Its separate hero contains order and listing controls. Shared dispatch actions are mostly exclusive by role and shipment status.

## 5. Repeated markup and component candidates

| Component candidate | Evidence | Contract and reuse boundary |
| --- | --- | --- |
| `x-ui.button`, `x-ui.icon-button` | Repeated `btn-*` and bespoke violet pills, destructive links, quantity controls and icon affordances across panels. | Anchor or native button, variant, size, disabled/busy state, optional leading/trailing slot, accessible name and focus ring. Forward Alpine and form attributes, including `formaction` and `formmethod`. |
| `x-ui.icon` and existing `x-theme-toggle` | Theme glyphs repeated in `layouts/app` while portal already uses `x-theme-toggle`. Heart repeated in product detail and cards, chevrons and close marks across layouts. | One path per named glyph with a small size scale, explicit decorative mode and accessible label where the icon conveys meaning alone. Reuse the existing theme component. |
| `x-ui.quantity-stepper` | `cart/index` and `catalog/show` use the same minus/input/plus structure with 36 versus 40 px controls. | Accept current min/max and a label, forward each page’s Alpine bindings and retain stock and form semantics. |
| `x-ui.section-nav` | `admin/reviews/index`, `admin/rider-records/index`, `admin/security/accounts`, `admin/partners/index` and buyer profile tab groups. | Shared visuals and active styling. Keep route links with `aria-current` distinct from Alpine tabs with tab/tabpanel semantics. |
| `x-ui.page-header`, `x-ui.filter-bar`, `x-ui.empty-state` | Back links and heading patterns, GET filters and card-based empty states recur in Admin queues, dispatch, Rider history and Seller sales. | Slots for title, copy, actions and page-specific filter fields. Preserve query keys and input names. |
| `x-ui.shipment-exception-fields` | Courier failure in `admin/logistics/index` and rider failure in `fulfillments/exceptions` duplicate reason, contact and note inputs. | Share field markup and validation messages. Leave confirmation copy, transport-specific fields, action values and CSRF at the call site. |
| Shared listing and media building blocks | `catalog/partials/card`, `components/review-media` and address `_form` already consolidate major repeated surfaces. | Extend the existing components or partials where possible. |

## 6. Performance red flags

| Area | Source evidence | Impact and confidence |
| --- | --- | --- |
| Livewire polling and component size | No `wire:poll`, Livewire views or app Livewire components found. | JavaScript polling and dense Blade pages are the relevant checks. |
| Repeated network activity | `resources/js/app.js` has 4 s chat message loops and a 10 s dock inbox refresh. Admin summary and stock refresh use 10 s timers, rider offers 15 s, buyer notification bell 30 s. | Multiple active surfaces can create overlapping traffic. Admin, Rider and bell paths already check visibility. Measure open/closed dock, background tab and slow request behavior before changing cadence. |
| Chat inbox and thread bounds | `ConversationController::conversationList()` uses unbounded `get()` for buyer and seller. The sidebar and widget both use it. Thread endpoint caps each response at 100 messages. | Inbox payload and render cost grow with account history. The same list is fetched by the widget periodically. Check client-side message accumulation separately from the bounded server response. |
| Unpaginated collections | Address book and checkout address selector load all relevant saved addresses. Admin Barangay Assignments loads the approved rider selector and matching barangays. Dispatch loads all approved riders for assignment. | Account and region growth can increase markup. Dispatch repeats rider options for every eligible shipment in a paginated result. Home feeds, profile previews, and rider offer limits are already bounded. |
| Missing intrinsic image dimensions | `catalog/show:16`, `orders/feedback:24,54` and `components/review-media:10` have no HTML width/height. Product cards and security QR specify both. | Fixed CSS boxes and aspect-ratio wrappers mitigate layout shift. Check actual sizing and transfer size, and preserve eager loading for the main product image. Review videos use `preload="metadata"`, which can still issue one request per video. |
| Shared JavaScript and third-party dependencies | Three layouts load the same Vite app bundle, Alpine, Axios bootstrap and theme script. Firebase App/Auth and reCAPTCHA are loaded in the phone verification flows. Three layouts request four Figtree weights. | Shared bundle carries page-specific behavior. Phone auth adds third-party work when invoked. No bundle-size claim without measuring compiled transfer and execution. |
| View-triggered N+1 paths | `account/profile:268` calls `Address::formatted()`, which reads four PSGC relationships. The profile loads up to three addresses without eager loading them. Saved-item controller loads `product.shop` while `catalog/partials/card:14` also reads `product.category`. | Source identifies lazy-load paths. Profile cost is capped; wishlist page can load category per card. Checkout already eager loads PSGC relations. Home/catalog/shop generally batch saved IDs and load card relations. |
| Dense rendered forms | `admin/logistics/index`, `fulfillments/index` and exception partials render multiple form trees per eligible shipment. | Pagination limits top-level rows. Inspect DOM size and repeated rider select options before adding further controls. Seller stock reads have precomputed stock in the dashboard query, so repeated calls there are not evidence of N+1. |

## 7. Prioritized fixes

| Priority | Fix | Acceptance evidence |
| --- | --- | --- |
| High | Restore visible keyboard focus on the shared `btn` family. Its `focus:outline-none` overrides the base outline. `btn-accent:focus-visible` sets outline color without restoring a visible outline. | Tab and Shift+Tab through sign-in, checkout, dispatch, application review and Rider controls in light and dark. Verify visible focus on links and buttons. |
| High | Correct light text on the dark-theme accent fill in `chat/partials/dock`, `account/profile` active mobile pill and `layouts/app` sign-out CTA. The source tokens yield about 2.7:1 for white on dark-theme violet. | Use the existing accent foreground token and verify at least 4.5:1 for normal-sized labels. Check light-theme `text-error` on white as well (about 3.8:1). |
| High | Reduce the Admin Dashboard’s 12 equal-weight destinations and group the portal navigation. | At 360, 768 and 1280 px, primary dispatch and operational queues remain easy to reach. Keyboard users can open, navigate and dismiss any disclosure. |
| Medium | Standardize button sizes, destructive styling and icon treatment. Start with auth, checkout and shipment actions. | Confirm consistent names, targets and focus in both themes. Preserve existing form endpoints and confirm flows. |
| Medium | Bound chat inbox payload and validate polling under load. | Measure inbox response size for accounts with long histories. Verify unread totals, active conversation selection and no overlapping requests. Backend contract below. |
| Medium | Eager load the relationships used by profile address previews and wishlist cards. | Compare SQL query counts as address/card counts increase. Backend contract below. |
| Medium | Simplify the logistics action area and share shipment exception fields. | Verify ready, assigned, in-transit and exception states, including all existing actions and required inputs. |
| Low | Add intrinsic image sizes where known and profile shared JS, fonts and review media. | Measure layout shifts and transfer at 360, 768 and 1280 px before and after. |
| Low | Consolidate repeated icons, page headers and filter shells. | Compare keyboard focus, glyph weights, accessible names and text wrapping across all four panels. |

### Backend Request

These contracts are for approval before implementation. This audit only documents them.

| Request | Exact contract |
| --- | --- |
| Paginate the chat inbox | Keep the existing Buyer/Seller authorization and order by most recent message. For `GET /chat`, provide a paginated `$conversations` collection and an aggregate unread total across the full authorized inbox. For `GET /chat/widget/conversations`, preserve existing fields per conversation (`id`, `name`, `product`, `preview`, `unread`, `last_message_id`) and add a backward-compatible opt-in cursor response with `data`, nullable `next_cursor` and integer `unread_total`. Apply equivalent paging to the conversation page sidebar. |
| Load relationships used by Blade | `GET /account/profile` should pass the existing buyer address preview with `psgcBarangay`, `psgcCityMunicipality`, `psgcProvince` and `psgcRegion` loaded. `GET /account/saved` should pass the existing paginated saved items with `product.category` loaded in addition to `product.shop`. Preserve current view variables and null handling. |

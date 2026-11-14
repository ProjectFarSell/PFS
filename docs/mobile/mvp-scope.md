# FarSell mobile MVP scope

**Status** — Draft for approval, 12 November 2026.

Two NativePHP apps share the FarSell Laravel API and use role-scoped Sanctum bearer tokens. The Buyer app covers discovery through receipt. The Rider app covers an approved FarSell rider’s work from availability through handover and recorded earnings.

The expected contract, `docs/api/openapi.yaml`, is absent from this checkout. It remains the source of truth when supplied. [API routes](../../routes/api.php) and the [catalog controller](../../app/Http/Controllers/Api/V1/CatalogController.php) show one product-facing JSON operation, `GET /api/v1/catalog`. The health route is also present. All other API operation names below refer to **Backend Requests** with proposed paths and shapes. Reconcile them with OpenAPI before implementation. The [web routes](../../routes/web.php) and linked Blade views establish web behavior. No Buyer or Rider Livewire views were found in this checkout.

## Shared launch behavior

- Inject API origin and link domains per local, staging, and production build. Keep secrets out of source and app bundles. Keep tokens in Keychain/Keystore-backed secure storage and clear role-specific data on sign-out. On expiration, ask the user to sign in and restore the intended destination after checking access.
- Show progress for first loads and submissions. Preserve valid input on recoverable errors and explain what can be retried. On reconnect, fetch authoritative state before retrying checkout or shipment actions with an uncertain result. Use the same idempotency key when replaying the same action. Keep unresolved actions visible until acknowledged, resolved, or explicitly discarded.
- Label stale cached data with its last refresh time. Keep cached sensitive data to a minimum and purge it on sign-out. Keep interrupted rider proof drafts in protected app-private storage with a visible retry or discard action. Delete drafts on confirmation, explicit discard, or within 24 hours of creation. Request the PIN again if needed after interruption. Keep PINs, credentials, and personal delivery details out of logs and push payloads.
- Give icon-only controls accessible labels, use touch targets of at least 44 × 44 points or dp, support dynamic text and screen readers, and meet WCAG AA contrast. Announce progress and errors, and convey status with text as well as color.

## Buyer app

### User stories

**Must**

- As a buyer, I can create an account, review the privacy notice and consent, verify my contact, and sign in with any required security challenge so I can make a purchase.
- As a shopper, I can browse and search the public catalog, open a product, choose a valid variant and quantity, and see availability and price.
- As a buyer, I can add, remove, and change quantities in my cart and resume checkout after sign-in with my selections intact.
- As a buyer, I can select or add a delivery address, review shipment charges and totals, choose an available payment method, and place an order once. Offer **cash on delivery (`cod`)** for real purchases and **Card / e-wallet (`gateway_stub`)** in staging only when the backend enables it. The web stub never charges a card or wallet. [Web checkout](../../resources/views/checkout/create.blade.php)
- As a buyer, I can see my order list and follow each shop shipment’s timeline, open a courier tracking link where supplied, and view the internal delivery PIN at the handover stage. I can confirm receipt for each delivered shipment so it can be completed.
- As a buyer, I can opt into push for order and shipment updates, tap through to the correct order, and check my order history even if I decline push.
- As a buyer who signed up in the app, I can initiate account deletion from Account for public store release. [Apple App Review Guidelines, section 5.1.1](https://developer.apple.com/app-store/review/guidelines/#privacy)

**Should**

- As a returning buyer, I can view and mark order notifications read in an in-app inbox.
- As a buyer, I can use saved items or Buy now to reach a purchase faster.
- As a buyer, I can recover my password and manage saved addresses beyond adding or choosing one at checkout. For MVP, offer a configured web recovery link if native recovery is deferred.

**Later**

- As a buyer, I can chat with sellers, write reviews, rate delivery, and attach review media.
- As a buyer, I can manage cancellations, returns, refunds, and replacement requests in the app.
- As a buyer, I can pay through a real card or wallet gateway once the backend and payment operations support it.

### Navigation

```text
Buyer app
├── Authentication stack (opened from protected routes)
│   ├── Sign in
│   ├── Create account
│   ├── Sign-in challenge (conditional)
│   └── Verify contact (conditional)
└── Main tabs
    ├── Browse stack
    │   ├── Browse and Search
    │   └── Product detail
    ├── Cart stack
    │   ├── Cart
    │   └── Checkout
    │       ├── Add address (modal)
    │       └── Order detail and tracking (successful order opens Orders)
    ├── Orders stack
    │   ├── Orders
    │   └── Order detail and tracking (push destination)
    │       └── Proof photo (modal, when present)
    └── Account stack
        └── Account
            └── Delete account (confirmation modal, public release)
```

After sign-in, return to the initiating checkout or protected deep link if it still resolves for the signed-in buyer. An unknown or inaccessible order link opens Orders with an explanation. A checkout success opens the created order in the Orders stack.

### Screens

Each entry gives the purpose, data operations and primary action, followed by all four states.

- **Sign in** — Establish a Buyer session. Data `Login`. Primary action **Sign in**. **Loading** submits credentials. **Empty** shows the blank form and Create account. **Error** shows invalid credentials, wrong role, throttling, or retry. **Offline** shows reconnect guidance and retains only the identifier.
- **Create account** — Collect name, email, password, and privacy consent. Data `RegisterBuyer`. Primary action **Create account**. **Loading** submits once. **Empty** shows the blank form and privacy notice. **Error** maps validation errors and offers Sign in for an existing account. **Offline** keeps non-secret input and waits to submit.
- **Sign-in challenge** — Complete any server-required OTP or authenticator check. Data `SendLoginCode`, `VerifyLoginChallenge`. Primary action **Verify**. **Loading** shows send or verify progress. **Empty** asks for the code and shows resend where eligible. **Error** handles invalid or expired challenges and resend cooldowns. **Offline** shows reconnect and restart options if the challenge expires.
- **Verify contact** — Select a supported contact channel and verify the code. Data `GetSession`, `SendContactCode`, `VerifyContact`. Primary action **Verify contact**. **Loading** loads available channels or submits verification. **Empty** shows eligible channels or offers retry if none are returned. **Error** explains invalid, expired, or throttled codes. **Offline** preserves the selected channel and allows retry after reconnect.
- **Browse and Search** — Discover purchasable products. Data observed `GET /api/v1/catalog` with `q`, `page`, and `sort`; product cards use name, image, price, stock and shop. Primary action **Open product**. **Loading** shows cards or a pagination indicator. **Empty** distinguishes an empty catalog from no search matches. **Error** retries the current query. **Offline** shows cached results when available and explains that new results need a connection.
- **Product detail** — Inspect listing, images, price and stock by variant. Data `GetProduct`, `AddCartLine` for a signed-in buyer. Primary action **Add to cart**. **Loading** fetches detail or adds a line. **Empty** handles removed listings and unavailable variants. **Error** preserves selection and reloads current stock on conflict. **Offline** shows stale detail and keeps selection for review before adding.
- **Cart** — Review variants, quantity, and current subtotal. Data `GetCart`, `SetCartLine`, `MergeGuestCart` on sign-in. Primary action **Checkout**. **Loading** retrieves cart or saves a line. **Empty** offers Browse. **Error** flags stock or price changes per item and gives a retry. **Offline** shows saved items and pending edits, with checkout paused until totals refresh.
- **Checkout** — Review address, shop shipments, fees and server-enabled payment methods. Data `ListAddresses`, `QuoteCheckout`, `PlaceOrder`, and `GetOrder` for an order already identified. Primary action **Place order**. **Loading** shows quote or submit progress and prevents duplicate taps. **Empty** offers Add address or returns to Cart if it is empty. **Error** preserves choices and asks for review after a quote or stock change. **Offline** keeps the draft, shows any order whose submission outcome is pending, and reconciles it before allowing a new submission.
- **Add address** — Save a Philippine delivery address and return it selected. Data `ListAddressLocations`, `CreateAddress`. Primary action **Save address**. **Loading** shows progress for dependent region, province, city/municipality and barangay choices or save. **Empty** prompts the user to select a parent or retry an empty directory. **Error** retains address fields and shows specific validation errors. **Offline** keeps the draft and pauses validation and save.
- **Orders** — See purchase history and current status. Data `ListOrders`. Primary action **Track order**. **Loading** shows order placeholders. **Empty** offers Browse products. **Error** retries history and retains any displayed list. **Offline** shows cached summaries with their last refresh time.
- **Order detail and tracking** — Show current order total, separate shipment amounts, timelines, retry and return states, authorized in-transit PIN, and external courier link if provided. Data `GetOrder`, `ConfirmReceipt`. Primary action **Refresh status**, or **Confirm received** for an eligible delivered shipment. **Loading** shows detail or confirmation progress. **Empty** handles an unavailable order or a shipment timeline with no events. **Error** distinguishes inaccessible links from retryable failures. **Offline** shows cached summaries and hides PIN and receipt submission until refreshed. [Web shipment detail](../../resources/views/orders/fulfillments.blade.php)
- **Proof photo** — Inspect evidence for an owned shipment. Data `GetProofPhoto`. Primary action **View photo**. **Loading** shows image progress. **Empty** says no photo is available. **Error** retries or closes an inaccessible photo. **Offline** offers reconnect with a return to tracking.
- **Account** — View session, privacy notice, order alert preference, and sign out. Data `GetSession`, `SignOut`, `RegisterPushDevice`, `RemovePushDevice`, and OS notification status. Primary action **Manage order alerts**. **Loading** shows session or setting progress. **Empty** shows Sign in when signed out. **Error** explains failed registration or offers Settings if OS permission was denied. **Offline** shows local settings and pending sync, including any unconfirmed server revocation after local sign-out.
- **Delete account** — Explain deletion consequences and let the buyer initiate deletion. Data `RequestAccountDeletion` and `GetSession` for current request status. Primary action **Request account deletion**. **Loading** submits the request once. **Empty** shows the explanation and confirmation input. **Error** shows verification requirements or retry. **Offline** keeps the modal available and pauses submission.

### Web features outside Buyer MVP

- **Saved items, Buy now, shop directory, and advanced filters** — The catalog, search, and cart cover the purchase path. These shortcuts and discovery features can follow. [Product view](../../resources/views/catalog/show.blade.php), [web routes](../../routes/web.php)
- **Seller chat and review/rating submission with media** — Defer messaging and moderation workflows. [Product view](../../resources/views/catalog/show.blade.php), [feedback view](../../resources/views/orders/feedback.blade.php)
- **Self-service cancellations, returns, refund and replacement requests, invoices, and packing slips** — Keep these in the web account. The app still shows current shipment outcomes and amounts. [Order view](../../resources/views/orders/show.blade.php), [return actions](../../resources/views/orders/return-actions.blade.php)
- **Google sign-in, full profile/security management, and the notification inbox with read history** — Keep web parity for a later pass. Required account challenges, order push, privacy access, and sign-out remain in launch scope. [Web portal](../../resources/views/welcome.blade.php), [web notifications](../../resources/views/account/notifications.blade.php)

### Native capabilities and prompts

- **Push and verified deep links — Must.** Offer **Enable order alerts** on the first successful order screen and in Account. App copy “Get notified when your order or delivery status changes.” Buttons **Enable alerts** and **Not now**. On Enable, request OS permission on iOS and on Android versions requiring it. System wording is controlled by the OS. If denied, keep order tracking available and offer **Open Settings** on a later explicit request. A push opens an authenticated order route using an opaque order ID. Universal Links / Android App Links use the build’s configured domain and require no in-app runtime permission. [Apple notification authorization](https://developer.apple.com/documentation/usernotifications/asking-permission-to-use-notifications), [Android notification permission](https://developer.android.com/develop/ui/compose/notifications/notification-permission)
- **Keychain/Keystore — Must.** Save the token after successful authentication. Secure storage by itself adds no permission prompt. Optional biometric unlock is deferred, so no Face ID, Touch ID, or fingerprint prompt at launch. [NativePHP secure storage](https://nativephp.com/plugins/nativephp/mobile-secure-storage)
- **Camera, uploads, foreground and background location — Outside launch scope.** Addresses use manual entry and the address directory. Shipment tracking uses server events. Viewing a protected proof image stays inside the app. These flows trigger no camera, photos, file, or location permission.

## Rider app

### User stories

**Must**

- As an approved rider, I can sign in and complete required security challenges. If I lack approval, I see how to access the existing web workflow.
- As a rider, I can go available or unavailable for new work while keeping accepted assignments accessible.
- As a rider, I can view assigned shipments and eligible offers, then accept or decline a live offer. I see its expiry and learn if it was taken or expired. Pickup and recipient details become available after assignment. [Web delivery requests](../../resources/views/rider/delivery-requests.blade.php)
- As a rider, I can open turn-by-turn directions to pickup and drop-off in the device’s maps app and copy the address if navigation is unavailable.
- As a rider, I can confirm pickup, then confirm delivery using the buyer’s six-digit PIN, recipient name, handover note, and COD acknowledgment when due. I can attach an optional proof photo and recover an interrupted submission. [Web fulfillment controls](../../resources/views/fulfillments/index.blade.php)
- As a rider, I can report an unsuccessful attempt and see retry or return instructions. If I already collected cash or the workflow is blocked, I can find dispatch support.
- As a rider, I can opt into push for new offers and assignments and open the current offer or shipment from an alert. The offers list remains available when permission is denied.
- As a rider, I can see recorded earnings and recent credits. Earnings are credited on buyer-confirmed completion. Failed attempts and returns do not generate credits. [Web earnings](../../resources/views/rider/earnings.blade.php)

**Should**

- As a rider, I can search completed shipment history and review the full event trail.

**Later**

- As a rider, I can apply, update my profile, and upload onboarding documents in the app.
- As a rider, I can use optional biometric unlock or see a route in the app. Background location depends on a separately approved dispatch feature.

### Navigation

```
Rider app
├── Authentication stack
│   ├── Sign in
│   └── Sign-in challenge (conditional)
└── Main tabs
    ├── Deliveries stack
    │   ├── Deliveries (availability toggle, Assigned and Offers segments)
    │   ├── Offer detail (modal)
    │   └── Shipment detail (Pickup → Drop-off)
    │       ├── Device maps (external handoff)
    │       ├── Proof of delivery (modal)
    │       │   └── Camera / photo picker (system surface)
    │       └── Report unsuccessful attempt (modal)
    ├── Earnings stack
    │   └── Earnings
    └── Account stack
        └── Account
```

For rider push, links resolve to an offer or shipment after authentication and a fresh access check. An expired offer or revoked assignment routes to Deliveries with an explanation.

### Screens

- **Sign in** — Establish a Rider session and check approval. Data **Login**, **GetRiderStatus**. Primary action **Sign in**. **Loading** validates credentials and role. **Empty** shows the blank form. **Error** distinguishes invalid credentials and missing approval. **Offline** shows reconnect guidance and retains only the identifier.
- **Sign-in challenge** — Complete any required OTP or authenticator check. Data **SendLoginCode**, **VerifyLoginChallenge**. Primary action **Verify**. **Loading** shows send or verify progress. **Empty** asks for the code and offers resend when eligible. **Error** explains invalid or expired challenges and cooldowns. **Offline** offers restart after reconnect if needed.
- **Deliveries** — Manage availability and switch between assigned work and eligible offers. Data **GetRiderStatus**, **SetRiderAvailability**, **ListRiderShipments**, **ListRiderOffers**. Primary action **Open shipment or offer**. **Loading** refreshes lists or saves availability. **Empty** gives separate no-assignment and no-offer messages. **Error** restores last confirmed availability and offers refresh. **Offline** labels last confirmed availability and cached assignments and disables decisions on stale offers.
- **Offer detail** — Review shop, pickup area and expiry before deciding. Data **ListRiderOffers**, **RespondToRiderOffer**. Primary action **Accept offer**, with **Decline** available. **Loading** submits one decision. **Empty** explains an expired or removed offer. **Error** refreshes on conflict and reconciles an uncertain response. **Offline** shows the last known deadline and requires reconnecting for a live decision.
- **Shipment detail** — Show items, pickup and recipient details, addresses, COD, and current step. Data **GetRiderShipment**, **RecordPickup**. Primary action **Confirm pickup** when assigned, then **Record delivery** once in transit. **Open directions** passes the relevant address or coordinates to maps. **Loading** retrieves shipment or submits pickup. **Empty** covers withdrawn assignments and missing addresses with dispatch contact where available. **Error** refreshes invalid transitions and gives an address fallback when maps fails. **Offline** shows any authorized cached address and an unconfirmed pickup action with retry after reconciliation.
- **Proof of delivery** — Collect recipient, six-digit PIN, handover note, COD acknowledgment when due, and optional photo. Data **GetRiderShipment**, **RecordDelivery**. Primary action **Confirm delivered**. **Loading** shows upload and submission progress. **Empty** shows the blank form and optional photo placeholder. **Error** identifies invalid PIN, file or status and keeps permitted draft fields for retry. **Offline** keeps the protected draft and labels delivery as pending confirmation.
- **Report unsuccessful attempt** — Capture reason, contact method and note and show the next instruction. Data **GetRiderShipment**, **ReportDeliveryFailure**. Primary action **Record failed attempt**. **Loading** submits the report. **Empty** shows choices or explains when reporting is unavailable. **Error** preserves the draft and routes cash-collected cases to dispatch. **Offline** keeps an unsent draft for retry after refreshing. [Web exception controls](../../resources/views/fulfillments/exceptions.blade.php)
- **Earnings** — Show recorded credits in PHP and recent entries with order, shipment, amount and credit date. Data **GetRiderEarnings**. Primary action **Refresh earnings**. **Loading** shows placeholders. **Empty** shows zero and explains when credits appear. **Error** offers retry and retains the last confirmed total. **Offline** shows the cached ledger with a last-refresh timestamp.
- **Account** — Show approval, sign-out and alert settings for rider alerts. Data **GetSession**, **GetRiderStatus**, **SignOut**, and optionally **RegisterPushDevice** and **RemovePushDevice**. Primary action **Sign out**. **Loading** loads account or changes settings. **Empty** shows Sign in when the session is missing. **Error** offers retry or Settings as appropriate. **Offline** allows local sign-out and reports unconfirmed server revocation.

### Web features outside Rider MVP

- **Application, application updates, document uploads, and full profile** — Keep onboarding and approval on the web so the app can start with approved riders. [Application](../../resources/views/rider/register.blade.php), [profile](../../resources/views/rider/profile.blade.php)
- **Full history and advanced status filters** — Active assignments, offers and the earnings ledger cover daily work. Historical timelines remain on the web. [Dashboard](../../resources/views/rider/dashboard.blade.php), [history](../../resources/views/rider/history.blade.php)
- **Manual dispatch, retry scheduling, and return-receipt management** — Keep admin and seller operations in their web panels. Riders report unsuccessful attempts and follow resulting instructions. [Fulfillment controls](../../resources/views/fulfillments/index.blade.php), [exception controls](../../resources/views/fulfillments/exceptions.blade.php)

### Native capabilities and prompts

- **Camera and file upload — Must for the optional proof photo.** On **Take proof photo**, show “Allow camera access to take a photo of the delivery handover.” Use that reason for the iOS camera usage description and as the Android app rationale if runtime permission is needed. Request access at this tap. Android supplies the system dialog wording. If denied, offer **Choose photo**, **Open Settings**, or continue with PIN, recipient and note. **Choose photo** opens the system picker on tap with access to the selected file and no broad photos or storage permission. Preview the file before upload. [Apple protected resources](https://developer.apple.com/documentation/bundleresources/protected-resources), [Android Photo Picker](https://developer.android.com/training/data-storage/shared/photo-picker)
- **Location and map handoff — Must for directions.** On **Open pickup directions** or **Open drop-off directions**, pass the destination to maps. No FarSell foreground or background location prompt is needed. The maps app may request its own location access using its own wording. If maps or location is unavailable, show **Copy address**. [Android location permissions](https://developer.android.com/develop/sensors-and-location/location/permissions)
- **Push and verified deep links — Must.** After the rider first goes available successfully or taps **Enable delivery alerts** in Account, show “Get notified when you receive a delivery offer or assignment.” Buttons **Enable alerts** and **Not now**. On Enable, request OS permission where required. The OS controls the dialog wording. On denial, keep the offers list with refresh and later Settings access. Links require no runtime permission and recheck the offer or assignment. [Apple notification authorization](https://developer.apple.com/documentation/usernotifications/asking-permission-to-use-notifications), [Android notification permission](https://developer.android.com/develop/ui/compose/notifications/notification-permission)
- **Keychain/Keystore — Must.** Store the rider session token securely after sign-in. Secure storage triggers no separate permission prompt. Biometrics are deferred and trigger no prompt in MVP. [NativePHP secure storage](https://nativephp.com/plugins/nativephp/mobile-secure-storage)

## Backend Requests

These proposed operations cover the screens above. Use the observed catalog operation for Browse and Search. Web route names are evidence for behavior and do not imply a mobile API contract.

In the shapes below, IDs are opaque strings for new operations, with the observed catalog ID accepted as the product lookup identifier. Amounts are integer PHP centavos, timestamps are ISO 8601, and nullable fields are marked with “?”. A list response is {data: item[], next_cursor: string?}; list requests accept cursor? unless specified otherwise. A request marked “none” has no body. Success returns JSON with HTTP 200 unless a different status is specified. Proposed protected routes require a role-scoped bearer token and resource ownership or an active assignment. Error envelope is {message: string, code: string, errors?: map of field to string[]}. Use 401 for missing or expired authentication, 403 for ineligible roles, 404 for inaccessible resources, 409 for stale or conflicting state, 422 for field errors, and 429 with Retry-After for throttling.

Require Idempotency-Key on order creation and retryable resource mutations that create a record or transition a shipment. Repeating the same payload with the same key returns the prior outcome before applying current-state checks. A reused key with a different payload returns 409. For absolute-value updates, return the resulting state so the app can reconcile a timeout.

### Identity and push

- **Login — POST /api/v1/auth/login.** Request {email, password, app: buyer|rider, device_name}. Response 200 {token, token_type: Bearer, expires_at?, account: Account} or 202 {challenge_id, methods: (email_otp|phone_otp|totp)[], expires_at}. Apply the same eligibility and challenge policy as web sign-in and scope the resulting token to the app. [Web login](../../app/Http/Controllers/Auth/LoginController.php)
- **RegisterBuyer — POST /api/v1/auth/buyers.** Request {name, email, password, password_confirmation, privacy_consent: true, privacy_notice_version, device_name}. Response 201 with the same session fields as Login and any pending verification in Account.
- **SendLoginCode — POST /api/v1/auth/login-challenges/{challenge_id}/code.** Request {method: email_otp|phone_otp}. Response 202 {masked_destination, expires_at, resend_at}. Authenticator codes require no send operation.
- **VerifyLoginChallenge — POST /api/v1/auth/login-challenges/{challenge_id}/verify.** Request {method: email_otp|phone_otp|totp, code: string}. Response 200 {token, token_type: Bearer, expires_at?, account: Account}.
- **GetSession — GET /api/v1/auth/session.** Request none. Response Account. **Account** is {id, name, email, role: buyer|rider, verified_contact: boolean, verification_channels: (email|phone)[], deletion_request_status?}.
- **SendContactCode — POST /api/v1/auth/contact-verification/code.** Request {channel: email|phone, phone?}. Response 202 {verification_id, masked_destination, expires_at, resend_at}. **VerifyContact — POST /api/v1/auth/contact-verification/{verification_id}/verify.** Request {code: string}. Response {account: Account}. Confirm any phone-provider credential exchange in OpenAPI.
- **SignOut — DELETE /api/v1/auth/session.** Request none. Response 204, with current token revoked and this session’s push registration unbound. Local sign-out clears credentials immediately. Server revocation can be confirmed only while online with valid authorization.
- **RequestAccountDeletion — POST /api/v1/account/deletion-requests.** Request {confirmed: true, reauth_token?}. Response 202 {id, status: pending, submitted_at}. If recent authentication is required, return 403 with code reauthentication_required. Resolve the confirmation mechanism with the existing privacy workflow.
- **RegisterPushDevice — PUT /api/v1/push/devices/{installation_id}.** Request {platform: ios|android, app: buyer|rider, provider: fcm, push_token, enabled_events: string[]}. Response {installation_id, enabled_events, updated_at}. Upsert on token rotation and rebind safely if the account changes. **RemovePushDevice — DELETE /api/v1/push/devices/{installation_id}.** Request none. Response 204.
- **Push event payload.** Buyer order or shipment event {event_id, event_type: order_placed|shipment_status_changed, order_id, shipment_id?}. Rider event {event_id, event_type: offer_received|shipment_assigned, offer_id?, shipment_id?, expires_at?}. Alerts carry a generic user-visible title and body. Dispatch from committed events, deduplicate by event ID, and avoid showing expired offers as actionable. [NativePHP push notifications](https://nativephp.com/docs/mobile/4/digging-deeper/push-notifications)

### Buyer commerce and tracking

- **GetProduct — GET /api/v1/products/{id}.** Request none. Response {id, name, description, images: URL[], shop: {id, name}, price_cents, compare_at_price_cents?, stock, variants: {id, sku, options: map of string to string, price_cents, stock}[]}.
- **GetCart — GET /api/v1/cart.** Request none. Response Cart. **AddCartLine — POST /api/v1/cart/lines.** Request {product_id, variant_id?, quantity}. Response 201 Cart. **SetCartLine — PUT /api/v1/cart/lines/{line_id}.** Request {quantity}, with 0 removing the line. Response Cart. **MergeGuestCart — POST /api/v1/cart/merge.** Request {lines: {product_id, variant_id?, quantity}[]}. Response {cart: Cart, rejected_lines: {product_id, variant_id?, quantity, reason}[]}. Merge once and preserve rejected lines locally until the buyer reviews them. **Cart** is {version, lines: {id, product_id, variant_id?, name, sku?, quantity, available_quantity, unit_price_cents, line_total_cents}[], subtotal_cents}. Guest edits use local cart data and merge on sign-in.
- **ListAddresses — GET /api/v1/addresses.** Request none. Response {data: Address[]}. **CreateAddress — POST /api/v1/addresses.** Request {label?, line1, region_id, province_id, city_municipality_id, barangay_id, postal_code?, phone?, is_default: boolean}. Response 201 Address. **Address** is the submitted fields plus {id, formatted}. **ListAddressLocations — GET /api/v1/address-locations.** Query {level: region|province|city_municipality|barangay, parent_id?}, with parent required below region. Response {data: {id, name}[]}. [Web address form](../../resources/views/account/addresses/_form.blade.php)
- **QuoteCheckout — POST /api/v1/checkout/quotes.** Request {address_id, cart_version}. Response {quote_id, expires_at, subtotal_cents, shipping_cents, total_cents, shipments: {shop_id, shop_name, shipping_cents, total_cents}[], payment_methods: (cod|gateway_stub)[]}. Production returns only enabled real methods. **PlaceOrder — POST /api/v1/orders.** Request {quote_id, payment_method: cod|gateway_stub}. Response 201 {order: OrderSummary}. Reject a stale quote or altered cart with 409 and require a fresh review.
- **ListOrders — GET /api/v1/orders.** Query {cursor?}. Response paginated OrderSummary. **GetOrder — GET /api/v1/orders/{id}.** Request none. Response OrderSummary plus {shipments: BuyerShipment[]}. **OrderSummary** is {id, number, created_at, status, payment_method, total_due_cents}. **BuyerShipment** is {id, shop_name, status, items: Item[], shipping_cents, amount_due_cents, events: Event[], rider_name?, retry_at?, returned_at?, rejection_reason?, cod_collected_at?, delivery_pin?, can_confirm_receipt: boolean, courier?: {name, reference?, tracking_url?}, proof?: {recipient_name, note, photo_available: boolean}}. **Item** is {name, sku?, variant_options: map of string to string, quantity, line_total_cents?}. **Event** is {status, occurred_at, note?}. Return the PIN only to the owning buyer for the applicable in-transit FarSell shipment. Use the server’s totals and statuses for split orders.
- **ConfirmReceipt — POST /api/v1/orders/{order_id}/shipments/{shipment_id}/receipt.** Request none. Response {order: OrderSummary, shipment: BuyerShipment}. Applies completion once and lets the existing earnings rules run.
- **GetProofPhoto — GET /api/v1/orders/{order_id}/shipments/{shipment_id}/proof-photo.** Request none. Response 200 private image bytes with their image MIME type and Cache-Control private, no-store, or 404 for an unavailable photo. Scope to the owning buyer.

### Rider dispatch, proof, and earnings

- **GetRiderStatus — GET /api/v1/rider/status.** Request none. Response RiderStatus. **SetRiderAvailability — PUT /api/v1/rider/availability.** Request {is_available: boolean}. Response RiderStatus. **RiderStatus** is {approval_status, is_available, updated_at}. Existing assignments remain active when availability is turned off.
- **ListRiderOffers — GET /api/v1/rider/offers.** Query {cursor?}. Response {data: Offer[], next_cursor?, server_time}. **Offer** is {id, shipment_id, shop_name, pickup_area, expires_at}. Show server-authorized offers only and keep recipient and contact details private before assignment. The web currently describes a two-minute offer.
- **RespondToRiderOffer — POST /api/v1/rider/offers/{id}/decision.** Request {decision: accept|decline}. Response {offer_id, decision, decided_at, shipment_id?}, with shipment ID on accept. Return 409 with code offer_expired or offer_unavailable when relevant. Accept assigns atomically.
- **ListRiderShipments — GET /api/v1/rider/shipments.** Query {cursor?, status?}; default to active assigned FarSell shipments. Response paginated {id, order_number, shop_name, status, updated_at}. **GetRiderShipment — GET /api/v1/rider/shipments/{id}.** Request none. Response RiderShipment. **RiderShipment** is {id, order_number, shop_name, status, updated_at, items: Item[], pickup: Stop, dropoff: Stop, payment_method, cod_due_cents, cod_collected_at?, events: Event[], allowed_actions: string[], retry_at?, instructions?, dispatch_contact?}. **Stop** is {address?, contact_name?, contact_phone?, latitude?, longitude?}. Supply usable addresses for launch coverage. Coordinates are optional and require confirmation of source and accuracy. Drop-off data excludes the delivery PIN.
- **RecordPickup — POST /api/v1/rider/shipments/{id}/pickup.** Request none. Response RiderShipment reflecting in_transit (shown as Picked up) after a valid assigned pickup.
- **RecordDelivery — POST /api/v1/rider/shipments/{id}/delivery.** Multipart request {delivery_recipient_name: string up to 120 characters, delivery_pin: six-digit string, delivery_note: string up to 1,000 characters, cod_collected: boolean when COD is due, delivery_photo?: JPEG/PNG/WebP up to 4 MiB}. Response {shipment: RiderShipment, proof: {recorded_at, photo_available: boolean}}. Validate assignment, status, PIN, and applicable COD together. A successful result reflects delivered status. [Web validation](../../app/Http/Controllers/FulfillmentController.php)
- **ReportDeliveryFailure — POST /api/v1/rider/shipments/{id}/failed-attempts.** Request {failure_reason: unreachable|refused|buyer_wrong_variant|seller_wrong_item|address_issue|other, contact_method: phone|chat|doorstep, exception_note: string of 10–1,000 characters}. Response 201 {shipment: RiderShipment, attempt_id, recorded_at}. Return current retry or return instructions when available. Preserve the web rule requiring dispatch assistance after cash collection.
- **GetRiderEarnings — GET /api/v1/rider/earnings.** Query {cursor?}. Response {currency: PHP, total_earned_cents, data: {shipment_id, order_number?, amount_cents, earned_at}[], next_cursor?}. Report credited earnings separately from any payout system.

## Decisions and outstanding questions

- The contract comes first. Review and approve the proposed OpenAPI document before backend or mobile implementation. Existing routes remain the implementation evidence until the approved contract is implemented.
- Token decision for review — issue per-device Sanctum tokens with a 30-day fixed lifetime and server-side revocation on sign-out, account suspension, security changes, or administrator action. Require sign-in and the same active web challenges on expiry. There are no refresh tokens. The current Sanctum configuration sets global expiration to null, so per-token expiry needs backend implementation. Schedule rider reauthentication when possible and preserve unresolved work.
- Money — product, order, fulfillment, and rider earnings columns use decimal pesos. Settlement records use integer centavos. New API fields ending in _cents require exact decimal-to-integer conversion at the API boundary without floating-point rounding. The existing catalog returns decimal peso price fields, so preserve that observed endpoint shape. [Marketplace migration](../../database/migrations/2024_01_01_000003_create_marketplace_tables.php), [earnings migration](../../database/migrations/2026_10_29_000004_create_rider_earnings_and_delivery_ratings.php), [settlement migration](../../database/migrations/2026_11_06_000001_add_delivery_exceptions_and_settlements.php)
- COD remittance is outside the mobile MVP. Dispatch is the proposed owner, subject to operational confirmation. Existing collection and settlement records do not establish that cash was remitted.
- Live rider location and background GPS are outside v1. External maps and copyable addresses meet the approved navigation scope. Evaluate the framework against secure storage, push delivery and link handling, camera and upload, offline retry, and maps handoff.
- Buyer push covers order placement and each shipment status change. Rider push covers offers and assignments. Production checkout offers COD, and staging may enable the card/e-wallet stub.
- Keep the optional proof photo and purge local proof drafts on confirmation, explicit discard, or within 24 hours. Direct PIN and COD issues to dispatch. Include external courier tracking and failed-attempt reporting. Offer web account recovery and in-app Buyer deletion. Match the web’s active verification and sign-in challenges.

Open questions remain for the dispatch contact and remittance procedure, phone-verification provider exchange, deletion reauthentication mechanism, and who verifies missing pickup or drop-off addresses.
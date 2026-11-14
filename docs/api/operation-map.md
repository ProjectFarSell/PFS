# Mobile API operation map

The 45 base operations and 15 additional Buyer operations are implemented as of November 13, 2026. The table below preserves the original B1 estimates and service-extraction plan as a historical reference. Current routes and response shapes are in `openapi.yaml`; implementation evidence and external acceptance gates are in `audit-report.md`.

| Group | Operation | Effort | Risk | Reuse / extraction |
|---|---|---|---|---|
| Existing | `GET /health` | S | Low | routes/api.php |
| Existing | `GET /catalog` | S | Low | CatalogController::index |
| Identity | `POST /auth/login` | M | High | Extract primary checks from Auth/LoginController; reuse AccountSecurity and SignInOtp |
| Identity | `POST /auth/register` | M | High | Extract Auth/RegisterController validation/creation; PrivacyNotice; AccountSecurity; SignInOtp |
| Identity | `POST /auth/challenges/send` | M | High | SignInOtp::issue; refactor request-session context into reusable challenge context |
| Identity | `POST /auth/challenges/verify` | M | High | SignInOtp::verifyEmail / verifyPhone; FirebasePhoneVerificationService; AccountSecurity identity fingerprint |
| Identity | `GET /session` | S | Medium | User; AccountSecurity fingerprint; riderProfile |
| Identity | `POST /auth/logout` | S | Medium | Sanctum token deletion; new device registration binding service |
| Identity | `POST /auth/reauthenticate` | M | High | Extract password/eligibility checks; AccountSecurity recent confirmation policy |
| Identity | `POST /account/contact-verification/send` | M | Medium | ContactVerificationService; FirebasePhoneVerificationService |
| Identity | `POST /account/contact-verification/verify` | M | Medium | ContactVerificationService::verify; FirebasePhoneVerificationService; extract locked phone freshness checks |
| Identity | `POST /account/deletion-requests` | M | High | Extract Account/PrivacyController::requestDeletion transaction; AccountDeletionRequest |
| Push | `PUT /devices/{installation_id}` | M | High | New device registration service; Sanctum currentAccessToken |
| Push | `DELETE /devices/{installation_id}` | M | Medium | New device registration service |
| Buyer catalogue | `GET /products` | M | Medium | Extract CatalogController filters; Product::visible and withAvailableStock |
| Buyer catalogue | `GET /products/{product_id}` | S | Medium | Product::visible; ProductVariant::effectivePrice; Product::imageUrl |
| Buyer cart | `GET /buyer/cart` | M | Medium | Extract Cart hydration into session-independent shared service; new per-user mobile cart storage |
| Buyer cart | `POST /buyer/cart/lines` | M | High | Extract CartController::purchaseLine; Cart line hydration |
| Buyer cart | `PATCH /buyer/cart/lines/{line_id}` | M | Medium | Extract CartController::update purchase policy |
| Buyer cart | `POST /buyer/cart/merge` | M | High | Shared cart purchase policy; new durable merge receipt |
| Buyer addresses | `GET /buyer/addresses` | S | Medium | User::addresses; Address relations |
| Buyer addresses | `POST /buyer/addresses` | M | Medium | Extract AddressController::validatedAddress and default-address transaction |
| Buyer addresses | `PUT /buyer/addresses/{address_id}` | M | Medium | Shared address validation; owner-scoped lookup |
| Buyer addresses | `DELETE /buyer/addresses/{address_id}` | S | Medium | Address policy; extract web destroy logic |
| Locations | `GET /locations/regions` | S | Low | AddressController directory queries; PsgcRegion |
| Locations | `GET /locations/provinces` | S | Low | AddressController directory queries; PsgcProvince |
| Locations | `GET /locations/cities` | S | Low | AddressController directory queries; PsgcCityMunicipality |
| Locations | `GET /locations/barangays` | S | Low | AddressController directory queries; PsgcBarangay |
| Buyer checkout | `POST /buyer/checkout/quotes` | M | High | DeliveryFeeService; CheckoutAvailability; StockReservationService validation |
| Buyer checkout | `POST /buyer/orders` | L | High | Extract CheckoutController::store transaction; StockReservationService; FulfillmentService; OrderNotificationService |
| Buyer orders | `GET /buyer/orders` | M | Medium | User::orders with eager-loaded aggregates |
| Buyer orders | `GET /buyer/orders/{order_id}` | M | Medium | Account/OrderController; CheckoutController::show; fulfillment relations |
| Buyer orders | `POST /buyer/shipments/{shipment_id}/confirm-receipt` | M | High | FulfillmentService::act complete; SettlementService; existing unique RiderEarning credit |
| Buyer orders | `GET /buyer/shipments/{shipment_id}/proof-photo` | S | High | Extract FulfillmentController::proofPhoto owner/path validation; Storage local |
| Rider status | `GET /rider/status` | S | Medium | RiderProfile::isApproved; user role |
| Rider status | `PUT /rider/availability` | M | Medium | Extract Rider/DeliveryRequestController::availability; DeliveryRequestService |
| Rider offers | `GET /rider/offers` | M | High | DeliveryRequestService::requests; rider_delivery_requests table |
| Rider offers | `POST /rider/offers/{offer_id}/decision` | M | High | FulfillmentService::act claim/decline; DeliveryRequestService; extract state-error classifier |
| Rider shipments | `GET /rider/shipments` | M | Medium | Fulfillment query scoped to approved rider and delivery_method internal; HistoryController |
| Rider shipments | `GET /rider/shipments/{shipment_id}` | M | High | FulfillmentService authorization; fulfillment/order/item relations |
| Rider shipments | `POST /rider/shipments/{shipment_id}/pickup` | M | High | FulfillmentService::act pickup |
| Rider shipments | `POST /rider/shipments/{shipment_id}/deliver` | L | High | FulfillmentService::act deliver; SafeImage; extract shared delivery request validation |
| Rider shipments | `POST /rider/shipments/{shipment_id}/failed-attempts` | M | High | FulfillmentService::act fail; DeliveryExceptionService |
| Rider earnings | `GET /rider/earnings` | M | Medium | Rider/EarningsController; RiderEarning; SettlementService::riderPay |
| Realtime | `POST /broadcasting/auth` | M | High | New Sanctum channel authorization; ownership/assignment rules |

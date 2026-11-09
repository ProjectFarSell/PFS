const R=(status,actual,remarks)=>[actual,status,remarks];
const P=(a,r)=>R('Pass',a,r),F=(a,r)=>R('Fail',a,r),B=(a,r)=>R('Blocked',a,r);
export const modules={};
function set(name,rows){modules[name]=rows;}
export function absent(name,count,feature,evidence){
 set(name,Array.from({length:count},(_,i)=>i===0
  ?F(`${feature} is not implemented; the expected positive workflow is unavailable.`,`Inspection: ${evidence}.`)
  :B(`Not executed: this case requires ${feature}, which is unavailable.`,`Blocked by the first case in this module. ${evidence}.`)));
}
const pending=(feature)=>B(`Not executed: ${feature}.`,'Required workflow or fixture unavailable; no matching result claimed.');
set('B1 Account',[
 F('Name edits work, but profile photo upload is not implemented.','ProfileCrudTest passed for text edits; inspection: ProfileController has no avatar upload.'),
 F('Names J, Juan123 and 51 characters were accepted and saved.','HTTP: profile_name_*; one full-name field, max 120. Photo control absent.'),
 P('Correct current password changes password; old login fails and new login succeeds.','ProfileCrudTest::test_password_change_hashes_password_clears_session_and_requires_new_credentials.'),
 P('Wrong current password is rejected and the original password hash is unchanged.','ProfileCrudTest::test_wrong_current_password_weak_password_and_mismatched_confirmation_are_rejected.'),
 F('Only minimum length and confirmation are required; missing character classes are allowed.','Inspection: Password::defaults(); earlier HTTP complexity checks also accepted these classes.'),
 F('New email was saved immediately, without OTP verification.','HTTP: email_change_immediate; database confirmed new email and null verification.'),
 pending('email-change OTP verification is not implemented'),
 P('Case-insensitive duplicate email changes are rejected; account stays unchanged.','ProfileCrudTest::test_invalid_details_and_case_insensitive_duplicate_email_are_rejected_without_password_flash.'),
 F('Phone changes save directly; no OTP or phone uniqueness check exists.','Inspection: ProfileController. Broad phone regex; no verification flow.'),
]);
set('B2 Address Book',[
 F('Addresses can be saved, but no separate recipient name is collected or stored.','AddressPolicyTest create passed; inspection: AddressController fields differ from ADDR-2.'),
 P('Location endpoints filter by parent; changing a parent clears downstream fields.','AddressPolicyTest location endpoints passed; public/js/address-location.js cascade inspected.'),
 F('Location and street required; phone is optional and recipient field is absent.','Inspection: validatedAddress rules. Does not enforce every workbook mandatory field.'),
 F('All four invalid phone strings were accepted into addresses.','HTTP: address_phone_*; phone has string/max validation only.'),
 F('11th and 12th addresses saved successfully.','HTTP: address_number_11/12; database confirmed more than 10 addresses.'),
 P('Switching default left exactly one default; checkout preselected that address.','HTTP switch_default/default_preselected and database default count = 1; equivalent address fixture.'),
 F('Default address deleted successfully while no alternative was default.','HTTP: delete_default_address; database default_count = 0.'),
 F('Order stores an address snapshot, but printable waybill is not implemented.','CheckoutFlowTest and FulfillmentFlowTest snapshot assertions passed; full expected view set absent.'),
 P('Another buyer’s address edit/delete is denied; record remains unchanged.','AddressPolicyTest::test_user_cannot_edit_another_users_address and delete counterpart.'),
]);
set('B3 Browse & Search',[
 P('Category filtering returns visible matching products and excludes other categories.','CatalogFilterTest combined filters; HomeControllerTest visible category counts. Equivalent fixtures used.'),
 F('Store pages list products, but CAT-1–CAT-5 store categories are not implemented.','Inspection: ShopController and Shop model; only platform product categories exist.'),
 P('Exact, partial, uppercase and outer-spaced searches all returned the basket.','HTTP: search_Rattan Fruit Basket, search_rattan, search_RATTAN, spaced search. SQLite.'),
 F('Typo ratan baskt returned no basket and no typo suggestion.','HTTP: search_ratan baskt and search_zzqxw both 200. Fails typo-recovery expectation.'),
 P('Injection strings returned no basket/all-product matches, no dialog and no browser errors.','HTTP search probes plus live browser for script, SQL string and image-onerror input; no script ran.'),
 P('Empty and whitespace search returned the normal product listing without error.','HTTP: search_empty and whitespace, both 200.'),
 P('Inclusive decimal price filtering and invalid range validation passed.','CatalogFilterTest::test_filters_can_be_combined_and_preserve_decimal_prices and invalid-filter test.'),
 F('Category/city/price filters work; rating 4+ filter is deliberately unavailable.','CatalogFilterTest::test_rating_control_is_hidden_until_product_reviews_exist.'),
 F('Price sorts work, but best-selling sort is not supported.','CatalogFilterTest price sorts passed; ProductController only relevance/price/newest.'),
 P('Unpublished/inactive products and hidden-shop products are excluded from public access.','SellerProductManagementTest draft/unpublish and hidden-shop tests; equivalent fixtures.'),
 F('No vacation setting, label or purchase block exists.','Inspection: Shop model and seller routes; active/hidden shop is a different feature.'),
]);
set('B4 Product Detail',[
 P('Purchase disabled until both options selected; valid Natural-M enabled purchase and added once.','Live browser option selection plus HTTP tote_no_variant/tote_add_M_1; workbook variant values.'),
 F('Variant price/stock controls exist; per-colour variant image switching does not.','Inspection: ProductVariant model and catalog/show view; full expected image change unavailable.'),
 F('Black-M remained selectable. Selecting it showed 0 stock and disabled purchase buttons.','Live browser: Size M option disabled=false for Black. HTTP zero-stock addition was rejected.'),
 P('Invalid quantities rejected; 20 accepted for stock 20 and the plus button disabled at 20.','HTTP add/update quantity boundaries and live basket-page spinbutton check.'),
 F('No per-buyer cumulative purchase-limit field or enforcement exists.','Inspection: Product, CartController and checkout stock service.'),
 F('No scheduled flash countdown, claimable vouchers or flash allocation exists.','Inspection: is_flash display flag differs from the workbook sale/voucher workflow.'),
 P('Buy Now checked out only Natural-M at ₱249; charger remained in the cart.','HTTP tote_buy_now/buy_now_checkout/buy_now_keeps_charger. Exact product values, demo shop substituted.'),
 P('Inactive/draft and hidden-shop product URLs are denied.','SellerProductManagementTest draft/hidden-shop public access tests passed.'),
]);
set('B5 Wishlist & Follow',[
 P('Buyer can save and unsave; the saved record and listing follow the operation.','SavedItemTest save/unsave/index tests passed using equivalent product fixtures.'),
 B('Duplicate saves are idempotent in regression; rapid-click heart state not browser-verified.','SavedItemTest::test_saving_the_same_product_twice_is_idempotent passed.'),
 F('A deleted product is skipped from the saved list rather than retained as Unavailable.','Inspection: account/saved/index.blade.php skips missing product relations.'),
 F('Store following and follower counts are not implemented.','Inspection: no following model, route or store follow control.'),
 B('Guest wishlist access redirects to login; follow action is absent.','SavedItemTest guest tests passed; full combined case blocked by missing following.'),
]);
set('B6 Cart',[
 P('Natural-M additions of 1 and 2 merged into quantity 3, subtotal ₱747.00.','HTTP tote_add_M_1/tote_add_M_2/cart_merged_747; exact variant values.'),
 P('Natural-M and Natural-S remained distinct cart lines with their own prices.','HTTP tote_add_S_1/cart_two_variants; shared Cart line keys verified.'),
 F('Cart renders a flat list with shop labels, rather than separate store groups.','Inspection: cart/index.blade.php; module-cart.html from live HTTP run.'),
 F('Cart does not offer per-line selection for selective checkout.','Inspection: cart/index and Cart::forCheckout include all lines unless Buy Now is used.'),
 F('Quantity 0 removes immediately; 1 saves and 21 is rejected for stock 20.','HTTP: cart_qty_*. Workbook requires rejecting 0 or asking to remove; no confirmation occurs.'),
 P('After seller changed charger price from ₱399 to ₱420, refreshed cart showed ₱420.','HTTP charger_price_420/cart_price_refresh; equivalent demo seller.'),
 F('Deleted products are filtered out instead of kept as an unavailable cart line.','Inspection: Cart::hydrateLines returns null for missing products; required selective checkout absent.'),
 F('Cart is session-only; logout invalidates it and no cross-device account cart exists.','Inspection: Cart::SESSION_KEY and LoginController::destroy.'),
 P('Updating a cart line to 0 removed it successfully.','HTTP: cart_qty_0; removal uses Cart::update and live cart was retrieved afterward.'),
 P('Injected user/buyer IDs did not alter the other buyer’s session cart.','HTTP cart_user_id_tamper; independent buyer session remained empty.'),
]);
absent('B7 Vouchers',12,'voucher wallet, claiming and redemption','No voucher models/routes; checkout validates only address and payment');
set('B8 Checkout',[
 F('Checkout uses flat ₱49 shipping and one order with per-shop fulfillments; vouchers absent.','CheckoutFlowTest/FulfillmentFlowTest passed for current behavior; worked ₱1,083 example cannot match.'),
 F('Configured fee is flat ₱49; it does not use the workbook’s NCR/Visayas rate table.','CheckoutFlowTest total assertion and FlatRateDeliveryFeeService inspection.'),
 F('Shipping ignores weight and destination; no rounded kilogram charging.','Inspection: FlatRateDeliveryFeeService returns configured fixed fee.'),
 F('No service-zone coverage check or unserviceable-address block exists.','Inspection: CheckoutController accepts any owned address; flat fee service.'),
 F('Checkout has no note-to-seller input or 200-character note storage.','Inspection: CheckoutController::store validation/created order fields.'),
 P('Checkout without a payment method returned a required-field message.','HTTP: checkout_no_payment returned 422; no order created by this attempt.'),
 B('Double-click/concurrent placement was not exercised.','Regression covers transactional stock reservation, not duplicate-submit idempotency.'),
 F('After price changed during checkout, order was placed at the new price without reconfirmation.','HTTP stale_price_tampered_checkout: charger 420→480; order subtotal 480, no confirmation step.'),
 P('Insufficient stock prevents the order, keeps the cart and leaves stock unchanged.','CheckoutFlowTest::test_failed_stock_check_rolls_back_order_and_keeps_cart; equivalent stock fixture.'),
 pending('voucher validation/revalidation is not implemented'),
 P('Submitted price/total of ₱0.01 were ignored; order used real ₱480 plus ₱49 shipping.','HTTP stale_price_tampered_checkout; database order subtotal 480 and total 529.'),
]);
set('B9 Payment',[
 F('COD reserves stock and awaits seller confirmation; the exact ₱500 order fixture was not run.','CheckoutFlowTest verifies current COD status/₱49 fee. No real payment collection tested.'),
 F('Only demo gateway_stub is available; real e-wallet/card payment and references absent.','Inspection: PaymentMethod/CheckoutController; stub-paid regression passed.'),
 ...Array.from({length:5},()=>pending('real payment gateway, pending payment retries and timed auto-cancel are unavailable')),
]);
set('B10 Order Tracking',[
 F('Order history is a paginated list; workbook status tabs and counts are absent.','OrderHistoryTest passed for current list; OrderController has no status tabs/filter.'),
 F('Shipment events exist, but logistics hub stages and full workbook timeline are absent.','FulfillmentFlowTest passed; FulfillmentStatus has no At Hub or Failed Delivery.'),
 F('Buyer receipt confirmation completes delivery, but rating and earnings release are absent.','FulfillmentFlowTest COD completion passed; no ratings/earnings models or routes.'),
 P('Receipt completion before Delivered is rejected.','FulfillmentFlowTest::test_invalid_or_stale_steps_and_unapproved_riders_are_blocked.'),
 F('No 7-day automatic completion or earnings release job is implemented.','Inspection: console schedules and fulfillment actions; buyer confirmation is manual.'),
 P('Another buyer’s order URL returned HTTP 403 with no order details.','HTTP other_buyer_order_access; equivalent isolated buyer/order fixtures.'),
]);
absent('B11 Cancellation',6,'buyer order cancellation UI/API','Order routes expose listing/details only; fulfillment completion is the buyer action');
absent('B12 Return & Refund',5,'buyer return/refund requests','No return/refund models, routes or workflow');
absent('B13 Ratings & Reviews',6,'product review submission and ratings','No review model/routes; rating filter intentionally hidden');
set('B14 Chat & Notifs',[
 F('Product-linked text chat starts and sends, but image attachments are unsupported.','HTTP chat_start/message sends; ConversationController validates body only.'),
 F('1,001-character message was sent successfully instead of being rejected.','HTTP chat_length_1001_True returned 201. Current maximum 4,000; attachments unsupported.'),
 F('Order-change notification feed is not implemented.','Inspection: chat unread counts exist; no order notification controller/route.'),
 pending('scheduled flash sale reminders are not implemented'),
 pending('an in-app order notification feed is not implemented'),
]);

absent('S1 Store Profile',13,'editable store profile/settings','Shop fields exist, but seller profile/settings mutation routes do not');
absent('S2 Store Categories',13,'seller-managed store categories','Only platform categories exist; no per-store category model/routes');
set('S3 Product Mgmt',[
 F('Product can publish, but weight/dimensions, store categories and image gallery are absent.','HTTP create_basket; SellerProductManagementTest listing flow passed with simpler fixtures.'),
 F('Publish accepts no image and ignores weight; required set differs from workbook.','HTTP zero_image_publish 302; current fields use one optional image.'),
 F('4/121-character names and 19/3,001-character descriptions were accepted.','HTTP product_name_*/product_description_*; limits are max 150/5,000 with no minimum.'),
 F('₱0.99 and ₱1,000,000.01 were accepted; 3-decimal/non-numeric values rejected.','HTTP product_price_*; actual range ₱0.01–₱99,999,999.99.'),
 F('100,000 stock was accepted; negatives and fractions rejected.','HTTP product_stock_*; actual maximum 1,000,000 rather than 99,999.'),
 F('Product published with zero images; editor has one optional image input.','HTTP zero_image_publish 302; no 1–9 gallery enforcement.'),
 B('File-content and 5 MB boundaries were not uploaded to configured R2 storage.','Inspection shows JPG/JPEG/PNG/WebP and max 4 MB. Isolated upload integration unavailable.'),
 F('Weight/dimensions have no product input or validation; shipping is a flat fee.','Inspection: Seller ProductController and FlatRateDeliveryFeeService.'),
 F('Name-only draft was rejected for description, category, price and stock.','HTTP minimal_draft 422; draft uses the same required field rules as publication.'),
 B('Exact existing incomplete-draft publish sequence unavailable after name-only draft failed.','Blocked by SEL-PROD-009; standard validation tests passed.'),
 B('Unpublish/public visibility passed in regression; exact stale-cart/reactivation sequence untested.','SellerProductManagementTest drafts/unpublish and hidden-shop tests are partial evidence.'),
 F('Product duplication is not implemented.','Inspection: seller product routes offer create/store/edit/update only.'),
 B('Exact live cart/checkout price-change sequence was not exercised.','Cart reads current prices; static inspection does not establish this full scenario.'),
 pending('product deletion is not implemented; no delete endpoint to exercise'),
 pending('product deletion with finished-order snapshots is unavailable'),
 pending('bulk deactivate/delete actions are not implemented'),
 F('Submitting the same Publish payload twice created two distinct products.','HTTP duplicate_publish_0/1; database count increased by 2. No idempotency protection.'),
 P('Other seller cannot view/edit product; ownership is enforced.','SellerProductManagementTest::test_only_active_shop_owner_can_create_or_edit_even_with_tampered_ids.'),
]);
set('S4 Variants',[
 F('Options are entered as manual combination rows, not auto-generated Cartesian rows.','Inspection: seller/product-form. Variant storage exists; workbook generation UI absent.'),
 F('A product with Color, Size and Material was accepted.','HTTP variant_three_types 302; no maximum two-dimension rule.'),
 F('Both 20 and 21 Color options were accepted as variant combination rows.','HTTP options_20/options_21 returned 302; no 20-option cap.'),
 P('Red/red duplicate combinations and empty option text were rejected.','HTTP duplicate_variant_options/empty_variant_options returned 422; creation rolled back.'),
 F('Variant price override is optional and actual price/stock bounds differ.','Inspection: validateProduct; stock max 1,000,000, price min 0.01.'),
 F('Existing SKU caused HTTP 500 UniqueConstraintViolation, not a duplicate-SKU validation message.','HTTP duplicate_sku_existing using MCH-TOTE-Natural-S. Product transaction rolled back.'),
 F('Product header showed base PHP 199.00, not 199–299 range; selection showed PHP 249.00.','Live browser with original six workbook variant values before rename.'),
 F('Variants have no image field or colour-image switch.','Inspection: ProductVariant model and catalog/show view.'),
 F('Black-M with stock 0 can be selected; buttons disable only after selection.','Live browser using exact six variant values; all Size options had disabled=false for Black.'),
 P('Order retained Natural-M at ₱249 after product colours changed to Beige/Brown.','HTTP rename_variant_after_order/order_variant_snapshot/renamed_variant_page; original order values preserved.'),
 B('Stale cart option handling exists; exact variant removal/other-item checkout sequence untested.','Inspection: Cart::hydrateLines and stock validation; no matching HTTP fixture.'),
 F('Omitted variant rows are deactivated without an unfinished-order check.','Inspection: saveVariants updates is_active for omitted IDs; no delete/unfinished-order guard.'),
]);
set('S5 Inventory',[
 P('COD order placement deducts reserved stock transactionally.','CheckoutFlowTest::test_checkout_uses_owned_saved_address_and_reserves_stock_transactionally; equivalent 2-unit fixture.'),
 F('Real unpaid e-wallet orders are absent; gateway_stub marks orders demo-paid immediately.','Inspection: CheckoutController; stub-paid test does not satisfy To Pay expectation.'),
 B('State-machine cancellation restores once, but buyer cancellation workflow is absent.','OrderStateMachineTest passed; exact buyer action blocked by missing cancellation UI/API.'),
 pending('unpaid-order timed auto-cancellation is not implemented'),
 P('Natural-M stock changed 8→7 after its order; all five sibling stocks were unchanged.','HTTP tote_one_variant_order; database variant_stock_after_order matches workbook values.'),
 B('Stock rollback tests passed, but two simultaneous checkouts were not run.','Concurrency/oversell claim remains unverified; SQLite run is not a production lock test.'),
 B('Out-of-stock controls exist; exact last-unit two-buyer view sequence was not exercised.','Inspection: product stock controls. No browser view-state result claimed.'),
 F('No once-per-crossing alert system exists for seller inventory.','Inspection: dashboard low-stock summaries differ from threshold notification logic.'),
 F('Manual product edits permit up to 1,000,000 stock rather than 99,999.','HTTP creation boundary accepted 100,000; shared validateProduct rules apply to updates.'),
 P('Checkout rejects cart quantity above current available stock and keeps cart unchanged.','CheckoutFlowTest failed-stock rollback and CartStockPolicyTest passed with equivalent fixtures.'),
]);
absent('S6 Discounts',10,'scheduled store discounts','No seller discount schedule model/routes');
absent('S7 Store Vouchers',11,'store voucher management','No voucher creation, claim counters or redemption models/routes');
absent('S8 Flash & Bundles',8,'scheduled flash sales and bundles','is_flash display flag exists; no time-slot/allocation/bundle management');
set('S9 Order Processing',[
 F('Seller dashboard/fulfillments list exists; requested tabs/date/search combination absent.','SellerDashboardTest scopes/pagination passed; FulfillmentController has status filter only.'),
 F('Accept/ready/rider actions exist; separate Packed step, waybill and logistics queue absent.','FulfillmentFlowTest passed for current states; no printable waybill route.'),
 P('Ready before seller acceptance is rejected; shipment remains pending.','FulfillmentFlowTest::test_invalid_or_stale_steps_and_unapproved_riders_are_blocked.'),
 F('Seller rejection releases stock once and shows reason; voucher return/notification absent.','FulfillmentFlowTest::test_rejection_releases_only_own_stock_once_and_waives_its_fee.'),
 pending('real prepaid refund and seller earnings workflows are unavailable'),
 P('Seller rejection after handover is blocked by state checks.','Inspection: seller reject expects Pending; FulfillmentFlowTest validates stale/out-of-order actions.'),
 F('Per-shop item snapshots exist; voucher discount and buyer note are absent.','FulfillmentFlowTest shop snapshots passed; checkout has no voucher/note inputs.'),
 P('Seller access is scoped to owned shipment items; other shop mutations denied.','FulfillmentFlowTest::test_wrong_roles_and_other_shops_cannot_mutate_shipments.'),
]);
absent('S10 Returns',7,'seller return/refund/dispute handling','No return request, evidence or refund model/routes');
set('S11 Reviews & Chat',[
 F('Product reviews and seller review filters are not implemented.','Inspection: no review model/routes.'),
 pending('seller review replies are not implemented'),
 pending('review reply creation/editing is not implemented'),
 pending('no review record exists to test protection against deletion'),
 F('Text chat sends and is product-linked; image attachments are unsupported.','HTTP chat_start and seller_chat_1001; ConversationController supports body only.'),
 B('Invalid attachment upload cases not sent; there is no attachment control/storage flow.','Blocked by unavailable chat attachment feature.'),
 F('Empty/whitespace rejected; both 1,000 and 1,001 characters sent in full.','HTTP chat_length_* and seller_chat_1001. Actual maximum 4,000.'),
]);
absent('S12 Reports & Earnings',7,'seller earnings balances, commission reports and payouts','Dashboard sales totals exist, but no commission/balance/payout workflow');

set('R1 Profile & Vehicle',[
 F('Rider profile exists, but company and license expiry fields are absent.','RiderRegistrationTest/RiderDashboardTest passed for current self-application flow; no logistics ownership.'),
 F('Approved riders cannot edit approved vehicle applications; must contact administrator.','AdminRiderApprovalTest::test_approved_and_suspended_applications_cannot_be_reset_by_applicant.'),
 B('Approved-rider vehicle editing is blocked before duplicate-plate validation can be tested.','Blocked by RID-PROF-002; plate uniqueness is not a validated rule in self-application.'),
 F('License expiry is not collected or validated.','Inspection: RiderRegistrationController only requires license_no string.'),
 B('5 MB file/content sequence not run on an approved rider; approved applications cannot edit.','RiderRegistrationTest replacement tests passed for buyer applicants, a different prerequisite.'),
 P('Suspended riders cannot load assignments, claim work or go available.','RiderDashboardTest non-approved test; RiderDeliveryRequestTest approval rechecks; admin selectors filter approved.'),
 B('Exact cross-rider profile URL/request tampering was not exercised.','Current profile uses authenticated user; source scoping is not a direct attack test.'),
]);
set('R2 Availability',[
 F('Availability and offer backfill work, but a logistics-company dashboard is absent.','RiderDeliveryRequestTest availability test passed; full cross-role expectation cannot match.'),
 F('Offline reroutes offers, but admin manual assignment does not check is_available.','RiderDeliveryRequestTest offline test passed; FulfillmentService assign checks approved role only.'),
 P('Going unavailable preserves accepted work and reroutes new offers.','RiderDeliveryRequestTest::test_availability_backfills_requests_and_offline_does_not_cancel_accepted_work.'),
 F('Logout invalidates session without clearing persisted rider availability.','Inspection: LoginController::destroy; no session-timeout availability reset hook.'),
]);
set('R3 Job Assignment',[
 F('Offer/claim works, but pickup pay and logistics-company view are absent.','RiderDeliveryRequestTest eligibility/claim passed; no rider-pay/company workflow.'),
 P('Decline removes the offer and offers the job to the next eligible rider.','RiderDeliveryRequestTest::test_automatic_offer_prioritizes_lower_workload_and_decline_moves_to_next_rider.'),
 F('Offer expiry is 2 minutes, rather than 60 seconds.','RiderDeliveryRequestTest expires at 121 seconds; DeliveryRequestService uses addMinutes(2).'),
 F('Pool exhaustion is based on eligible riders, not a maximum of 3 declines.','Inspection: DeliveryRequestService tried-rider filtering; no fixed three-attempt cutoff.'),
 B('First-claim-wins regression passed; genuinely simultaneous manual/accept requests were not run.','RiderDeliveryRequestTest::test_first_claim_wins_and_admin_cannot_overwrite_it is sequential.'),
 F('No maximum-three-active-jobs check exists; workload affects offer ordering only.','Inspection: DeliveryRequestService and FulfillmentService claim/assign.'),
 pending('logistics-company ownership is not implemented'),
]);
set('R4 Pickup',[
 F('Pickup address/contact and order number are shown; separate tracking/parcel count absent.','FulfillmentFlowTest pickup-view assertions passed; no waybill/tracking workflow.'),
 F('Pickup action advances shipment, but barcode scan and ₱30 pickup pay are absent.','FulfillmentFlowTest COD flow passed for current action; no scan/pay endpoints.'),
 pending('tracking-number manual pickup input is not implemented'),
 pending('tracking-number validation is not implemented'),
 B('Repeated state changes are rejected, but scan input/pay counting cannot be exercised.','FulfillmentFlowTest stale-step checks passed; scanning/pay feature absent.'),
]);
set('R5 Delivery',[
 F('Delivery records note/COD confirmation; photo, OTP and rider pay/balances are absent.','FulfillmentFlowTest COD delivery passed without photo or OTP.'),
 P('Delivery is allowed only from InTransit; earlier states are rejected.','FulfillmentService::act state check; FulfillmentFlowTest invalid/stale-step regression passed.'),
 F('Delivery without a proof photo succeeded in the existing COD regression.','FulfillmentFlowTest::test_cod_flow_requires_collection_and_buyer_confirmation supplies note/boolean only.'),
 pending('delivery OTP and 15-minute OTP lockout are not implemented'),
 F('Delivery asks for a COD-collected boolean, not an exact entered cash amount.','Inspection: FulfillmentController/FulfillmentService; no amount-match validation.'),
 B('Demo prepaid delivery records no cash; a real prepaid order/balance fixture is unavailable.','FulfillmentFlowTest demo_prepaid test passed; full real-payment case remains blocked.'),
 P('Only the assigned approved rider can update pickup/delivery.','FulfillmentFlowTest wrong-rider pickup denied; shared ownership gate covers delivery.'),
]);
absent('R6 Failed Delivery',5,'failed attempts, rescheduling and return-to-sender','FulfillmentStatus/actions contain no failed-delivery or RTS state');
absent('R7 Earnings & COD',6,'rider delivery history/pay/COD remittance','Rider dashboard shows active work; no earnings/remittance ledger/routes');

export function logisticsRows(count){return Array.from({length:count},(_,i)=>
 B('Not executed: logistics role, company accounts and portal are unavailable.','Blocked by LOG-DASH-001. Admin dispatch is a different role and workflow.'));}
export const logisticsFirst=F('No logistics role/company dashboard exists in the current project.','Inspection: UserRole has buyer/seller/rider/admin only; route list has no logistics portal.');

export const rules={
 A1:'Testing used isolated SQLite, equivalent built-in factory/demo fixtures, HTTP boundaries and the existing 189-test suite. Exact unsupported fixtures are marked Blocked; named source expectations retained.',
 A2:'No voucher wallet, stacking or redemption implementation.',
 A3:'Checkout reserves stock transactionally; seller rejection/state-machine cancellation releases stock once. Buyer cancellation/RTS workflows absent.',
 A4:'No voucher application on sale products.',
 A5:'is_flash display flag exists; no scheduled flash price/quantity allocation.',
 A6:'No buyer cancellation endpoint or timed unpaid-order auto-cancel. Seller rejection is restricted to pending fulfillment.',
 A7:'Variant combination rows support arbitrary option names; three dimensions accepted. Store categories absent.',
 A8:'No voucher funding/split/refund allocation implementation.',
 A9:'No 5% commission ledger or net earnings workflow.',
 A10:'Pending → Accepted → Ready → Assigned → InTransit → Delivered → Completed, or seller rejection. No Packed-between-Accepted/Ready, hub/failed/RTS stages.',
 A11:'Buyer manually confirms Delivered. No 7-day automatic completion or return window.',
 A12:'Reviews, ratings and review replies absent.',
 A13:'No failed-attempt counter, failure reasons or return-to-sender workflow.',
 A14:'Offers expire after 2 minutes; ordered by workload/ID in matching city. No fixed three-job or three-decline limit.',
 A15:'No Pending/Available earnings balances or payout request workflow.',
 A16:'Product editor: one optional JPG/JPEG/PNG/WebP image, max 4 MB. Rider application documents: optional JPG/JPEG/PNG/PDF, max 5 MB. Other upload flows absent.',
 A17:'Product price 0.01–99,999,999.99 with up to 2 decimals; stock 0–1,000,000 integer. Workbook limits differ.',
 A18:'No vacation flag. Product mutation requires active shop and seller role; unapproved applicants cannot save product drafts.',
 A19:'No store category/product delete routes; omitted variants are deactivated without unfinished-order check.',
 A20:'Configured flat ₱49 delivery fee, allocated among shop fulfillments. No weight/zone rate table.',
 A21:'No voucher wallet/cancellation-return implementation.',
 A22:'Role and ownership checks covered by regression tests; logistics role absent. Exact session-timeout boundary not exercised.',
 A23:'Chat unread counts exist; no order/shipment notification feed or sale reminders.',
 A24:'COD delivery records a collected boolean/time only. No remittance reconciliation ledger.',
 A25:'Profile name max 120; product name max 150, description max 5,000; chat max 4,000. No seller-note field. Seller rejection reason max 1,000.',
 A26:'No voucher code implementation.',
 A27:'No voucher claims or edit-after-claim workflow.',
 A28:'No scheduled store sale implementation.',
 A29:'Dashboard low-stock summaries exist; no once-per-threshold-crossing alert implementation.',
 A30:'More than 10 addresses accepted; default deletion allowed leaving zero defaults. Order ship_to is snapshotted.',
 A31:'No return/refund evidence, response deadline, dispute or refund-account implementation.',
 A32:'Delivery uses note plus COD boolean; no photo/OTP requirement or lockout.',
 A33:'No pickup/delivery pay ledger.',
 A34:'Offers use city/workload, not GPS distance/company. Admin manual assignment checks approved status; no offline/job-limit guard.',
 A35:'Fulfillment events capture actor/time/state/note; no logistics rate/override/remittance audit workflow.',
 A36:'No CSV/XLSX report export routes.',
 A37:'Saved-item creation and fulfillment states have duplicate protection. Identical Publish payload twice created two products. Checkout/payment concurrency unverified.',
};

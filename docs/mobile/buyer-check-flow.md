# Buyer app integration check flow

Use the main Flutter project (`farsell_mobile`), not a Rider app. Start Laravel, then rebuild Flutter after native package installation.

1. Browse as guest: search, load more, choose size/color, observe SKU/stock and add to the device cart.
2. Profile > Sign in / create account: use a Buyer identity. Real accounts complete the email OTP from SMTP. Phone is offered only when both server and client Firebase settings support it. A Seller/Admin/Rider identity must be rejected by the Buyer app login.
3. Cart > Add device cart to my account: check that quantities transfer once and rejected/unavailable lines remain for review. Refresh verifies current stock/prices.
4. Profile > Delivery addresses: add/edit/remove an address; changing a region clears downstream locations. Existing legacy addresses with missing geography must be completed before checkout.
5. Checkout: choose an address, verify SKU/quantity, server shipping and total, then explicitly Place order. COD is the only choice exposed by this app. An unserviceable address remains a server validation error.
6. Disconnect just after submitting an order: retry Check interrupted order, including after restarting the app. The original request/key is reused. Inspect My orders to confirm no duplicate. A rejected stale quote is refreshed explicitly rather than auto-ordered.
7. Profile > My orders: open an order, review per-shop shipments, timeline, amounts, and courier tracking when available. An in-transit PIN is visible only to the owner. Never disclose it before handover.
8. After the existing web/Rider backend marks delivery: view private proof details/photo and Confirm receipt for the delivered shipment. Completion should appear after refresh and use the existing backend earnings rules.
9. Sign out, sign in as a different Buyer and confirm account cart, addresses, orders and proof do not carry over. Guest saved selections are explicitly device data, not private order history.
10. Test forgotten password in the external website browser; test reviewed account deletion only on a disposable Buyer. Do not submit real deletion requests as an automated demo.

No new Rider app screens were built. Backend regression results are recorded separately in `../api/audit-report.md`. Live provider/device tests are not replaced by mocked Flutter tests.

## Verification result (November 13, 2026)

- Flutter analyzer: no issues.
- Flutter suite: 21 tests passed, including email OTP sign-in and a phone-layout COD checkout through the order-details screen with mocked API responses.
- Android debug APK: built and installed on emulator-5554.
- Live local smoke check: started the local Laravel server on port 8000, confirmed the new product API returned 200, and the emulator loaded 20 products without a connection error. Buyer account navigation was checked without creating an order or signing in as a real user.
- Real SMTP inbox, Firebase SMS and full physical-device acceptance were not tested by the automated mocks. Refer to the configuration requirements in ../../farsell_mobile/README.md.

Latest Buyer extensions add Google authentication support, shared chat/history, reviews and media, cancellation, returns/refunds, notification inbox, FCM receiving, private Reverb updates and HTTPS app-link/signing configuration. The new guide is buyer-extensions.md. Current automated checks: 420 backend tests and 28 Flutter tests passed, analyzer clean and Android debug APK built. Provider/device acceptance and hosting remain separate.

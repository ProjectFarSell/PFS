# Exact cart subtotal parity

The cart view's `subtotal` is now an exact **two-decimal string**, for example `"127.00"` or `"0.00"`. `Cart::subtotal()` and `CartDisplay::groups()` share `CartDisplay::subtotal()`; it multiplies the persisted decimal product price (or variant price override) by quantity using `Brick\Math\BigDecimal`, then sums without float arithmetic.

The cart controller hydrates once and passes those same lines to both grouping and subtotal calculation, preventing two different product reads from disagreeing during a concurrent price edit. `Cart::subtotal()` remains callable without arguments; the optional lines argument permits reuse of an existing snapshot. No route or request validation key changed. The current repository has no other production callers relying on a float return type.

The change fixes cart view totals only. Checkout, stock reservation, shipping fees, vouchers, order records and historical prices are not repriced. The existing float fields on hydrated cart lines remain compatible with current callers; they are not used for subtotal arithmetic.

Regression coverage includes guest and Buyer carts, two shops, PHP 19.99/0.01/33.33, quantities greater than one, a PHP 0.10 variant override, exact string equality between summed groups and the cart subtotal, and an empty `"0.00"` cart.

Related handoff failures were checked against current code:

- A Seller with no linked shop should see setup/application guidance and no shop storefront links. The route now supplies the actual Seller application to the existing view. Navigation tests distinguish this account from a Seller with an approved shop instead of loosening role boundaries.
- The old no-shop copy expectation now checks the current **Open your shop / Apply to open a shop** flow and continues to verify isolation from another shop's records.
- The printable voucher invoice test explicitly disables the external API key for its HTML fallback assertions. The separate API payload test continues testing the PDF path with a fake response. A developer's configured provider key can no longer change the fallback test's format.

Verification: the 42 focused cart, checkout, role isolation, Seller dashboard/application and voucher tests passed on both SQLite and MySQL (`farsell_test` only). Full-suite results from the frontend handoff were not treated as current verification, and the full suites were not rerun in this slice.

The Back-button reset and 375 px long-name layout still need real browser verification. This backend change does not claim those browser checks or copy a frontend slice from another computer. No local environment, database contents or migrations are changed by this fix.

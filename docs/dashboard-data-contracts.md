# Dashboard data contracts

The standalone Seller, Admin, and Rider demo views use synthetic fixtures under `resources/js/demo/`. This document defines a proposed live response contract. It does not introduce an endpoint. Existing live dashboards read their current operational data. Demo actions are disabled.

## Common request and response

- Scope the request to the authenticated role. Seller data belongs to the current shop and Rider data belongs to the current rider profile. Admin data belongs to the authorized marketplace scope.
- Accept inclusive ISO dates `start` and `end` in `Asia/Manila`. Today uses one calendar day. The 7-day and 30-day presets include today. The current custom picker allows up to 90 days of history.
- Compare against the immediately preceding range of equal calendar length. For as-of metrics, compare the closing snapshot of each range. Never compare a cumulative event count with a closing queue balance.
- Return numeric PHP amounts in major units to the UI, percentages on a 0–100 scale, and integer counts. Use the canonical minor-unit amounts for financial calculations before converting. Return `null` for an unavailable value or rate. Return `0` for an observed zero.
- The same selected range applies to every analytic widget in the response. Use an empty array when there are no chart points, alerts, or tracker rows. Include stable ordering and apply limits before returning rows.

```ts
type ISODate = string // YYYY-MM-DD
type Unit = 'php' | 'count' | 'percent'
type Snapshot = {
  period: {
    start: ISODate
    end: ISODate
    previousStart: ISODate
    previousEnd: ISODate
    timezone: 'Asia/Manila'
  }
  hasData: boolean
  kpis: Array<{
    key: string
    label: string
    value: number | null
    previous: number | null
    change: number | null
    unit: Unit
    note: string
  }>
  alerts: Array<{ key: string; text: string; count: number }>
  charts: Array<{
    key: string
    title: string
    type: 'line' | 'bar' | 'doughnut'
    stacked: boolean
    unit: Unit
    labels: string[]
    datasets: Array<{ label: string; values: Array<number | null> }>
    summary: string
  }>
  tables: Array<{
    key: string
    title: string
    columns: string[]
    rows: Array<{
      date: ISODate
      cells: Array<string | number>
      stock: { quantity: number; capacity: number; reorderPoint: number } | null
    }>
    actionLabel: string
  }>
}
```

The fixture generator currently returns the widget collections. The preview derives the period and availability locally. The proposed response carries those fields so a future live integration can report the authoritative range and empty state.

The chart datasets share their chart’s `labels` array, and every `values` array has the same length. Trend labels must be in ascending date order and stay aligned across series, including dates with zero activity. Use a null point for unavailable data. Return `change = (value - previous) / abs(previous) * 100` when both values are available and the previous value is nonzero. Use `0` when both are zero and `null` when the comparison is unavailable or the baseline is zero and the current value is nonzero. The tracker prepends the date column automatically. `cells` are display-ready values in the documented order. Only inventory rows supply a stock object, which fills the last column.

## Seller

| Widget key | Exact payload and definition |
| --- | --- |
| KPI `revenue` | PHP amount recognized for the shop during the range. Decide the recognition event and refund treatment before launch. |
| KPI `orders` | Pending or to-ship count at the range end, with one agreed fulfillment scope and no double counting of an order across statuses. |
| KPI `stock` | Active listings at or below their reorder point at the range end. Include the out-of-stock subset in `note`. |
| KPI `views` | Storefront views in the range under the agreed session/event counting rule. |
| Alerts `stock`, `orders` | Count and text for listings at/below reorder point and outstanding shipments past `ship_by_at` at the closing cutoff. |
| Chart `sales` | Daily labels with one `Sales` series in PHP, using the same recognition rule as revenue. |
| Chart `products` | Up to 10 product labels sorted by revenue descending, with one `Revenue` series in PHP. |
| Chart `statuses` | `Shipped`, `To ship`, and `Cancelled` labels with one `Orders` count series. Define one mutually exclusive classification for each counted order in the range. |
| Tracker `stock` | Closing date and cells `[productName, categoryName]`, plus stock object. Capacity is a positive display reference, quantity and reorder point are nonnegative. Sort reorder candidates first. |
| Tracker `orders` | Latest 10 rows by order date in range, with cells `[orderNumber, statusLabel, formattedOrderValue]`. The preview links to the existing Manage Shop Orders queue. |
| Tracker `top` | Closing date and cells `[productName, unitsSold, formattedRevenue]`. Limit to 10, sorted by units sold with a stable tie-breaker. |
| Tracker `returns` | Closing date and cells `[queueLabel, pendingCount]` for Returns and Reviews. The preview opens the existing returns queue. A Seller review destination would require separate approval. |

## Admin

| Widget key | Exact payload and definition |
| --- | --- |
| KPI `gmv` | Marketplace order value in PHP during the range. Define the included statuses, tax, shipping, discounts, cancellations, and refunds. |
| KPI `commission` | Platform commission recognized in PHP during the range, net of reversals under the agreed settlement policy. |
| KPI `orders` | Orders placed in the range. Label it Orders Today for the Today preset. |
| KPI `attention` | Closing count of disputes, refunds, seller approvals, and pending payouts. Include the four counts in `note`. Agree how overlapping disputes and refunds are counted. |
| Alert `failures` | Count of failed delivery events in the range. Link to the existing Failed Deliveries queue. |
| Chart `gmv` | Daily labels with `GMV` and `Commission` series in PHP. Use the same recognition rules as the KPI cards. |
| Chart `orders` | Daily labels with mutually exclusive `Processed`, `Pending`, and `Failed` count series. Use a stacked bar and define the classification cutoff. |
| Chart `growth` | Daily labels with `Buyers` and `Sellers` count series. Agree whether seller growth is counted at signup or approval. |
| Chart `categories` | Up to 10 category labels sorted by GMV, with one `GMV` series in PHP. Define how to classify deleted or changed categories. |
| Tracker `approvals` | Up to 10 pending records as of range end, oldest first. Cells `[shopName, approvalStatusLabel]`, with application date. |
| Tracker `disputes` | Up to 10 flagged records, newest first. Cells `[orderNumber, flagLabel]`, with flag date. |
| Tracker `top` | Closing date and cells `[shopName, formattedGmv]`, top 10 in range with a stable tie-breaker. |
| Tracker `fleet` | Closing date and cells `[availabilityLabel, riderCount]` for Active, Idle, and Offline. Define the signal and recency window for each state. |

## Rider

| Widget key | Exact payload and definition |
| --- | --- |
| KPI `active` | Closing active shipment count and next eligible shipment in `note`. Use the assigned rider’s permitted next action. The preview opens the existing active-deliveries view. |
| KPI `earnings` | Rider earnings in PHP earned within the range. Label it Earnings Today for the Today preset. |
| KPI `completed` | Deliveries completed within the range, with the pending closing count in `note`. |
| KPI `cod` | Outstanding COD balance in PHP as of the range end. Use null when COD does not apply to the rider. |
| Alert `active` | Failed delivery count and explanatory text for the selected range. |
| Chart `earnings` | Daily labels and one `Earnings` series in PHP, defaulting to seven days. |
| Chart `rates` | Daily labels with `Completion` and `On time` series on a 0–100 scale. Agree the completion denominator and promised-delivery timestamp. Use null when there is no denominator or reliable deadline. |
| Tracker `active` | Up to 10 shipments assigned as of the range end, ordered by next scheduled action. Cells `[shipmentReference, nextStepLabel]`, with assignment date. |
| Tracker `history` | Up to 10 recent days in range, newest first. Cells `[completedCount, failedCount, formattedEarnings]`. |
| Tracker `ratings` | Up to 10 ratings in range, newest first. Cells `[formattedRatingOutOfFive, authorizedFeedbackText]`, with review date. |

## Backend Requests

1. **Daily financial and growth aggregates.** GMV, commission, sales, earnings, conversions or views, category totals, and seller rankings may scan several event or item tables. Measure representative queries, then consider pre-aggregation by Manila business date and role scope. Invalidate on adjustments and reversals.
2. **Closing snapshots.** Inventory/reorder, overdue orders, outstanding approvals and refunds, fleet availability, and COD balances need historically correct closing values. Decide which events or snapshots are available before offering historical ranges. Cache per shop, marketplace, or rider with authorization built into the cache key and short freshness windows for live queues.
3. **Bounded operational rows.** Return authorized IDs and allowed actions separately from presentation rows before enabling row-specific live actions. Use stable pagination or bounded tracker results. Keep stock, approvals, delivery actions, and COD state fresh even if analytics are cached.
4. **Independent widget loading.** If generating chart or tracker data is expensive, expose separately fetchable widget payloads with the same validated period. Use server-side lazy loading only after the project approves the necessary integration. Current Alpine deferral delays client rendering and chart downloads.

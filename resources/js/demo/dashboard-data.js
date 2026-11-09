// Synthetic dashboard fixtures. This module does not read application records.
import { formatMetric } from '../dashboard-format.js';

export const snapshotDate = '2026-10-31';
const dayMs = 86_400_000;
const shortDate = new Intl.DateTimeFormat('en-PH', { month: 'short', day: 'numeric', timeZone: 'UTC' });

export function offsetDate(date, offset) {
    return new Date(Date.parse(date) + offset * dayMs).toISOString().slice(0, 10);
}

function daysBetween(start, end) {
    return Math.round((Date.parse(end) - Date.parse(start)) / dayMs) + 1;
}

function fixture(date, panelSeed) {
    const day = daysBetween(offsetDate(snapshotDate, -179), date) - 1;
    const seed = day + panelSeed;
    const dip = seed % 11 === 0 ? 4 : 0;
    const orders = 12 + Math.floor(day / 8) + seed % 5 - dip;
    const pending = seed % 5;
    const failed = date === offsetDate(snapshotDate, -2) ? 1 : 0;
    const completed = 4 + Math.floor(day / 10) + seed % 3 - (dip ? 2 : 0);
    return {
        date, orders, pending, failed, completed,
        revenue: orders * (330 + seed % 59),
        gmv: orders * (4200 + seed % 701),
        commission: orders * (290 + seed % 81),
        stock: [seed % 5, 9 + seed % 7, 8 + seed % 8, 7 + seed % 6],
        visits: orders * 20 + seed % 71,
        overdue: date === snapshotDate ? 1 : 0,
        returns: seed % 4,
        disputes: seed % 3,
        refunds: seed % 5,
        approvals: seed % 7,
        payouts: seed % 6,
        buyers: 12 + seed % 14,
        sellers: seed % 6,
        assigned: seed % 4,
        onTime: completed - (seed % 3 === 0 ? 1 : 0),
        earnings: completed * (55 + seed % 10),
        cod: seed % 5 === 0 ? 0 : (seed % 8) * 140,
        fleet: [15 + seed % 9, 7 + seed % 4, 5 + seed % 6],
    };
}

function rangeDays(start, end, seed) {
    return Array.from({ length: daysBetween(start, end) }, (_, index) => fixture(offsetDate(start, index), seed));
}

const total = (rows, key) => rows.reduce((sum, row) => sum + row[key], 0);
const rate = (part, whole) => whole ? 100 * part / whole : null;
const lowStock = row => row.stock.filter(value => value <= 5).length;
const attention = row => row.disputes + row.refunds + row.approvals + row.payouts;
const series = (label, values) => ({ label, values });

function metric(key, label, value, previous, unit = 'count', note = '') {
    const change = previous ? 100 * (value - previous) / Math.abs(previous) : previous === 0 && value === 0 ? 0 : null;
    return { key, label, value, previous, unit, note, change };
}

function graph(key, title, type, labels, datasets, unit = 'count', stacked = false) {
    return { key, title, type, labels, datasets, unit, stacked,
        summary: `${title} for ${labels.length} ${labels.length === 1 ? 'group' : 'groups'}. Exact values are listed in the data table.` };
}

const tracker = (key, title, columns, rows, actionLabel) => ({ key, title, columns, rows, actionLabel });
const datedRow = (date, cells, stock = null) => ({ date, cells, stock });

export function dashboardSnapshot({ role, seed, products: names, categories, sellers }, start, end) {
    const rows = rangeDays(start, end, seed);
    const previousRows = rangeDays(offsetDate(start, -rows.length), offsetDate(start, -1), seed);
    const latest = rows.at(-1);
    const previous = previousRows.at(-1);
    const recent = [...rows].reverse().slice(0, 4);
    const labels = rows.map(row => shortDate.format(new Date(row.date)));
    const forDays = (label, key) => series(label, rows.map(row => row[key]));
    const totals = key => [total(rows, key), total(previousRows, key)];
    const byWeight = (amount, weights) => weights.map(weight => Math.round(amount * weight / 100));

    if (role === 'seller') {
        const revenueByProduct = byWeight(total(rows, 'revenue'), [38, 27, 21, 14]);
        return {
            kpis: [
                metric('revenue', 'Revenue', ...totals('revenue'), 'php'),
                metric('orders', 'Pending / to ship', latest.pending, previous.pending, 'count', 'As of the range end'),
                metric('stock', 'Low / out of stock', lowStock(latest), lowStock(previous), 'count', `${latest.stock.filter(value => value === 0).length} out of stock · as of range end`),
                metric('views', 'Store views', ...totals('visits')),
            ],
            alerts: [
                { text: `${lowStock(latest)} listings at or below the sample reorder threshold of 5.`, key: 'stock', count: lowStock(latest) },
                { text: `${latest.overdue} orders past their sample ship-by date.`, key: 'orders', count: latest.overdue },
            ],
            charts: [
                graph('sales', 'Sales over time', 'line', labels, [forDays('Sales', 'revenue')], 'php'),
                graph('products', 'Revenue by product', 'bar', names, [series('Revenue', revenueByProduct)], 'php'),
                graph('statuses', 'Order status breakdown', 'doughnut', ['Shipped', 'To ship', 'Cancelled'], [series('Orders', [
                    total(rows, 'orders') - total(rows, 'pending') - total(rows, 'failed'), total(rows, 'pending'), total(rows, 'failed'),
                ])]),
            ],
            tables: [
                tracker('stock', 'Inventory and reorder alerts', ['Product', 'Category', 'Stock'], names.map((name, i) => datedRow(latest.date, [name, categories[i]], { quantity: latest.stock[i], capacity: 20, reorderPoint: 5 })), 'Open inventory'),
                tracker('orders', 'Recent orders', ['Sample order', 'Status', 'Order value'], recent.map((row, i) => datedRow(row.date, [`DEMO-S${i + 1}`, row.pending ? 'To ship' : 'Shipped', formatMetric(row.revenue / row.orders, 'php')])), 'Manage shop orders'),
                tracker('top', 'Top-selling products', ['Product', 'Units sold', 'Revenue'], names.map((name, i) => datedRow(latest.date, [name, byWeight(total(rows, 'orders'), [38, 27, 21, 14])[i], formatMetric(revenueByProduct[i], 'php')])), 'Open sales summary'),
                tracker('returns', 'Pending returns and reviews', ['Queue', 'Pending'], [datedRow(latest.date, ['Returns', latest.returns]), datedRow(latest.date, ['Reviews', latest.approvals])], 'Open return requests'),
            ],
        };
    }

    if (role === 'admin') {
        return {
            kpis: [
                metric('gmv', 'Gross merchandise value', ...totals('gmv'), 'php'),
                metric('commission', 'Platform commission', ...totals('commission'), 'php'),
                metric('orders', 'Orders in range', ...totals('orders')),
                metric('attention', 'Needs attention', attention(latest), attention(previous), 'count', `${latest.disputes} disputes · ${latest.refunds} refunds · ${latest.approvals} approvals · ${latest.payouts} payouts at range end`),
            ],
            alerts: [{ text: `${total(rows, 'failed')} delivery failures in this sample range.`, key: 'failures', count: total(rows, 'failed') }],
            charts: [
                graph('gmv', 'GMV and commission trend', 'line', labels, [forDays('GMV', 'gmv'), forDays('Commission', 'commission')], 'php'),
                graph('orders', 'Orders by status', 'bar', labels, [series('Processed', rows.map(row => row.orders - row.pending - row.failed)), forDays('Pending', 'pending'), forDays('Failed', 'failed')], 'count', true),
                graph('growth', 'New buyers and sellers', 'line', labels, [forDays('Buyers', 'buyers'), forDays('Sellers', 'sellers')]),
                graph('categories', 'Top categories by GMV', 'bar', categories, [series('GMV', byWeight(total(rows, 'gmv'), [38, 29, 20, 13]))], 'php'),
            ],
            tables: [
                tracker('approvals', 'Seller approval queue', ['Sample shop', 'Status'], sellers.slice(0, latest.approvals).map(name => datedRow(latest.date, [name, 'Pending approval'])), 'Open seller approvals'),
                tracker('disputes', 'Flagged and disputed orders', ['Sample order', 'Flag'], recent.filter(row => row.disputes || row.failed).map((row, i) => datedRow(row.date, [`DEMO-A${i + 1}`, row.failed ? 'Delivery failed' : 'Dispute open'])), 'Open return and dispute queue'),
                tracker('top', 'Top sellers', ['Sample shop', 'GMV'], sellers.map((name, i) => datedRow(latest.date, [name, formatMetric(byWeight(total(rows, 'gmv'), [35, 30, 20, 15])[i], 'php')])), 'Open partner directory'),
                tracker('fleet', 'Rider fleet status', ['Availability', 'Riders'], ['Active', 'Idle', 'Offline'].map((label, i) => datedRow(latest.date, [label, latest.fleet[i]])), 'Open rider directory'),
            ],
        };
    }

    const completed = total(rows, 'completed');
    const oldCompleted = total(previousRows, 'completed');
    const completedRate = (row) => rate(row.completed, row.completed + row.assigned + row.failed);
    return {
        kpis: [
            metric('active', 'Active deliveries', latest.assigned, previous.assigned, 'count', latest.assigned ? 'Next sample shipment: DEMO-R1 · Pickup pending' : 'No active sample shipments at range end'),
            metric('earnings', 'Earnings in range', ...totals('earnings'), 'php'),
            metric('completed', 'Deliveries completed', completed, oldCompleted, 'count', `${latest.assigned} pending at range end`),
            metric('cod', 'COD to remit', latest.cod, previous.cod, 'php', 'Sample balance at range end'),
        ],
        alerts: [{ text: `${total(rows, 'failed')} failed deliveries in this sample range.`, key: 'active', count: total(rows, 'failed') }],
        charts: [
            graph('earnings', 'Earnings by day', 'bar', labels, [forDays('Earnings', 'earnings')], 'php'),
            graph('rates', 'Completion and on-time rates', 'line', labels, [
                series('Completion', rows.map(completedRate)), series('On time', rows.map(row => rate(row.onTime, row.completed))),
            ], 'percent'),
        ],
        tables: [
            tracker('active', 'Upcoming assigned deliveries', ['Sample shipment', 'Next step'], Array.from({ length: latest.assigned }, (_, i) => datedRow(latest.date, [`DEMO-R${i + 1}`, 'Pickup pending'])), 'Open active deliveries'),
            tracker('history', 'Delivery history', ['Completed', 'Failed', 'Earnings'], recent.map(row => datedRow(row.date, [row.completed, row.failed, formatMetric(row.earnings, 'php')])), 'Open delivery history'),
            tracker('ratings', 'Buyer ratings', ['Rating', 'Sample feedback'], recent.map((row, i) => datedRow(row.date, [i === 1 ? '4 / 5' : '5 / 5', i === 1 ? 'Good communication' : 'Delivered on time'])), 'Open rider feedback'),
        ],
    };
}

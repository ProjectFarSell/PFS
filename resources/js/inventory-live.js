import { createStockPoller } from './stock-poller.js';

for (const section of document.querySelectorAll('[data-live-inventory]')) {
    const rows = [...section.querySelectorAll('[data-inventory-product]')];
    const url = new URL(section.dataset.stockUrl, location.origin);
    for (const row of rows) url.searchParams.append('ids[]', row.dataset.inventoryProduct);
    const status = section.querySelector('[data-stock-refresh-status]');
    const say = text => { if (status) status.textContent = text; };
    createStockPoller({
        url: url.toString(),
        onData(data) {
            if (!Array.isArray(data.products)) throw new Error('Invalid stock response');
            const products = new Map(data.products.map(product => [String(product.id), product]));
            for (const row of rows) {
                const product = products.get(row.dataset.inventoryProduct);
                const stock = row.querySelector('[data-inventory-stock]');
                if (stock) {
                    const amount = Number(product?.stock ?? 0);
                    stock.textContent = !product ? 'Unavailable' : `${amount.toLocaleString()}${section.dataset.stockLabels === 'true' ? (amount === 0 ? ' · Out of stock' : amount <= 5 ? ' · Low stock' : '') : product.active ? '' : ' · Inactive'}`;
                    stock.classList.toggle('text-error', !product || amount <= 5);
                    stock.classList.toggle('font-semibold', !product || amount <= 5);
                }
                const listing = row.querySelector('[data-inventory-listing]');
                if (listing) {
                    listing.textContent = product?.active ? 'Active' : 'Inactive';
                    listing.classList.toggle('badge-success', !!product?.active);
                    listing.classList.toggle('badge-neutral', !product?.active);
                }
                for (const target of row.querySelectorAll('[data-inventory-variant]')) {
                    const variant = product?.variants.find(variant => String(variant.id) === target.dataset.inventoryVariant);
                    target.textContent = variant?.active ? `${Number(variant.stock).toLocaleString()} in stock` : 'Unavailable';
                }
            }
            for (const target of document.querySelectorAll('[data-seller-inventory-stat]')) {
                const value = data.stats?.[target.dataset.sellerInventoryStat];
                if (Number.isFinite(value)) target.textContent = value.toLocaleString();
            }
            say(`Updated ${new Date().toLocaleTimeString()}`);
        },
        onError(error) {
            say([401, 403, 423].includes(error.status) ? 'Refresh the page and sign in again to update inventory.' : 'Stock refresh unavailable; showing the last loaded quantities.');
        },
    });
}

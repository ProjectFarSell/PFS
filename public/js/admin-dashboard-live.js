(() => {
    const dashboard = document.querySelector('[data-admin-dashboard][data-summary-url]');
    if (!dashboard) return;

    const summaryUrl = dashboard.dataset.summaryUrl;
    const list = document.querySelector('[data-low-stock-list]');
    const count = document.querySelector('[data-low-stock-count]');
    const updated = document.querySelector('[data-inventory-updated]');
    let refreshing = false;

    function updateLowStock(products) {
        if (!list) return;
        list.replaceChildren();
        if (!products.length) {
            const row = document.createElement('li');
            row.className = 'py-4 text-sm text-text-muted';
            row.textContent = 'No active products are low on stock.';
            list.append(row);
            return;
        }

        for (const product of products) {
            const row = document.createElement('li');
            row.className = 'flex items-center justify-between gap-3 py-3';

            const details = document.createElement('div');
            details.className = 'min-w-0';
            const link = document.createElement('a');
            link.className = 'break-words text-sm font-medium text-accent underline';
            link.href = product.url;
            link.textContent = product.name;
            const shop = document.createElement('p');
            shop.className = 'mt-1 text-xs text-text-muted';
            shop.textContent = product.shop;
            details.append(link, shop);

            const stock = document.createElement('span');
            stock.className = `shrink-0 text-xs font-semibold tabular-nums ${product.stock <= 0 ? 'text-error' : 'text-text-base'}`;
            stock.textContent = product.stock <= 0 ? 'Out of stock' : `${product.stock} left`;
            row.append(details, stock);
            list.append(row);
        }
    }

    async function refresh() {
        if (refreshing || document.hidden) return;
        refreshing = true;
        try {
            const response = await fetch(summaryUrl, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
                cache: 'no-store',
            });
            if (!response.ok) throw new Error('Dashboard refresh failed');
            const data = await response.json();
            if (!data.stats || !Array.isArray(data.lowStockProducts)) throw new Error('Invalid dashboard data');

            for (const [key, value] of Object.entries(data.stats)) {
                const target = document.querySelector(`[data-admin-stat-value="${CSS.escape(key)}"]`);
                if (target) {
                    const formatted = Number(value).toLocaleString();
                    target.textContent = formatted;
                    const card = target.closest('a');
                    if (card) card.setAttribute('aria-label', `${card.dataset.adminStatLabel}: ${formatted}. ${card.dataset.adminStatDescription}`);
                }
            }
            if (count) count.textContent = Number(data.lowStockCount).toLocaleString();
            updateLowStock(data.lowStockProducts);
            if (updated) updated.textContent = `Updated ${new Date().toLocaleTimeString()}`;
        } catch {
            if (updated) updated.textContent = 'Live refresh unavailable; showing the last loaded data.';
        } finally {
            refreshing = false;
        }
    }

    refresh();
    window.setInterval(refresh, 10000);
    document.addEventListener('visibilitychange', refresh);
})();

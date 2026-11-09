import { createStockPoller } from './stock-poller.js';

export default function productOptions(variants = [], optionNames = [], availability = {}) {
    return {
        qty: 1,
        variants,
        optionNames,
        baseStock: Number(availability.stock ?? 0),
        listingAvailable: true,
        optionsChanged: false,
        stockMessage: '',
        poller: null,
        choices: Object.fromEntries(optionNames.map((name) => [name, ''])),
        restoreTimer: null,
        init() {
            this.restoreSelection();
            if (availability.url) {
                this.poller = createStockPoller({
                    url: availability.url,
                    onData: data => this.applyAvailability(data),
                    onError: error => {
                        if (error.status === 404) {
                            this.listingAvailable = false;
                            this.stockMessage = 'This listing is no longer available.';
                        } else {
                            this.stockMessage = 'Could not refresh stock. Availability will be checked at checkout.';
                        }
                    },
                });
            }
        },
        destroy() {
            clearTimeout(this.restoreTimer);
            this.poller?.stop();
        },
        restoreSelection() {
            clearTimeout(this.restoreTimer);
            // History traversal can restore native form controls after pageshow.
            // Wait for that restoration and Alpine's option rendering before reading them.
            this.restoreTimer = setTimeout(() => {
                this.restoreTimer = null;
                this.$nextTick(() => this.syncRestoredFields());
            }, 0);
        },
        syncRestoredFields() {
            const fields = [...this.$el.querySelectorAll('[data-product-option]')];
            this.choices = Object.fromEntries(this.optionNames.map((name) => {
                const value = fields.find((field) => field.dataset.productOption === name)?.value ?? '';
                return [name, this.valuesFor(name).includes(value) ? value : ''];
            }));
            const input = this.$el.querySelector('input[name="qty"]');
            const limit = this.optionNames.length ? this.selectedVariant()?.stock ?? 1 : Number(availability.stock ?? input?.max ?? 99);
            this.qty = Math.max(1, Math.min(99, Math.max(1, limit), Math.trunc(Number(input?.value) || 1)));
        },
        applyAvailability(data) {
            if (!Number.isInteger(data.stock) || data.stock < 0 || typeof data.has_variants !== 'boolean'
                || !Array.isArray(data.variants) || data.variants.some(variant => !Number.isInteger(variant.stock) || variant.stock < 0 || !variant.options)) {
                throw new Error('Invalid availability response');
            }
            const names = [...new Set(data.variants.flatMap(variant => Object.keys(variant.options)))];
            // Structural changes need a fresh form; never silently buy a different option.
            this.optionsChanged = data.has_variants !== (availability.hasVariants ?? this.optionNames.length > 0)
                || (data.variants.length > 0 && names.slice().sort().join('\0') !== this.optionNames.slice().sort().join('\0'));
            this.listingAvailable = data.available === true;
            this.baseStock = data.stock;
            if (!this.optionsChanged) {
                this.variants = data.variants;
                this.choices = Object.fromEntries(this.optionNames.map(name => [name, this.valuesFor(name).includes(this.choices[name]) ? this.choices[name] : '']));
            }
            const previousQty = this.qty;
            this.qty = Math.max(1, Math.min(this.quantityLimit() || 1, Number(this.qty) || 1));
            this.stockMessage = this.optionsChanged ? 'Product options changed. Refresh this page to continue.'
                : !this.listingAvailable ? 'This listing is no longer available.'
                : previousQty !== this.qty ? 'Available stock changed; quantity has been adjusted.' : '';
        },
        quantityLimit() {
            if (!this.listingAvailable || this.optionsChanged) return 0;
            const stock = this.optionNames.length ? this.selectedVariant()?.stock ?? 0 : this.baseStock;
            return Math.min(99, Math.max(0, Number(stock) || 0));
        },
        canPurchase() {
            return this.quantityLimit() > 0;
        },
        stockLabel() {
            if (!this.listingAvailable) return 'UNAVAILABLE';
            return (this.optionNames.length ? this.selectedVariant()?.stock ?? this.baseStock : this.baseStock) > 0 ? 'IN STOCK' : 'OUT OF STOCK';
        },
        valuesFor(name) {
            return [...new Set(this.variants.map((variant) => variant.options[name]).filter(Boolean))];
        },
        isAvailable(name, value) {
            return this.variants.some((variant) => variant.options[name] === value
                && this.optionNames.every((other) => other === name || !this.choices[other] || variant.options[other] === this.choices[other]));
        },
        clearUnavailable(name) {
            for (const other of this.optionNames) {
                if (other !== name && this.choices[other] && !this.isAvailable(other, this.choices[other])) {
                    this.choices[other] = '';
                }
            }
        },
        selectedVariant() {
            if (!this.optionNames.length || this.optionNames.some((name) => !this.choices[name])) return null;
            return this.variants.find((variant) => this.optionNames.every((name) => variant.options[name] === this.choices[name])) ?? null;
        },
    };
}

const currency = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP', maximumFractionDigits: 2 });
const numbers = new Intl.NumberFormat('en-PH', { maximumFractionDigits: 1 });

export function formatMetric(value, unit = 'count') {
    if (value === null || value === undefined) return '—';
    if (unit === 'php') return currency.format(value);
    return numbers.format(value) + (unit === 'percent' ? '%' : '');
}

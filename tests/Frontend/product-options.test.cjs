const { test } = require('node:test');
const assert = require('node:assert/strict');

async function setup(values = ['', ''], quantity = '1') {
    const { default: productOptions } = await import('../../resources/js/product-options.js');
    const variants = [
        { id: 1, sku: 'SHIRT-BKS', stock: 5, options: { Size: 'S', Color: 'Black' } },
        { id: 2, sku: 'SHIRT-BLM', stock: 2, options: { Size: 'M', Color: 'Blue' } },
        { id: 3, sku: 'SHIRT-RDL', stock: 0, options: { Size: 'L', Color: 'Red' } },
    ];
    const fields = ['Size', 'Color'].map((name, index) => ({ dataset: { productOption: name }, value: values[index] }));
    const input = { value: quantity, max: '99' };
    const component = productOptions(variants, ['Size', 'Color']);
    component.$el = { querySelectorAll: () => fields, querySelector: () => input };
    component.$nextTick = callback => callback();
    return { component, fields, input };
}

test('native restored dropdowns restore the selected variant, SKU and quantity', async () => {
    const { component } = await setup(['S', 'Black'], '3');
    assert.equal(component.selectedVariant(), null);
    component.syncRestoredFields();
    assert.equal(component.selectedVariant().id, 1);
    assert.equal(component.selectedVariant().sku, 'SHIRT-BKS');
    assert.equal(component.qty, 3);
});

test('history restoration is read after the event rather than before native controls restore', async () => {
    const { component, fields } = await setup();
    component.restoreSelection();
    fields[0].value = 'M';
    fields[1].value = 'Blue';
    await new Promise(resolve => setTimeout(resolve, 15));
    assert.equal(component.selectedVariant().id, 2);
    component.destroy();
});

test('fresh and incomplete selections stay unselected; unknown options cannot enable a purchase', async () => {
    for (const values of [['', ''], ['S', ''], ['XL', 'Black'], ['S', 'Blue']]) {
        const { component } = await setup(values);
        component.syncRestoredFields();
        assert.equal(component.selectedVariant(), null);
    }
});

test('a sold-out restored variant remains sold out and restored quantity respects stock', async () => {
    const { component } = await setup(['L', 'Red'], '9');
    component.syncRestoredFields();
    assert.equal(component.selectedVariant().stock, 0);
    assert.equal(component.qty, 1);
    const { component: inStock } = await setup(['M', 'Blue'], '9');
    inStock.syncRestoredFields();
    assert.equal(inStock.qty, 2);
});

test('stock refresh preserves selected options, clamps quantity, and handles sell-out/restock', async () => {
    const { component } = await setup(['S', 'Black'], '4');
    component.syncRestoredFields();
    const data = { available: true, has_variants: true, stock: 2, variants: component.variants.map(v => ({ ...v, stock: v.id === 1 ? 2 : 0 })) };
    component.applyAvailability(data);
    assert.equal(component.selectedVariant().id, 1);
    assert.equal(component.qty, 2);
    assert.equal(component.quantityLimit(), 2);
    assert.equal(component.canPurchase(), true);
    component.applyAvailability({ ...data, stock: 0, variants: data.variants.map(v => ({ ...v, stock: 0 })) });
    assert.equal(component.stockLabel(), 'OUT OF STOCK');
    assert.equal(component.canPurchase(), false);
    component.applyAvailability(data);
    assert.equal(component.canPurchase(), true);
    assert.equal(component.selectedVariant().id, 1);
});

test('changed option structure blocks stale purchases and unknown data cannot erase good stock', async () => {
    const { component } = await setup(['S', 'Black']);
    component.syncRestoredFields();
    assert.throws(() => component.applyAvailability({ stock: -1, variants: [] }));
    assert.equal(component.selectedVariant().stock, 5);
    component.applyAvailability({ available: true, has_variants: false, stock: 10, variants: [] });
    assert.equal(component.optionsChanged, true);
    assert.equal(component.canPurchase(), false);
});

test('plain products update quantity limits and retain the 99-unit cap', async () => {
    const { default: productOptions } = await import('../../resources/js/product-options.js');
    const component = productOptions([], [], { stock: 150, hasVariants: false });
    assert.equal(component.quantityLimit(), 99);
    component.qty = 40;
    component.applyAvailability({ available: true, has_variants: false, stock: 3, variants: [] });
    assert.equal(component.qty, 3);
    component.applyAvailability({ available: true, has_variants: false, stock: 0, variants: [] });
    assert.equal(component.canPurchase(), false);
});

const { test } = require('node:test');
const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const { join } = require('node:path');
const { runInNewContext } = require('node:vm');
const source = readFileSync(join(__dirname, '../../public/js/address-location.js'), 'utf8');
const settle = () => new Promise((resolve) => setImmediate(resolve));

function setup(fetch, saved = ['', '', '', '']) {
    function Option(text, value) { this.textContent = text; this.value = value; }
    const fields = saved.map((value, i) => ({
        value: i === 0 ? value : '', disabled: i > 0,
        dataset: { selected: value, url: '/locations/' + i, parentParam: 'parent' },
        options: [new Option('Select', '')], listeners: {},
        replaceChildren(...options) { this.options = options; this.value = ''; },
        addEventListener(name, callback) { this.listeners[name] = callback; },
        change(value) { this.value = value; this.listeners.change(); },
    }));
    const status = { textContent: '' };
    const save = { disabled: true };
    const retry = { hidden: true, addEventListener(name, callback) { this.click = callback; } };
    const elements = ['psgc_region_id', 'psgc_province_id', 'psgc_city_municipality_id', 'psgc_barangay_id'];
    const form = {
        dataset: {}, elements: { namedItem: (name) => fields[elements.indexOf(name)] },
        querySelector: (selector) => ({ '[data-location-status]': status, '[data-location-retry]': retry, '[data-address-save]': save })[selector],
        addEventListener(name, callback) { this[name] = callback; },
    };
    runInNewContext(source, {
        document: { querySelectorAll: () => [form] }, window: { location: { origin: 'http://localhost' } },
        fetch, Option, URL, AbortController, setTimeout, clearTimeout,
    });
    return { fields, status, save, retry, form };
}
const response = (id) => ({ ok: true, json: async () => [{ id, name: 'Location ' + id }] });

test('cascades without Alpine and clears descendants when a parent changes', async () => {
    const calls = [];
    const ui = setup(async (url) => { calls.push(url); return response(String(calls.length)); });
    ui.fields[0].change('region'); await settle();
    assert.equal(ui.fields[1].disabled, false);
    ui.fields[1].change('1'); await settle();
    ui.fields[2].change('2'); await settle();
    ui.fields[3].change('3');
    assert.equal(ui.save.disabled, false);
    assert.equal(calls[1].searchParams.get('parent'), '1');
    ui.fields[0].change('different'); await settle();
    assert.equal(ui.fields[2].value, '');
    assert.equal(ui.fields[3].disabled, true);
    assert.equal(ui.save.disabled, true);
});

test('restores saved selections after options load', async () => {
    let level = 1;
    const ui = setup(async () => response(String(++level)), ['1', '2', '3', '4']);
    await settle();
    assert.deepEqual(ui.fields.map((field) => field.value), ['1', '2', '3', '4']);
    assert.equal(ui.save.disabled, false);
});

test('failed requests show retry and keep incomplete addresses unsavable', async () => {
    let failed = true;
    const ui = setup(async () => failed ? { ok: false } : response('2'));
    ui.fields[0].change('1'); await settle();
    assert.equal(ui.retry.hidden, false);
    assert.match(ui.status.textContent, /Unable to load/);
    assert.equal(ui.save.disabled, true);
    failed = false;
    ui.retry.click(); await settle();
    assert.equal(ui.retry.hidden, true);
    assert.equal(ui.fields[1].disabled, false);
});

test('a slow earlier response cannot overwrite the current region choices', async () => {
    let resolveOld;
    const ui = setup((url) => url.searchParams.get('parent') === 'old'
        ? new Promise((resolve) => { resolveOld = resolve; }) : Promise.resolve(response('new')));
    ui.fields[0].change('old');
    ui.fields[0].change('new'); await settle();
    resolveOld(response('old')); await settle();
    assert.equal(ui.fields[1].options[1].value, 'new');
});

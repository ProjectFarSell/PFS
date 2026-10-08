const { test } = require('node:test');
const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const { join } = require('node:path');
const { runInNewContext } = require('node:vm');
const source = readFileSync(join(__dirname, '../../public/js/theme.js'), 'utf8');

function setup({ saved = null, dark = false, blocked = false } = {}) {
    const classes = new Set();
    const writes = [];
    const events = {};
    const media = { matches: dark, addEventListener: (key, callback) => { events.os = callback; } };
    const window = {
        matchMedia: () => media,
        addEventListener: (key, callback) => { events[key] = callback; },
        localStorage: {
            getItem: () => { if (blocked) throw new Error('blocked'); return saved; },
            setItem: (key, value) => { if (blocked) throw new Error('blocked'); writes.push([key, value]); },
        },
    };
    runInNewContext(source, { window, document: { documentElement: { classList: {
        toggle: (name, enabled) => enabled ? classes.add(name) : classes.delete(name),
    } } } });
    return { window, theme: window.FarSellTheme, classes, writes, events, media };
}

test('stored preference wins over the system preference before paint', () => {
    const ui = setup({ saved: 'light', dark: true });
    assert.equal(ui.theme.isDark, false);
    assert.equal(ui.classes.has('dark'), false);
    assert.equal(setup({ saved: 'dark' }).classes.has('dark'), true);
});

test('all toggles share state and persist both directions', () => {
    const ui = setup();
    const header = ui.theme;
    const footer = ui.theme;
    header.toggle();
    assert.equal(footer.isDark, true);
    assert.equal(ui.classes.has('dark'), true);
    footer.toggle();
    assert.equal(header.isDark, false);
    assert.equal(ui.classes.has('dark'), false);
    assert.deepEqual(ui.writes, [['farsell_theme', 'dark'], ['farsell_theme', 'light']]);
});

test('blocked local storage does not break initialization or toggles', () => {
    const ui = setup({ blocked: true, dark: true });
    assert.equal(ui.theme.isDark, false);
    assert.doesNotThrow(() => ui.theme.toggle());
    assert.equal(ui.theme.isDark, true);
});

test('light mode is the default even when the system uses dark mode', () => {
    const ui = setup({ saved: 'invalid', dark: true });
    assert.equal(ui.theme.isDark, false);
});

test('storage changes synchronize other tabs and clearing reverts to light mode', () => {
    const ui = setup();
    ui.events.storage({ key: 'farsell_theme', newValue: 'dark' });
    assert.equal(ui.theme.isDark, true);
    ui.events.storage({ key: 'unrelated', newValue: 'light' });
    assert.equal(ui.theme.isDark, true);
    ui.events.storage({ key: null, newValue: null });
    assert.equal(ui.theme.isDark, false);
    assert.equal(ui.writes.length, 0);
});

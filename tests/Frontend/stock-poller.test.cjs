const { test } = require('node:test');
const assert = require('node:assert/strict');
const settle = () => new Promise(resolve => setImmediate(resolve));

function events() {
    const handlers = new Map();
    return { handlers, hidden: false,
        addEventListener: (name, fn) => handlers.set(name, fn),
        removeEventListener: name => handlers.delete(name),
        emit(name) { handlers.get(name)?.(); },
        setInterval(fn) { this.interval = fn; return 1; },
        clearInterval() { this.interval = null; },
    };
}

test('polls while visible, resumes on focus/pageshow and cleans up listeners', async () => {
    const { createStockPoller } = await import('../../resources/js/stock-poller.js');
    const documentRef = events(), windowRef = events(), received = [];
    let calls = 0;
    const poller = createStockPoller({ url: '/stock', documentRef, windowRef,
        fetchImpl: async () => ({ ok: true, json: async () => ({ stock: ++calls }) }), onData: data => received.push(data.stock) });
    await settle();
    documentRef.hidden = true; documentRef.emit('visibilitychange'); windowRef.interval(); await settle();
    assert.equal(calls, 1);
    documentRef.hidden = false; documentRef.emit('visibilitychange'); await settle();
    windowRef.emit('focus'); await settle();
    windowRef.emit('pagehide'); windowRef.interval(); await settle();
    assert.equal(calls, 3);
    windowRef.emit('pageshow'); await settle();
    assert.deepEqual(received, [1, 2, 3, 4]);
    poller.stop();
    assert.equal(windowRef.handlers.size, 0);
    assert.equal(documentRef.handlers.size, 0);
    assert.equal(windowRef.interval, null);
});

test('an aborted older request cannot overwrite fresher stock even if fetch ignores abort', async () => {
    const { createStockPoller } = await import('../../resources/js/stock-poller.js');
    const documentRef = events(), windowRef = events(), requests = [], received = [];
    const poller = createStockPoller({ url: '/stock', documentRef, windowRef,
        fetchImpl: () => new Promise(resolve => requests.push(resolve)), onData: data => received.push(data.stock) });
    windowRef.emit('pagehide'); windowRef.emit('pageshow');
    requests[1]({ ok: true, json: async () => ({ stock: 1 }) }); await settle();
    requests[0]({ ok: true, json: async () => ({ stock: 99 }) }); await settle();
    assert.deepEqual(received, [1]);
    poller.stop();
});

test('failed requests keep the last good data and subsequent refresh can recover', async () => {
    const { createStockPoller } = await import('../../resources/js/stock-poller.js');
    const documentRef = events(), windowRef = events(), received = [], errors = [];
    let ok = false;
    const poller = createStockPoller({ url: '/stock', documentRef, windowRef,
        fetchImpl: async () => ({ ok, status: 503, json: async () => ({ stock: 2 }) }), onData: data => received.push(data.stock), onError: error => errors.push(error.status) });
    await settle();
    assert.deepEqual(received, []);
    assert.deepEqual(errors, [503]);
    ok = true; await poller.refresh();
    assert.deepEqual(received, [2]);
    poller.stop();
});

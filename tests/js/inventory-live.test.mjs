import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';
import test from 'node:test';

const source = readFileSync(new URL('../../resources/js/inventory-live.js', import.meta.url), 'utf8');

function fixture(fetchResponse = async () => ({ ok: true, status: 200, json: async () => ({ html: 'updated stock' }) })) {
    const handlers = {};
    const state = { open: false, requests: 0, interval: null };
    const results = {
        innerHTML: 'original stock',
        contains: () => false,
        setAttribute() {}, removeAttribute() {},
        querySelectorAll: () => [], querySelector: () => null,
    };
    const status = { textContent: '' };
    const button = { disabled: false, addEventListener: (event, callback) => { handlers.click = callback; } };
    const document = {
        hidden: false, activeElement: null,
        addEventListener: (event, callback) => { handlers[event] = callback; },
        querySelector: () => ({ querySelector: (selector) => ({
            '[data-inventory-results]': results,
            '[data-inventory-live-status]': status,
            '[data-inventory-refresh]': button,
            'dialog[open]': state.open,
        })[selector] }),
    };
    const window = {
        location: { href: 'http://localhost/inventory?search=rice&page=2' },
        addEventListener: (event, callback) => { handlers[event] = callback; },
        setInterval: (callback) => { state.interval = callback; return 1; },
        clearInterval: () => { state.interval = null; },
        setTimeout: () => 2, clearTimeout() {},
    };
    runInNewContext(source, {
        document, window, AbortController, CSS: { escape: (value) => value },
        fetch: async (...args) => { state.requests++; state.request = args; return fetchResponse(...args); },
    });
    return { state, results, status, button, document, handlers };
}

test('refresh keeps the selected filters and pagination and replaces stock', async () => {
    const f = fixture();
    await f.handlers.click();
    assert.equal(f.state.request[0], 'http://localhost/inventory?search=rice&page=2');
    assert.equal(f.state.request[1].headers.Accept, 'application/json');
    assert.equal(f.results.innerHTML, 'updated stock');
    assert.equal(f.button.disabled, false);
});

test('polling preserves an open stock form and resumes after it closes', async () => {
    const f = fixture();
    f.state.open = true;
    await f.state.interval();
    assert.equal(f.state.requests, 0);
    assert.equal(f.results.innerHTML, 'original stock');
    f.state.open = false;
    await f.state.interval();
    assert.equal(f.results.innerHTML, 'updated stock');
});

test('a response arriving after a form opens cannot overwrite that form', async () => {
    let respond;
    const f = fixture(() => new Promise((resolve) => { respond = resolve; }));
    const refresh = f.handlers.click();
    f.state.open = true;
    respond({ ok: true, status: 200, json: async () => ({ html: 'new form with empty inputs' }) });
    await refresh;
    assert.equal(f.results.innerHTML, 'original stock');
});

test('network failures preserve current stock and allow retry', async () => {
    let fail = true;
    const f = fixture(async () => {
        if (fail) throw new Error('offline');
        return { ok: true, status: 200, json: async () => ({ html: 'retried stock' }) };
    });
    await f.handlers.click();
    assert.equal(f.results.innerHTML, 'original stock');
    assert.match(f.status.textContent, /Could not update/);
    fail = false;
    await f.handlers.click();
    assert.equal(f.results.innerHTML, 'retried stock');
});

test('expired access stops polling without replacing results with a login page', async () => {
    const f = fixture(async () => ({ status: 401 }));
    await f.handlers.click();
    await f.state.interval();
    assert.equal(f.state.requests, 1);
    assert.equal(f.results.innerHTML, 'original stock');
});

test('returning through the back cache resumes polling', async () => {
    const f = fixture();
    f.handlers.pagehide();
    assert.equal(f.state.interval, null);
    await f.handlers.pageshow({ persisted: true });
    assert.equal(typeof f.state.interval, 'function');
    assert.equal(f.results.innerHTML, 'updated stock');
});

'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

function deferred() {
    let resolve, reject;
    const promise = new Promise((done, fail) => { resolve = done; reject = fail; });
    return { promise, resolve, reject };
}

const flush = () => new Promise(resolve => setImmediate(resolve));

function browser(webSession = true) {
    const calls = [], alerts = [], attributes = new Map(), handlers = new Map();
    const storage = new Map([
        ['cccontrol.auth.token', 'qa-bearer-token'],
        ['cccontrol.auth.user', JSON.stringify({ id: 18 })],
    ]);
    const csrf = { content: 'qa-csrf' };
    const cookies = { session: 'old-authenticated-session' };
    const link = {
        dataset: {},
        addEventListener: (name, handler) => handlers.set(name, handler),
        setAttribute: (name, value) => attributes.set(name, value),
        removeAttribute: name => attributes.delete(name),
    };
    let setup;
    const document = {
        querySelector: selector => selector.includes('ccc-auth')
            ? (webSession ? {} : null) : (selector.includes('csrf-token') ? csrf : null),
        querySelectorAll: selector => selector === '[data-api-logout]' ? [link] : [],
        addEventListener: (name, callback) => { if (name === 'DOMContentLoaded') setup = callback; },
    };
    const window = {
        document,
        localStorage: {
            getItem: key => storage.get(key) || null,
            setItem: (key, value) => storage.set(key, value),
            removeItem: key => storage.delete(key),
        },
        location: { href: '/admin-web/products' },
        addEventListener() {},
        setTimeout,
        alert: message => alerts.push(message),
        fetch(url, options) {
            const response = deferred();
            options.signal.addEventListener('abort', () => {
                response.reject(Object.assign(new Error('The operation was aborted'), { name: 'AbortError' }));
            });
            calls.push({ url, options, response });
            return response.promise;
        },
    };
    const context = vm.createContext({ window, document, AbortController, FormData, URL, Event });
    for (const script of ['api-client.js', 'auth-api.js']) {
        const filename = path.resolve(__dirname, '../../public/js', script);
        vm.runInContext(fs.readFileSync(filename, 'utf8'), context, { filename });
    }
    setup();
    return {
        api: window.CCApi, window, calls, cookies, storage, alerts, attributes,
        clickLogout: () => handlers.get('click')({ preventDefault() {} }),
        reply(index, { status = 200, cookie = 'old-authenticated-session', body } = {}) {
            cookies.session = cookie;
            calls[index].response.resolve({
                status, ok: status >= 200 && status < 300,
                headers: { get: () => null },
                text: () => body || Promise.resolve(JSON.stringify(status >= 400
                    ? { error: { message: 'QA request failed' } } : { data: [] })),
            });
        },
    };
}

test('logout waits for pending reads and writes, blocks new requests and keeps its cookie last', async () => {
    const b = browser();
    const reading = b.api.request('/api/v1/admin/products');
    const writing = b.api.request('/api/v1/admin/products/1', { method: 'PATCH', body: { name: 'QA' } });
    b.clickLogout();
    b.clickLogout();
    await flush();
    assert.equal(b.calls.length, 2, 'logout must not start while old HTTP responses can still set cookies');
    assert.equal(b.attributes.get('aria-busy'), 'true');
    await assert.rejects(b.api.request('/api/v1/brands'), error => error.code === 'SESSION_CLOSING');
    await assert.rejects(b.api.request('/api/v1/admin/products', { method: 'POST' }), error => error.code === 'SESSION_CLOSING');
    assert.equal(b.calls.length, 2, 'blocked requests never reach fetch');
    assert.ok(b.calls.every(call => !call.options.signal.aborted), 'drain must not abort HTTP requests');
    b.reply(1);
    await writing;
    await flush();
    assert.equal(b.calls.length, 2, 'the remaining read still prevents logout');
    b.reply(0);
    await reading;
    await flush();
    assert.equal(b.calls.length, 3);
    assert.equal(b.calls[2].url, '/web-session/logout');
    assert.equal(b.calls[2].options.headers['X-CSRF-TOKEN'], 'qa-csrf');
    b.reply(2, { status: 204, cookie: 'logged-out-session' });
    await flush();
    assert.equal(b.cookies.session, 'logged-out-session');
    assert.equal(b.window.location.href, '/login');
    assert.equal(b.storage.has('cccontrol.auth.user'), false);
    await assert.rejects(b.api.request('/api/v1/auth/me'), error => error.code === 'SESSION_CLOSING');
    assert.equal(b.calls.length, 3, 'the successful close stays sealed until navigation');
});

test('logout drains the response body too and duplicate close calls share one POST', async () => {
    const b = browser();
    const body = deferred();
    const reading = b.api.request('/api/v1/units');
    const closing = b.api.closeWebSession();
    assert.equal(b.api.closeWebSession(), closing);
    b.reply(0, { body: body.promise });
    await flush();
    assert.equal(b.calls.length, 1, 'headers alone do not finish an in-flight request');
    body.resolve('{"data":[]}');
    await reading;
    await flush();
    assert.equal(b.calls.length, 2);
    b.reply(1, { status: 204, cookie: 'logged-out-session' });
    await closing;
    assert.equal(b.calls.filter(call => call.url === '/web-session/logout').length, 1);
});

test('failed requests settle the drain, failed logout restores requests and the logout link can retry', async () => {
    const b = browser();
    const reading = b.api.request('/api/v1/admin/products');
    const rejectedReading = assert.rejects(reading, error => error.status === 503);
    b.clickLogout();
    b.reply(0, { status: 503 });
    await rejectedReading;
    await flush();
    assert.equal(b.calls[1].url, '/web-session/logout');
    b.reply(1, { status: 500 });
    await flush();
    assert.equal(b.window.location.href, '/admin-web/products');
    assert.equal(b.storage.has('cccontrol.auth.user'), true);
    assert.equal(b.attributes.has('aria-busy'), false);
    assert.equal(b.alerts.length, 1);
    const retryRead = b.api.request('/api/v1/admin/products');
    assert.equal(b.calls.length, 3, 'a failed close must reopen the request gate');
    b.clickLogout();
    await flush();
    assert.equal(b.calls.length, 3, 'retry still drains new work');
    b.reply(2);
    await retryRead;
    await flush();
    assert.equal(b.calls[3].url, '/web-session/logout');
    b.reply(3, { status: 204, cookie: 'logged-out-session' });
    await flush();
    assert.equal(b.window.location.href, '/login');
    assert.equal(b.alerts.length, 1);
});

test('bearer logout keeps its existing endpoint and does not activate the web session gate', async () => {
    const b = browser(false);
    const first = b.api.request('/api/v1/products');
    b.clickLogout();
    assert.equal(b.calls.length, 2);
    assert.equal(b.calls[1].url, '/api/v1/auth/logout');
    assert.equal(b.calls[1].options.headers.Authorization, 'Bearer qa-bearer-token');
    assert.equal(b.calls[1].options.headers['X-CSRF-TOKEN'], undefined);
    const second = b.api.request('/api/v1/units');
    assert.equal(b.calls.length, 3);
    b.reply(0);
    b.reply(2);
    await Promise.all([first, second]);
    b.reply(1, { status: 204 });
    await flush();
    assert.equal(b.window.location.href, '/login');
});

test('page disposal during logout does not abort responses that still need draining', async () => {
    const b = browser();
    b.api.request('/api/v1/admin/products');
    const closing = b.api.closeWebSession();
    b.api.cancelPageReads();
    assert.equal(b.calls[0].options.signal.aborted, false);
    await flush();
    assert.equal(b.calls.length, 1);
    b.reply(0);
    await flush();
    assert.equal(b.calls[1].url, '/web-session/logout');
    b.reply(1, { status: 204, cookie: 'logged-out-session' });
    await closing;
    assert.equal(b.cookies.session, 'logged-out-session');
});


test('screen change before logout keeps the old web request alive until its body drains', async () => {
    const b = browser();
    const body = deferred();
    let staleRendered = false, staleError = false;
    b.api.request('/api/v1/admin/products').then(
        () => { staleRendered = true; },
        () => { staleError = true; }
    );
    b.api.cancelPageReads();
    await flush();
    const closing = b.api.closeWebSession();
    await flush();
    assert.equal(b.calls.length, 1, 'logout must still drain the request from the disposed screen');
    assert.equal(b.calls[0].options.signal.aborted, false, 'web session transport stays alive after navigation');
    b.reply(0, { body: body.promise });
    await flush();
    assert.equal(b.calls.length, 1, 'the old response headers do not permit logout before its body finishes');
    body.resolve('{"data":[{"id":1}]}');
    await flush();
    assert.equal(b.calls.length, 2);
    assert.equal(b.calls[1].url, '/web-session/logout');
    assert.equal(staleRendered, false, 'the old screen must not render into the current page');
    assert.equal(staleError, false, 'the old screen must not show an error after disposal');
    b.reply(1, { status: 204, cookie: 'logged-out-session' });
    await closing;
    assert.equal(b.cookies.session, 'logged-out-session');
});

test('screen changes still abort bearer reads and discard obsolete results', async () => {
    const b = browser(false);
    let staleSettled = false;
    b.api.request('/api/v1/products').then(
        () => { staleSettled = true; },
        () => { staleSettled = true; }
    );
    b.api.cancelPageReads();
    await flush();
    assert.equal(b.calls[0].options.signal.aborted, true);
    assert.equal(staleSettled, false);
    const current = b.api.request('/api/v1/units');
    b.reply(1);
    await current;
    assert.equal(b.calls[1].options.signal.aborted, false);
});

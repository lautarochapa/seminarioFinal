const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const source = fs.readFileSync(path.join(__dirname, '../../public/js/auth-api.js'), 'utf8');
const flush = () => new Promise(resolve => setImmediate(resolve));
function deferred() {
    let resolve, reject;
    const promise = new Promise((ok, fail) => { resolve = ok; reject = fail; });
    return { promise, resolve, reject };
}
function harness(dataset = {}) {
    const buttons = [0, 1].map(() => ({ dataset: {}, textContent: 'Registrarme', disabled: false }));
    const message = { style: {}, textContent: '', className: '' };
    const handlers = [];
    const attrs = {};
    const form = {
        dataset: { apiEndpoint: '/api/v1/auth/register', authSession: 'true', redirect: '/web', ...dataset },
        setAttribute: (key, value) => { attrs[key] = value; },
        querySelectorAll: selector => selector === '[type="submit"]' ? buttons : [],
        querySelector: selector => selector === '[data-api-message]' ? message : null,
        addEventListener: (event, handler) => { if (event === 'submit') handlers.push(handler); },
    };
    let ready;
    const calls = [];
    const window = {
        dispatchEvent() {},
        location: { origin: 'https://cocina.example.com', href: '' },
        CCApi: {
            request(route) { const call = { route, ...deferred() }; calls.push(call); return call.promise; },
            setSession() {}, getUser() { return null; },
        },
    };
    const document = {
        dispatchEvent() {},
        querySelectorAll: selector => selector === 'form[data-api-endpoint]' ? [form] : [],
        addEventListener: (event, handler) => { if (event === 'DOMContentLoaded') ready = handler; },
    };
    class FormData { entries() { return [['email', 'prueba@example.invalid']]; } }
    vm.runInNewContext(source, { window, document, URL, FormData, Event: class Event {} });
    ready();
    ready();
    assert.equal(handlers.length, 1, 'Do not bind a second submit handler.');
    return { buttons, message, attrs, calls, window, submit: () => handlers[0]({ preventDefault() {} }) };
}
const session = { token_payload: { permissions: ['web.user.dashboard'] } };

(async () => {
    const busy = harness();
    busy.submit(); busy.submit();
    assert.ok(busy.buttons.every(button => button.disabled));
    assert.equal(busy.attrs['aria-busy'], 'true');
    await flush();
    assert.equal(busy.calls.length, 1, 'Double click or Enter must send only one registration.');
    busy.calls[0].resolve({ data: {} });
    await flush();
    busy.submit();
    assert.equal(busy.calls.length, 2);
    assert.equal(busy.calls[1].route, '/api/v1/auth/me');
    busy.calls[1].resolve(session);
    await flush();
    assert.equal(busy.window.location.href, '/web');
    busy.submit(); await flush();
    assert.equal(busy.calls.length, 2, 'Keep locked until navigation finishes.');
    assert.ok(busy.buttons.every(button => button.disabled));

    const retry = harness();
    retry.submit(); await flush();
    retry.calls[0].reject(new Error('Validation failed'));
    await flush();
    assert.ok(retry.buttons.every(button => !button.disabled));
    assert.equal(retry.attrs['aria-busy'], 'false');
    retry.submit(); await flush();
    assert.equal(retry.calls.length, 2, 'A rejected registration can be corrected and resubmitted.');
    retry.calls[1].resolve({ data: {} }); await flush();
    retry.calls[2].reject(new Error('Session lookup interrupted')); await flush();
    assert.ok(retry.buttons.every(button => !button.disabled && button.textContent === 'Continuar'));
    retry.submit(); await flush();
    assert.equal(retry.calls[3].route, '/api/v1/auth/me');
    assert.equal(retry.calls.filter(call => call.route.endsWith('/register')).length, 2);
    retry.calls[3].resolve(session); await flush();
    assert.equal(retry.window.location.href, '/web');

    const reset = harness({ apiEndpoint: '/api/v1/auth/forgot-password', authSession: '', redirect: '', successMessage: 'Solicitud recibida.' });
    reset.submit(); reset.submit(); await flush();
    assert.equal(reset.calls.length, 1);
    reset.calls[0].resolve({}); await flush();
    assert.equal(reset.message.textContent, 'Solicitud recibida.');
    assert.ok(reset.buttons.every(button => !button.disabled));
    assert.equal(reset.window.location.href, '');
    console.log('OK: submit unico, bloqueo hasta redireccion, reintento de errores y sesion sin duplicar el alta.');
})().catch(error => { console.error(error); process.exitCode = 1; });

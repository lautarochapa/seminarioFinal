const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { JSDOM } = require(require.resolve('jsdom', { paths: [path.join(__dirname, '../../mobile')] }));
const base = path.join(__dirname, '../..');
const source = name => fs.readFileSync(path.join(base, 'public/js/' + name + '.js'), 'utf8');
const template = new JSDOM(fs.readFileSync(path.join(base, 'resources/views/web/user-screen.blade.php'), 'utf8'));
const tick = () => new Promise(resolve => setImmediate(resolve));
async function flush() { for (let i = 0; i < 8; i++) await tick(); }
function fixture(selector, url) {
    const html = template.window.document.querySelector(selector).outerHTML;
    const dom = new JSDOM('<main data-panel-page>' + html + '</main>', { url: 'http://localhost' + url, runScripts: 'outside-only' });
    const w = dom.window;
    w.HTMLDialogElement.prototype.showModal = function () { this.setAttribute('open', ''); };
    w.HTMLDialogElement.prototype.close = function () { this.removeAttribute('open'); this.dispatchEvent(new w.Event('close')); };
    w.HTMLElement.prototype.scrollIntoView = function () {};
    return dom;
}

(async () => {
    const stock = fixture('[data-user-stock-locations]', '/web/stock');
    const w = stock.window, calls = [];
    w.CCApi = { request: async url => {
        calls.push(url);
        if (url === '/api/v1/family-groups') return { data: [{ id: 7, name: 'Hogar QA' }] };
        if (url.startsWith('/web-data/')) return { data: [{ id: 1, product_id: 99, unit_id: 3, quantity: 2,
            product: { id: 99, name: 'Producto QA' }, unit: { id: 3, name: 'Unidad', code: 'u' } }],
            locations: [], summary: {}, value: {}, meta: { current_page: 1, last_page: 1 } };
        return { data: [], meta: { current_page: 1, last_page: 1 } };
    } };
    w.eval(source('panel-ui')); w.eval(source('user-stock-locations'));
    await flush();
    assert.equal(calls.length, 2, 'Stock startup makes only group + overview requests: ' + calls);
    w.document.getElementById('tab-alertas').click(); await flush();
    assert.equal(calls.length, 6, 'Four separate deferred alert loaders are retained');
    w.document.getElementById('tab-stock').click();
    w.document.getElementById('tab-alertas').click(); await flush();
    assert.equal(calls.length, 6, 'Revisiting an unchanged tab does not fetch again');
    w.document.getElementById('tab-stock').click();
    w.document.querySelector('[data-stock-edit]').click(); await flush();
    assert.ok(calls.some(url => url.includes('/products?')), 'Product catalog loads on modal open');
    assert.ok(calls.some(url => url.includes('/units?')), 'Unit catalog loads on modal open');
    assert.equal(w.document.querySelector('[data-stock-item-form]').elements.unit_id.value, '3', 'Lazy unit loading preserves the edited stock unit');
    stock.window.close();

    const profile = fixture('.workspace-profile', '/web/profile-objectives');
    const profileCalls = [];
    profile.window.CCApi = { request: async url => { profileCalls.push(url); return { data: {} }; } };
    profile.window.eval(source('panel-ui')); profile.window.eval(source('user-profile'));
    await flush();
    assert.ok(profileCalls.length > 0);
    assert.ok(!profileCalls.some(url => /allergies|consents|measurements/.test(url)), 'Hidden profile sections stay unloaded');
    profile.window.document.getElementById('tab-consentimientos').click(); await flush();
    assert.ok(profileCalls.some(url => url.includes('/consents')));
    profile.window.close();

    const shopping = fixture('[data-user-shopping-lists]', '/web/shopping-list');
    const s = shopping.window, shoppingCalls = [];
    const item = { id: 8, quantity: 2, status: 'pending', product: { id: 99, name: 'Producto QA' }, unit: { id: 3, name: 'Unidad' } };
    const list = { id: 9, source_type: 'manual', status: 'draft', meal_plan_id: 5, items: [item] };
    s.CCApi = { request: async url => {
        shoppingCalls.push(url);
        if (url === '/api/v1/family-groups') return { data: [{ id: 7, name: 'Hogar QA' }] };
        if (url.endsWith('/shopping-lists/9')) return { data: list };
        if (url.includes('/shopping-lists?')) return { data: [list], meta: { current_page: 1, last_page: 1 } };
        return { data: [] };
    } };
    s.eval(source('panel-ui')); s.eval(source('user-shopping-lists')); await flush();
    assert.equal(shoppingCalls.length, 2, 'Shopping lists do not wait for modal catalogs');
    s.document.querySelector('[data-shopping-list-edit="9"]').click(); await flush();
    assert.equal(s.document.querySelector('[data-shopping-list-form]').elements.meal_plan_id.value, '5');
    s.CCUI.close(s.document.querySelector('[data-shopping-list-form]'));
    s.document.querySelector('[data-shopping-list-show="9"]').click(); await flush();
    s.document.querySelector('[data-shopping-list-item-edit="8"]').click(); await flush();
    const itemForm = s.document.querySelector('[data-shopping-list-item-form]');
    assert.equal(itemForm.elements.product_id.value, '99', 'Lazy catalogs preserve the edited product even outside the first page');
    assert.equal(itemForm.elements.unit_id.value, '3', 'Lazy catalogs preserve the edited unit');
    shopping.window.close();

    const page = new JSDOM('<head><meta name="ccc-auth" content="session"><meta name="csrf-token" content="qa-csrf"></head><body><header id="panel-header" data-user="1" data-turbo-permanent><a href="/web/stock">Stock</a></header><main data-panel-page></main></body>',
        { url: 'http://localhost/web/stock', runScripts: 'outside-only' });
    const p = page.window;
    p.Turbo = { session: {}, config: { drive: {} } };
    p.eval(source('panel-navigation'));
    p.eval(source('api-client'));
    p.eval(source('panel-menu'));
    const menu = p.document.createElement('div');
    menu.className = 'site-navbar';
    menu.innerHTML = '<div class="dropdown"><a role="button" href="#" data-toggle="dropdown" aria-expanded="false">Menu</a><div class="dropdown-menu"><a href="/web/stock">Stock</a></div></div>';
    p.document.body.appendChild(menu);
    const toggle = menu.querySelector('[data-toggle]');
    toggle.click(); assert.equal(toggle.getAttribute('aria-expanded'), 'true');
    toggle.dispatchEvent(new p.KeyboardEvent('keydown', { key: 'ArrowDown', bubbles: true }));
    assert.equal(p.document.activeElement.textContent, 'Stock');
    p.document.activeElement.dispatchEvent(new p.KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
    assert.equal(toggle.getAttribute('aria-expanded'), 'false');
    assert.equal(p.document.activeElement, toggle);
    menu.remove();
    let mounts = 0, cleans = 0, clicks = 0;
    p.CCPage.register('test', () => {
        mounts++;
        p.CCPage.onDispose(() => cleans++);
        p.CCPage.listen(p.document, 'qa-click', () => clicks++);
    });
    await flush();
    p.document.dispatchEvent(new p.Event('turbo:load'));
    assert.equal(mounts, 1, 'DOMContentLoaded and turbo:load do not double mount');
    assert.equal(p.Turbo.session.drive, false);
    assert.equal(p.document.querySelector('a').dataset.turbo, 'true');
    let config, resolveRead;
    p.fetch = (url, options) => { config = options; return new Promise(resolve => { resolveRead = resolve; }); };
    let staleRendered = false;
    p.CCApi.request('/api/v1/example').then(() => { staleRendered = true; });
    assert.equal(config.headers['X-CSRF-TOKEN'], 'qa-csrf');
    assert.equal(config.headers.Authorization, undefined);
    const body = p.document.createElement('body');
    body.innerHTML = '<header id="panel-header" data-user="1" data-turbo-permanent><a href="/web/stock" class="active">Stock</a></header><main data-panel-page></main>';
    p.document.dispatchEvent(new p.CustomEvent('turbo:before-render', { detail: { newBody: body }, cancelable: true }));
    assert.equal(config.signal.aborted, true);
    assert.equal(cleans, 1);
    assert.ok(p.document.querySelector('header').hasAttribute('data-turbo-permanent'));
    assert.ok(p.document.querySelector('a').classList.contains('active'));
    resolveRead({ status: 200, ok: true, text: async () => '{"data":[]}' });
    await flush();
    assert.equal(staleRendered, false, 'Old request cannot render into a replacement page');
    p.document.body.replaceWith(body);
    p.document.dispatchEvent(new p.Event('turbo:load'));
    p.document.dispatchEvent(new p.Event('turbo:load'));
    p.document.dispatchEvent(new p.Event('qa-click'));
    assert.equal(mounts, 2);
    assert.equal(clicks, 1, 'Old delegated listeners were removed');
    let resolveWrite, resumed = false;
    p.fetch = (url, options) => { config = options; return new Promise(resolve => { resolveWrite = resolve; }); };
    const saving = p.CCApi.request('/api/v1/example', { method: 'PATCH', body: { quantity: 2 } });
    const next = p.document.createElement('body');
    next.innerHTML = '<header id="panel-header" data-user="2" data-turbo-permanent><a href="/web/stock">Other user</a></header><main data-panel-page></main>';
    const event = new p.CustomEvent('turbo:before-render', { detail: { newBody: next, resume: () => { resumed = true; } }, cancelable: true });
    p.document.dispatchEvent(event);
    assert.ok(event.defaultPrevented);
    assert.equal(config.signal.aborted, false, 'Never abort a pending write');
    assert.equal(resumed, false);
    resolveWrite({ status: 204, ok: true });
    await saving;
    await new Promise(resolve => setTimeout(resolve, 10)); await flush();
    assert.equal(resumed, true);
    assert.equal(p.document.querySelector('header').hasAttribute('data-turbo-permanent'), false, 'Identity changes replace the navbar');
    page.window.close(); template.window.close();
    console.log('PASS: lazy tabs/catalogs, CSRF session headers, lifecycle, stale reads, pending writes and navbar identity');
})().catch(error => { console.error(error); process.exitCode = 1; });

const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { JSDOM } = require(require.resolve('jsdom', { paths: [path.join(__dirname, '../../mobile')] }));
const source = fs.readFileSync(path.join(__dirname, '../../public/js/shopping-alternatives.js'), 'utf8');
const settle = async () => { for (let i = 0; i < 8; i++) await new Promise(resolve => setImmediate(resolve)); };
const clone = value => JSON.parse(JSON.stringify(value));
const row = () => ({
    item_id: 20, product: { id: 112, name: 'Arroz 1 kg' }, ingredient: { id: 4, name: 'Arroz' },
    quantity: '1.0000', unit: { id: 8, code: 'package', symbol: 'paq' }, can_select: true,
    alternatives: [{
        product: { id: 113, name: 'Arroz 400 g', net_quantity: '400.0000', package_unit: { id: 1, code: 'g', symbol: 'g' } },
        supermarket_product_id: 501, price: '800.00', unit_price: '2.0000',
        purchase_quantity: 3, purchase_unit_id: 8, purchase_unit: { id: 8, code: 'package', symbol: 'paq' },
        estimated_subtotal: 2400, reason: 'equivalent'
    }]
});
const selected = () => ({
    id: 20, product: { id: 113, name: 'Arroz 400 g' }, quantity: '3.0000',
    unit: { id: 8, code: 'package', symbol: 'paq' }, estimated_price: '800.00',
    estimated_subtotal: 2400, actual_price: null, status: 'pending'
});
function fixture(options = {}) {
    const dom = new JSDOM('<body><div id="alternatives"></div></body>', { url: 'http://localhost/web/shopping-list', runScripts: 'outside-only' });
    const w = dom.window, calls = [], callbacks = [], cleanups = [];
    const container = w.document.getElementById('alternatives');
    let rows = options.rows || [row()], nextError = options.error, pending = options.pending;
    w.CCPage = { onDispose(fn) { cleanups.push(fn); } };
    w.alert = () => { throw new Error('Blocking alert must not handle selection errors'); };
    w.CCApi = { request(url, config = {}) {
        calls.push({ url, config });
        if (config.method === 'POST') {
            if (pending) return new Promise(resolve => { options.resolveSelection = resolve; });
            if (nextError) return Promise.reject(nextError);
            return Promise.resolve({ data: selected() });
        }
        if (options.loadError) { options.loadError = false; return Promise.reject({ message: 'No disponible' }); }
        return Promise.resolve({ data: clone(rows) });
    } };
    w.eval(source);
    const mount = () => w.ShoppingAlternatives.mount(container, 8, 15, {
        onSelected(item) { callbacks.push(clone(item)); }
    });
    mount();
    return { dom, w, container, calls, callbacks, cleanups, mount, options,
        async open() { container.querySelector('[data-alt-load]').click(); await settle(); container.querySelector('[data-alt-toggle]').click(); },
        select() { container.querySelector('[data-alt-select-item]').click(); },
        text() { return container.textContent; },
        close() { dom.window.close(); }
    };
}

test('actual API shape displays package quantity, price and total; selection uses offer ID and authoritative callback', async () => {
    const f = fixture({ pending: true });
    try {
        await f.open();
        assert.match(f.text(), /Arroz 1 kg/);
        assert.match(f.text(), /3\s+paq/);
        assert.match(f.text(), /800[.,]00/);
        assert.match(f.text(), /2400[.,]00/);
        assert.match(f.text(), /400\s+g/);
        f.select(); f.select();
        const posts = f.calls.filter(call => call.config.method === 'POST');
        assert.equal(posts.length, 1, 'double click must not submit two selections');
        assert.deepEqual(clone(posts[0].config.body), { supermarket_product_id: 501 });
        f.options.resolveSelection({ data: selected() }); await settle();
        assert.deepEqual(f.callbacks, [selected()]);
    } finally { f.close(); }
});

test('422 selection error is inline and escaped, keeps alternatives and does not call parent', async () => {
    const f = fixture({ error: { status: 422, payload: { error: { message: 'Presentación inválida <img src=x>' } } } });
    try {
        await f.open(); f.select(); await settle();
        assert.match(f.container.querySelector('[role="alert"]').textContent, /Presentación inválida <img src=x>/);
        assert.equal(f.container.querySelector('img'), null);
        assert.equal(f.callbacks.length, 0);
        assert.equal(f.container.querySelector('[data-alt-select-item]').disabled, false);
    } finally { f.close(); }
});

test('finalized rows and missing offer IDs cannot be selected', async () => {
    const closed = row(); closed.can_select = false;
    const f = fixture({ rows: [closed] });
    try { await f.open(); assert.equal(f.container.querySelector('[data-alt-select-item]'), null); } finally { f.close(); }
    const missing = row(); delete missing.alternatives[0].supermarket_product_id;
    const g = fixture({ rows: [missing] });
    try { await g.open(); assert.equal(g.container.querySelector('[data-alt-select-item]'), null); } finally { g.close(); }
});

test('failed GET can be retried without a blocking alert', async () => {
    const f = fixture({ loadError: true });
    try {
        f.container.querySelector('[data-alt-load]').click(); await settle();
        assert.match(f.text(), /No disponible/);
        f.container.querySelector('[data-alt-load]').click(); await settle();
        assert.ok(f.container.querySelector('[data-alt-toggle]'));
    } finally { f.close(); }
});

test('remount removes old listener and disposed selection cannot refresh parent', async () => {
    const f = fixture({ pending: true });
    try {
        f.mount(); await f.open(); f.select();
        assert.equal(f.calls.filter(call => call.config.method === 'POST').length, 1);
        f.cleanups.forEach(fn => fn());
        f.options.resolveSelection({ data: selected() }); await settle();
        assert.equal(f.callbacks.length, 0);
    } finally { f.close(); }
});

test('real shopping-list parent reloads its authoritative item and total after alternative selection', async () => {
    const template = fs.readFileSync(path.join(__dirname, '../../resources/views/web/user-screen.blade.php'), 'utf8');
    const parsed = new JSDOM(template);
    const workspace = parsed.window.document.querySelector('[data-user-shopping-lists]').outerHTML;
    parsed.window.close();
    const dom = new JSDOM('<body>' + workspace + '</body>', { url: 'http://localhost/web/shopping-list', runScripts: 'outside-only' });
    const w = dom.window, cleanups = []; let mount, changed = false;
    const list = () => ({ id: 15, source_type: 'meal_plan', status: 'active', estimated_total: changed ? '2400.00' : '1800.00',
        items: [changed ? selected() : { id: 20, product: {id:112,name:'Arroz 1 kg'}, quantity:'1.0000', unit:{id:8,code:'package',symbol:'paq'}, estimated_price:'1800.00', status:'pending' }] });
    w.HTMLElement.prototype.scrollIntoView = function () {};
    w.CCPage = { register(name, fn) { mount = fn; }, onDispose(fn) { cleanups.push(fn); } };
    w.CCApi = { request(url, config = {}) {
        const pathname = new URL(url, 'http://localhost').pathname;
        if (pathname.endsWith('/select-alternative')) {
            assert.deepEqual(clone(config.body), { supermarket_product_id: 501 });
            changed = true; return Promise.resolve({data:selected()});
        }
        if (pathname.endsWith('/alternatives')) return Promise.resolve({data:[row()]});
        if (pathname === '/api/v1/family-groups') return Promise.resolve({data:[{id:8,name:'Hogar QA'}]});
        if (pathname.endsWith('/shopping-lists/15')) return Promise.resolve({data:list()});
        if (pathname.endsWith('/shopping-lists')) return Promise.resolve({data:[list()],meta:{total:1,current_page:1,last_page:1}});
        return Promise.resolve({data:[]});
    } };
    try {
        w.eval(source);
        w.eval(fs.readFileSync(path.join(__dirname, '../../public/js/user-shopping-lists.js'), 'utf8')); mount(); await settle();
        w.document.querySelector('[data-shopping-list-show="15"]').click(); await settle();
        w.document.querySelector('[data-alt-load]').click(); await settle();
        w.document.querySelector('[data-alt-toggle]').click();
        w.document.querySelector('[data-alt-select-item]').click(); await settle();
        const detail = w.document.querySelector('[data-shopping-list-detail]').textContent;
        assert.match(detail, /Arroz 400 g/);
        assert.match(detail, /3\.0000/);
        assert.match(w.document.querySelector('[data-shopping-list-body]').textContent, /2400\.00/);
    } finally { dom.window.close(); }
});

test('empty alternatives show a usable empty state without any selection request', async () => {
    const item = row(); item.alternatives = [];
    const f = fixture({ rows: [item] });
    try {
        f.container.querySelector('[data-alt-load]').click(); await settle();
        assert.match(f.text(), /No hay alternativas disponibles/);
        assert.equal(f.container.querySelector('[data-alt-select-item]'), null);
        assert.equal(f.calls.filter(call => call.config.method === 'POST').length, 0);
        assert.equal(f.container.querySelector('[data-alt-load]').disabled, false);
    } finally { f.close(); }
});

test('selection stays blocked during parent reload and distinguishes reload error from selection failure', async () => {
    const f = fixture();
    let rejectRefresh;
    try {
        f.w.ShoppingAlternatives.mount(f.container, 8, 15, { onSelected() {
            return new Promise((resolve, reject) => { rejectRefresh = reject; });
        } });
        await f.open(); f.select(); await settle();
        assert.equal(f.container.querySelector('[data-alt-load]').disabled, true);
        assert.match(f.text(), /Alternativa seleccionada/);
        rejectRefresh(new Error('Detalle no disponible')); await settle();
        assert.match(f.container.querySelector('[role="alert"]').textContent, /se seleccionó, pero no pudimos actualizar/);
        assert.equal(f.container.querySelector('[data-alt-load]').disabled, false);
        assert.equal(f.calls.filter(call => call.config.method === 'POST').length, 1);
    } finally { f.close(); }
});


for (const mode of ['reload-error', 'household-change']) {
    test('real parent guards selection refresh: ' + mode, async () => {
        const template = fs.readFileSync(path.join(__dirname, '../../resources/views/web/user-screen.blade.php'), 'utf8');
        const parsed = new JSDOM(template);
        const workspace = parsed.window.document.querySelector('[data-user-shopping-lists]').outerHTML;
        parsed.window.close();
        const dom = new JSDOM('<body>' + workspace + '</body>', { url: 'http://localhost/web/shopping-list', runScripts: 'outside-only' });
        const w = dom.window; let mount, changed = false, releaseDetail, rejectDetail;
        const list = () => ({ id: 15, source_type: 'manual', status: 'active', estimated_total: changed ? '2400.00' : '1800.00',
            items: [changed ? selected() : {id:20,product:{id:112,name:'Arroz 1 kg'},quantity:'1.0000',unit:{id:8,code:'package',symbol:'paq'},estimated_price:'1800.00',status:'pending'}] });
        w.HTMLElement.prototype.scrollIntoView = function () {};
        w.CCPage = { register(name, fn) { mount = fn; }, onDispose() {} };
        w.CCApi = { request(url, config = {}) {
            const pathname = new URL(url, 'http://localhost').pathname;
            if (pathname.endsWith('/select-alternative')) { changed = true; return Promise.resolve({data:selected()}); }
            if (pathname.endsWith('/alternatives')) return Promise.resolve({data:[row()]});
            if (pathname === '/api/v1/family-groups') return Promise.resolve({data:[{id:8,name:'Hogar QA'},{id:9,name:'Otro hogar'}]});
            if (pathname.endsWith('/shopping-lists/15')) {
                if (changed) return new Promise((resolve, reject) => {
                    releaseDetail = () => resolve({data:list()}); rejectDetail = reject;
                });
                return Promise.resolve({data:list()});
            }
            if (pathname.includes('/family-groups/9/') && pathname.endsWith('/shopping-lists')) {
                return Promise.resolve({data:[],meta:{total:0,current_page:1,last_page:1}});
            }
            if (pathname.endsWith('/shopping-lists')) return Promise.resolve({data:[list()],meta:{total:1,current_page:1,last_page:1}});
            return Promise.resolve({data:[]});
        } };
        try {
            w.eval(source);
            w.eval(fs.readFileSync(path.join(__dirname, '../../public/js/user-shopping-lists.js'), 'utf8')); mount(); await settle();
            w.document.querySelector('[data-shopping-list-show="15"]').click(); await settle();
            w.document.querySelector('[data-alt-load]').click(); await settle();
            w.document.querySelector('[data-alt-toggle]').click();
            w.document.querySelector('[data-alt-select-item]').click(); await settle();
            assert.ok(releaseDetail, 'authoritative detail request is pending');
            if (mode === 'reload-error') {
                rejectDetail(new Error('Detalle no disponible')); await settle();
                assert.match(w.document.querySelector('[data-alt-panel] [role="alert"]').textContent, /se seleccionó, pero no pudimos actualizar/);
                assert.match(w.document.querySelector('[data-shopping-list-detail]').textContent, /Arroz 1 kg/);
            } else {
                const group = w.document.querySelector('[data-shopping-list-group]');
                group.value = '9'; group.dispatchEvent(new w.Event('change', {bubbles:true})); await settle();
                releaseDetail(); await settle();
                assert.match(w.document.querySelector('[data-shopping-list-detail]').textContent, /Selecciona una lista/);
                assert.doesNotMatch(w.document.querySelector('[data-shopping-list-body]').textContent, /Arroz|2400|1800/);
                assert.equal(group.value, '9');
            }
        } finally { dom.window.close(); }
    });
}

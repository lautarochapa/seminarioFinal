const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { JSDOM } = require('jsdom');
const base = path.join(__dirname, '../..');
const read = name => fs.readFileSync(path.join(base, name), 'utf8');
const template = new JSDOM(read('resources/views/web/admin-screen.blade.php'));
const flush = async () => { for (let i = 0; i < 5; i++) await new Promise(resolve => setImmediate(resolve)); };
const entity = {
    id: 7, name: 'Dato QA', code: 'dato_qa', status: 'active', type: 'mass', symbol: 'g',
    normalized_name: 'dato qa', description: 'Descripción QA', color: '#123456',
    category_id: 7, base_unit_id: 7, default_unit_id: 7, unit_id: 7, nutrient_id: 7,
    ingredient_id: 7, from_ingredient_id: 7, to_ingredient_id: 7, from_unit_id: 7, to_unit_id: 7,
    factor: 1, quantity: 1, amount_per_100g: 5, sort_order: 1, net_quantity: 1000,
    external_sku: 'qa-sku', product_id: 7, supermarket_chain_id: 7, supermarket_branch_id: 7,
    city_id: 7, address: 'Dirección de ejemplo', country: 'Argentina', province: 'Río Negro',
    children: [], tags: [], images: [], product: { id: 7, name: 'Producto QA' },
    chain: { id: 7, name: 'Cadena QA' }, city: { id: 7, name: 'Ciudad QA' },
    branch: { id: 7, name: 'Sucursal QA', chain: { id: 7, name: 'Cadena QA' } },
};
const configs = [
    ['brands', 'brand'], ['food-tags', 'food-tag'], ['ingredient-categories', 'ingredient-category'],
    ['ingredient-equivalences', 'equivalence'], ['ingredients', 'ingredient'], ['meal-types', 'meal-type'],
    ['nutrients', 'nutrient'], ['product-categories', 'product-category'], ['recipe-categories', 'recipe-category'],
    ['recipe-tags', 'recipe-tag'], ['units', 'unit'], ['units', 'conversion'], ['cities', 'cities'], ['branches', 'branches'],
    ['supermarkets', 'supermarkets'],
    ['products', 'product'], ['supermarket-products', 'sp'],
];
async function fixture(name, overrides = {}) {
    const source = template.window.document.querySelector('[data-admin-' + name + ']');
    assert.ok(source, 'Real screen markup exists: ' + name);
    const dom = new JSDOM('<button data-screen-primary-action>Nuevo</button><main data-admin-ui data-admin-screen="' + name + '">' + source.outerHTML + '</main>', {
        url: 'https://qa.invalid/admin-web/' + name, runScripts: 'outside-only',
    });
    const w = dom.window, errors = [], requests = [];
    const row = { ...entity, ...overrides };
    w.addEventListener('error', e => { errors.push(e.error || e.message); e.preventDefault(); });
    w.HTMLDialogElement.prototype.showModal = function () { this.open = true; };
    w.HTMLDialogElement.prototype.close = function () { this.open = false; this.dispatchEvent(new w.Event('close')); };
    w.HTMLElement.prototype.scrollIntoView = function () {};
    w.fetch = () => { throw new Error('Network access is forbidden in this test'); };
    w.XMLHttpRequest.prototype.open = () => { throw new Error('Network access is forbidden in this test'); };
    w.confirm = () => true;
    const control = { rejectWrite: false };
    w.CCApi = { request: async (url, options = {}) => {
        requests.push({ url, ...options });
        if (options.method && options.method !== 'GET') {
            if (control.rejectWrite) throw { status: 422, message: 'Validación QA', payload: { error: { message: 'Validación QA' } } };
            return { data: { ...row, barcode: '7790000000010' } };
        }
        return { data: /\/7(?:\?|$)/.test(url) ? row : [row], meta: { current_page: 1, last_page: 1, total: 1 } };
    } };
    await new Promise(resolve => w.document.addEventListener('DOMContentLoaded', resolve, { once: true }));
    w.eval(read('public/js/admin-labels.js'));
    w.eval(read('public/js/panel-ui.js'));
    w.eval(read('public/js/admin-panel-ui.js'));
    w.eval(read('public/js/admin-ingredient-picker.js'));
    w.eval(read('public/js/admin-' + name + '.js'));
    w.document.dispatchEvent(new w.Event('DOMContentLoaded'));
    await flush();
    assert.deepEqual(errors, [], 'No initialization exceptions');
    return { dom, w, d: w.document, errors, requests, control };
}
for (const [name, prefix] of configs) {
    test(name + '/' + prefix + ': real editor opens, retains invalid changes, closes only after persistence and starts a clean new form', async () => {
        const f = await fixture(name);
        try {
            const form = f.d.querySelector('[data-' + prefix + '-form]');
            const dialog = form.closest('dialog');
            assert.ok(dialog, 'Original form moved into accessible dialog');
            const edit = f.d.querySelector('[data-' + prefix + '-edit="7"]');
            assert.ok(edit, 'List offers existing QA row');
            edit.click(); await flush();
            assert.equal(dialog.open, true, 'Edit reveals modal');
            assert.equal(f.d.querySelector('[data-' + prefix + '-form]'), form, 'Original form node preserved');
            if (form.elements.status) assert.equal(form.elements.status.value, 'active', 'Internal status value is not translated');
            if (form.elements.name) form.elements.name.value = 'Edición QA';
            f.control.rejectWrite = true;
            form.dispatchEvent(new f.w.Event('submit', { bubbles: true, cancelable: true })); await flush();
            assert.equal(dialog.open, true, '422 keeps editor visible');
            if (form.elements.name) assert.equal(form.elements.name.value, 'Edición QA');
            assert.match(dialog.textContent, /Validación QA|Datos inválidos/);
            f.control.rejectWrite = false;
            form.dispatchEvent(new f.w.Event('submit', { bubbles: true, cancelable: true })); await flush();
            assert.equal(dialog.open, false, 'Confirmed save closes only this modal');
            assert.equal(f.requests.filter(r => r.method && r.method !== 'GET').length, 2, 'One write per submit');
            if (form.elements.status) {
                for (const write of f.requests.filter(r => r.method && r.method !== 'GET')) {
                    assert.equal(write.body.status, 'active', 'API payload keeps the status code');
                }
            }
            const trigger = f.d.querySelector('button[aria-controls="' + dialog.id + '"]');
            trigger.click(); await flush();
            assert.equal(dialog.open, true, 'New trigger opens modal');
            if (form.elements.id) assert.equal(form.elements.id.value, '', 'New clears previous entity ID');
            if (form.elements.name) assert.equal(form.elements.name.value, '', 'New clears previous name');
            const before = f.requests.length;
            [...dialog.querySelectorAll('button')].find(b => b.textContent.trim() === 'Cancelar').click();
            assert.equal(dialog.open, false, 'Cancel closes without submitting');
            assert.equal(f.requests.length, before, 'Cancel does not mutate or reload');
            assert.deepEqual(f.errors, []);
        } finally { f.dom.window.close(); }
    });
}
test('barcodes: create modal keeps rejected values, closes on success and primary new clears input', async () => {
    const f = await fixture('barcodes');
    try {
        const form = f.d.querySelector('[data-barcode-create-form]'), dialog = form.closest('dialog');
        f.d.querySelector('[data-screen-primary-action]').click();
        form.elements.product_id.value = '7'; form.elements.barcode.value = '7790000000010';
        f.control.rejectWrite = true;
        form.dispatchEvent(new f.w.Event('submit', { bubbles: true, cancelable: true })); await flush();
        assert.equal(dialog.open, true); assert.equal(form.elements.barcode.value, '7790000000010');
        f.control.rejectWrite = false;
        form.dispatchEvent(new f.w.Event('submit', { bubbles: true, cancelable: true })); await flush();
        assert.equal(dialog.open, false);
        form.elements.barcode.value = 'old';
        f.d.querySelector('[data-screen-primary-action]').click();
        assert.equal(dialog.open, true); assert.equal(form.elements.barcode.value, '');
        assert.equal(f.requests.filter(r => r.method === 'POST').length, 2);
        assert.deepEqual(f.errors, []);
    } finally { f.dom.window.close(); }
});
test('nutrient relation: edit, cancel, new sends POST instead of reusing the previous PATCH target', async () => {
    const f = await fixture('nutrients');
    try {
        const ingredient = f.d.querySelector('[data-nutrient-ingredient-select]');
        ingredient.value = '7'; ingredient.dispatchEvent(new f.w.Event('change')); await flush();
        f.d.querySelector('[data-ingredient-nutrient-edit="7"]').click();
        const form = f.d.querySelector('[data-ingredient-nutrient-form]'), dialog = form.closest('dialog');
        assert.equal(dialog.open, true); assert.equal(form.dataset.editNutrientId, '7');
        [...dialog.querySelectorAll('button')].find(b => b.textContent.trim() === 'Cancelar').click();
        f.d.querySelector('button[aria-controls="' + dialog.id + '"]').click();
        assert.equal(form.dataset.editNutrientId, '');
        form.elements.nutrient_id.value = '7'; form.elements.amount_per_100g.value = '12.5';
        form.dispatchEvent(new f.w.Event('submit', { bubbles: true, cancelable: true })); await flush();
        const writes = f.requests.filter(r => r.method);
        assert.equal(writes.length, 1); assert.equal(writes[0].method, 'POST');
        assert.equal(writes[0].url, '/api/v1/admin/ingredients/7/nutrients');
        assert.equal(writes[0].body.nutrient_id, 7);
        assert.equal(dialog.open, false); assert.deepEqual(f.errors, []);
    } finally { f.dom.window.close(); }
});
test('mapping prices: row action reveals its tab and successful price write closes its form', async () => {
    const f = await fixture('supermarket-products');
    try {
        f.d.querySelector('[data-sp-prices-row="7"]').click(); await flush();
        const panel = f.d.querySelector('[data-sp-price-history]').closest('[role="tabpanel"]');
        assert.equal(panel.hidden, false);
        const form = f.d.querySelector('[data-sp-price-form]'), dialog = form.closest('dialog');
        f.d.querySelector('button[aria-controls="' + dialog.id + '"]').click();
        form.elements.price.value = '1800.01';
        form.dispatchEvent(new f.w.Event('submit', { bubbles: true, cancelable: true })); await flush();
        assert.equal(dialog.open, false);
        const writes = f.requests.filter(r => r.method);
        assert.equal(writes.length, 1); assert.equal(writes[0].url, '/api/v1/admin/supermarket-products/7/prices');
        assert.equal(writes[0].body.price, '1800.01');
        assert.deepEqual(f.errors, []);
    } finally { f.dom.window.close(); }
});
test('branch map stays with original form and initializes after the dialog becomes visible', async () => {
    const f = await fixture('branches', { latitude: -41, longitude: -71 });
    try {
        let mapCalls = 0, resizeCalls = 0;
        const map = { setView() { return this; }, invalidateSize() { resizeCalls++; } };
        const marker = { addTo() { return this; }, setLatLng() {} };
        f.w.L = {
            map(el) {
                mapCalls++;
                assert.equal(el.closest('dialog').open, true, 'Map is in the open editor');
                return map;
            },
            tileLayer() { return { addTo() {} }; }, marker() { return marker; },
        };
        f.d.querySelector('[data-branches-edit="7"]').click(); await flush();
        assert.equal(mapCalls, 1); assert.equal(resizeCalls, 1); assert.deepEqual(f.errors, []);
    } finally { f.dom.window.close(); }
});
for (const [name, prefix] of [['products', 'product'], ['ingredients', 'ingredient']]) {
    test(name + ': row detail action reveals the destination tab', async () => {
        const f = await fixture(name);
        try {
            const detail = f.d.querySelector('[data-' + prefix + '-detail]');
            const panel = detail.closest('[role="tabpanel"]');
            assert.equal(panel.hidden, true);
            f.d.querySelector('[data-' + prefix + '-view="7"]').click(); await flush();
            assert.equal(panel.hidden, false);
            assert.match(detail.textContent, /Dato QA/);
            assert.deepEqual(f.errors, []);
        } finally { f.dom.window.close(); }
    });
}

for (const [name, body] of [
    ['products', 'products'], ['product-categories', 'product-categories'], ['brands', 'brands'],
    ['ingredients', 'ingredients'], ['ingredient-categories', 'ingredient-categories'],
    ['ingredient-equivalences', 'equivalences'], ['health-preferences', 'health'],
    ['food-tags', 'food-tags'], ['meal-types', 'meal-types'], ['supermarket-products', 'sp'],
    ['nutrients', 'nutrients'], ['units', 'units'], ['objectives', 'objectives'],
]) {
    test(name + ': status is Spanish while arbitrary catalog content remains unchanged and escaped', async () => {
        const f = await fixture(name, { name: 'active <dato>', code: 'active', description: 'Manual user text', reason: 'Manual user text' });
        try {
            const row = f.d.querySelector('[data-' + body + '-body] tr');
            assert.ok(row, 'Catalog row is rendered');
            assert.ok([...row.cells].some(cell => cell.textContent.trim() === 'Activo'), 'Status cell uses Spanish label');
            assert.equal(row.querySelector('dato'), null, 'Catalog data is escaped');
            if (name !== 'ingredient-equivalences' && name !== 'supermarket-products') assert.match(row.textContent, /active/);
            assert.deepEqual(f.errors, []);
        } finally { f.dom.window.close(); }
    });
}

test('products: inactive remains its original filter and editor value', async () => {
    const f = await fixture('products', { status: 'inactive' });
    try {
        assert.match(f.d.querySelector('[data-products-body]').textContent, /Inactivo/);
        const filter = f.d.querySelector('[data-products-status]');
        filter.value = 'inactive';
        f.d.querySelector('[data-products-refresh]').click(); await flush();
        assert.ok(f.requests.some(request => request.url.includes('status=inactive')));
        f.d.querySelector('[data-product-edit="7"]').click(); await flush();
        const form = f.d.querySelector('[data-product-form]');
        assert.equal(form.elements.status.value, 'inactive');
        form.dispatchEvent(new f.w.Event('submit', { bubbles: true, cancelable: true })); await flush();
        assert.equal(f.requests.find(request => request.method === 'PATCH').body.status, 'inactive');
    } finally { f.dom.window.close(); }
});

for (const [screen, prefix, type, translated] of [
    ['units', 'unit', 'mass', 'Masa'], ['food-tags', 'food-tag', 'INGREDIENT_FORM', 'Forma del ingrediente'],
]) {
    test(screen + ': type label is translated only when rendering', async () => {
        const f = await fixture(screen, { type });
        try {
            assert.match(f.d.querySelector('[data-' + screen + '-body]').textContent, new RegExp(translated));
            f.d.querySelector('[data-' + prefix + '-edit="7"]').click(); await flush();
            const form = f.d.querySelector('[data-' + prefix + '-form]');
            assert.equal(form.elements.type.value, type);
            form.dispatchEvent(new f.w.Event('submit', { bubbles: true, cancelable: true })); await flush();
            assert.equal(f.requests.find(request => request.method === 'PATCH').body.type, type);
        } finally { f.dom.window.close(); }
    });
}

test('product requests: Spanish status and origin preserve approval endpoint and payload', async () => {
    const f = await fixture('product-requests', { status: 'pending', source: 'user_request' });
    try {
        assert.match(f.d.querySelector('[data-product-requests-body]').textContent, /Pendiente/);
        f.d.querySelector('[data-product-request-review="7"]').click(); await flush();
        assert.match(f.d.querySelector('[data-product-request-detail]').textContent, /Solicitud del usuario/);
        const form = f.d.querySelector('[data-product-request-approve-form]');
        form.dispatchEvent(new f.w.Event('submit', { bubbles: true, cancelable: true })); await flush();
        const write = f.requests.find(request => request.method === 'POST');
        assert.equal(write.url, '/api/v1/product-requests/7/approve');
        assert.equal(write.body.status, 'active');
        assert.equal(write.body.name, 'Dato QA');
    } finally { f.dom.window.close(); }
});

test('mapping prices: source and status labels do not alter amounts or currency', async () => {
    const f = await fixture('supermarket-products', { source: 'scraper', is_current: true, price: '1800.01', currency: 'ARS' });
    try {
        f.d.querySelector('[data-sp-prices-row="7"]').click(); await flush();
        for (const selector of ['[data-sp-current-price]', '[data-sp-price-history]']) {
            const text = f.d.querySelector(selector).textContent;
            assert.match(text, /Importación web/);
            assert.match(text, /ARS 1800\.01/);
            assert.doesNotMatch(text, /scraper/);
        }
        assert.match(f.d.querySelector('[data-sp-price-history]').textContent, /Activo/);
    } finally { f.dom.window.close(); }
});

test('free text equivalence types and unknown food types are preserved and escaped', async () => {
    for (const screen of ['ingredient-equivalences', 'food-tags']) {
        const f = await fixture(screen, { type: 'custom <type>', equivalence_type: 'custom <type>' });
        try {
            const body = f.d.querySelector('[data-' + (screen === 'food-tags' ? screen : 'equivalences') + '-body]');
            assert.match(body.textContent, /custom <type>/);
            assert.equal(body.querySelector('type'), null);
        } finally { f.dom.window.close(); }
    }
});

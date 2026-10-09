const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { JSDOM } = require('jsdom');

const base = path.join(__dirname, '../..');
const read = name => fs.readFileSync(path.join(base, name), 'utf8');
const template = new JSDOM(read('resources/views/web/admin-screen.blade.php'));
const source = template.window.document.querySelector('[data-admin-product-categories]').outerHTML;
template.window.close();
const flush = async () => { for (let i = 0; i < 5; i++) await new Promise(resolve => setImmediate(resolve)); };
const node = (id, name, children = []) => ({ id, name, description: '', children });
const row = (id, name, parent_id = null, extra = {}) => ({ id, name, parent_id, status: 'active', description: '', children_count: 0, products_count: 0, ...extra });
function catalog() {
    const children = Array.from({ length: 55 }, (_, i) => node(i + 10, 'Rubro ' + i));
    return {
        tree: [
            node(1, 'Alimentos', children),
            node(200, 'Bebidas', [node(201, 'Sin azúcar', [node(202, 'Especial')])]),
            node(300, 'Lácteos', [node(301, 'Sin azúcar')]),
            node(400, 'Panadería & afines', [node(401, 'Integral', [node(402, 'Pan de molde')])]),
        ],
        list: children.slice(0, 47).map(item => row(item.id, item.name, 1)).concat([
            row(400, 'Panadería & afines'), row(202, 'Especial', 201), row(402, 'Pan de molde', 401),
        ]),
    };
}
async function fixture({ deferTree = false } = {}) {
    const dom = new JSDOM('<button data-screen-primary-action>Nuevo</button><main data-admin-ui data-admin-screen="product-categories">' + source + '</main>', {
        url: 'https://qa.invalid/admin-web/product-categories', runScripts: 'outside-only',
    });
    const w = dom.window, d = w.document, requests = [], errors = [];
    const control = { ...catalog(), rejectWrite: false, deferTree, resolveTree: null };
    w.addEventListener('error', event => { errors.push(event.error || event.message); event.preventDefault(); });
    w.HTMLDialogElement.prototype.showModal = function () { this.open = true; };
    w.HTMLDialogElement.prototype.close = function () { this.open = false; this.dispatchEvent(new w.Event('close')); };
    w.HTMLElement.prototype.scrollIntoView = function () {};
    w.fetch = () => { throw new Error('Network is forbidden'); };
    w.XMLHttpRequest.prototype.open = () => { throw new Error('Network is forbidden'); };
    w.confirm = () => true;
    w.CCApi = { request: async (url, options = {}) => {
        requests.push({ url, ...options });
        if (options.method) {
            if (control.rejectWrite) throw { message: 'Validación de prueba', payload: { error: { message: 'Validación de prueba' } } };
            if (url.endsWith('/restore')) control.tree.push(node(900, 'Restaurada'));
            return { data: {} };
        }
        if (url === '/api/v1/product-categories') {
            if (control.deferTree) return new Promise(resolve => { control.resolveTree = () => { control.deferTree = false; resolve({ data: control.tree }); }; });
            return { data: control.tree };
        }
        assert.match(url, /^\/api\/v1\/admin\/product-categories\?/);
        return { data: control.list, meta: { total: 64, current_page: 1, last_page: 2 } };
    } };
    await new Promise(resolve => d.addEventListener('DOMContentLoaded', resolve, { once: true }));
    for (const script of ['admin-labels', 'panel-ui', 'admin-panel-ui', 'admin-product-categories']) w.eval(read('public/js/' + script + '.js'));
    d.dispatchEvent(new w.Event('DOMContentLoaded'));
    await flush();
    const form = d.querySelector('[data-product-category-form]');
    assert.deepEqual(errors, []);
    return {
        dom, w, d, requests, errors, control, form, select: form.elements.parent_id, dialog: form.closest('dialog'),
        edit(id) { d.querySelector('[data-product-category-edit="' + id + '"]').click(); },
        submit() { form.dispatchEvent(new w.Event('submit', { bubbles: true, cancelable: true })); },
        writes() { return requests.filter(request => request.method); },
    };
}

test('full active tree supplies more than 50 parents and disambiguates identical names outside the list page', async () => {
    const f = await fixture();
    try {
        assert.equal(f.control.list.length, 50);
        assert.equal(f.select.options.length, 65, '64 tree nodes plus the root choice; not the paginated list');
        const options = [...f.select.options];
        assert.equal(options.find(option => option.value === '201').textContent, 'Bebidas > Sin azúcar');
        assert.equal(options.find(option => option.value === '301').textContent, 'Lácteos > Sin azúcar');
        assert.equal(options.find(option => option.value === '402').textContent, 'Panadería & afines > Integral > Pan de molde');
        assert.equal(f.d.querySelector('[data-product-category-edit="202"]').closest('tr').cells[1].textContent, 'Bebidas > Sin azúcar');
        assert.equal(f.d.querySelector('[data-product-categories-count]').textContent, '64 categorias');
        assert.ok(f.requests.some(request => request.url === '/api/v1/product-categories'));
        assert.deepEqual(f.errors, []);
    } finally { f.dom.window.close(); }
});

test('editing excludes self and every descendant, then preserves a newly selected parent across both refresh controls', async () => {
    const f = await fixture();
    try {
        f.edit(400);
        assert.equal(f.dialog.open, true);
        for (const id of ['400', '401', '402']) assert.equal([...f.select.options].some(option => option.value === id), false);
        f.edit(202);
        assert.equal(f.select.value, '201', 'Current parent outside list page is retained');
        assert.equal([...f.select.options].some(option => option.value === '202'), false);
        f.select.value = '301';
        f.control.tree.push(node(800, 'Nueva rama'));
        f.d.querySelector('[data-product-categories-refresh]').click(); await flush();
        assert.equal(f.select.value, '301');
        assert.ok([...f.select.options].some(option => option.value === '800'));
        f.d.querySelector('[data-product-categories-tree-refresh]').click(); await flush();
        assert.equal(f.select.value, '301');
        assert.equal(f.form.elements.id.value, '202');
        assert.equal(f.dialog.open, true);
        f.submit(); await flush();
        assert.equal(f.writes()[0].url, '/api/v1/admin/product-categories/202');
        assert.equal(f.writes()[0].body.parent_id, 301);
        assert.equal(f.dialog.open, false);
        assert.deepEqual(f.errors, []);
    } finally { f.dom.window.close(); }
});

test('moving an existing category to root sends explicit null and keeps the modal and choice on rejected save', async () => {
    const f = await fixture();
    try {
        f.edit(202);
        f.select.value = '';
        f.control.rejectWrite = true;
        f.submit(); await flush();
        assert.equal(f.writes()[0].method, 'PATCH');
        assert.equal(f.writes()[0].body.parent_id, null);
        assert.equal(f.dialog.open, true);
        assert.equal(f.select.value, '');
        f.control.rejectWrite = false;
        f.submit(); await flush();
        assert.equal(f.writes()[1].body.parent_id, null);
        assert.equal(f.dialog.open, false);
        assert.deepEqual(f.errors, []);
    } finally { f.dom.window.close(); }
});

test('missing parent stays selected but disabled, uses provided parent name, and never silently submits as root', async () => {
    const f = await fixture();
    try {
        f.control.list = [row(901, 'Hoja fuera del árbol', 999, { parent: { id: 999, name: 'Archivada <dato>' } })];
        f.d.querySelector('[data-product-categories-refresh]').click(); await flush();
        const tableRow = f.d.querySelector('[data-product-category-edit="901"]').closest('tr');
        assert.equal(tableRow.cells[1].textContent, 'Archivada <dato>');
        assert.equal(tableRow.querySelector('dato'), null);
        f.edit(901);
        assert.equal(f.select.value, '999');
        assert.equal(f.select.selectedOptions[0].disabled, true);
        assert.equal(f.select.selectedOptions[0].textContent, 'Archivada <dato> (no disponible)');
        assert.equal([...f.select.options].some(option => option.value === '901'), false, 'Paginated rows never invent tree options');
        f.submit(); await flush();
        assert.equal(f.writes().length, 0);
        assert.equal(f.dialog.open, true);
        assert.match(f.dialog.textContent, /no esta disponible/);
        f.select.value = '201';
        f.submit(); await flush();
        assert.equal(f.writes()[0].body.parent_id, 201);
        assert.deepEqual(f.errors, []);
    } finally { f.dom.window.close(); }
});

test('opening an edit before the tree arrives preserves its parent and enables it once loaded', async () => {
    const f = await fixture({ deferTree: true });
    try {
        f.edit(202);
        assert.equal(f.select.value, '201');
        assert.equal(f.select.selectedOptions[0].disabled, true);
        f.control.resolveTree(); await flush();
        assert.equal(f.select.value, '201');
        assert.equal(f.select.selectedOptions[0].disabled, false);
        assert.equal(f.select.selectedOptions[0].textContent, 'Bebidas > Sin azúcar');
        assert.equal(f.dialog.open, true);
        assert.deepEqual(f.errors, []);
    } finally { f.dom.window.close(); }
});

test('restoring refreshes tree options without losing an in-progress parent choice; new resets the editor', async () => {
    const f = await fixture();
    try {
        f.edit(202);
        f.select.value = '301';
        f.d.querySelector('[data-product-category-restore-id]').value = '900';
        f.d.querySelector('[data-product-category-restore-submit]').click(); await flush();
        assert.equal(f.writes()[0].url, '/api/v1/admin/product-categories/900/restore');
        assert.ok([...f.select.options].some(option => option.value === '900'));
        assert.equal(f.select.value, '301');
        f.d.querySelector('[data-screen-primary-action]').click();
        assert.equal(f.form.elements.id.value, '');
        assert.equal(f.select.value, '');
        assert.ok([...f.select.options].some(option => option.value === '202'));
        assert.equal(f.dialog.open, true);
        assert.deepEqual(f.errors, []);
    } finally { f.dom.window.close(); }
});

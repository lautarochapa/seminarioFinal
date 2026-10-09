const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { JSDOM } = require('jsdom');
const base = path.resolve(__dirname, '../..');
const read = file => fs.readFileSync(path.join(base, file), 'utf8');
const flush = async () => { for (let i = 0; i < 6; i++) await new Promise(resolve => setImmediate(resolve)); };
const ingredients = [{ id: 213, name: 'fideo' }, { id: 491, name: 'puré de tomate' }];

async function fixture() {
    const template = new JSDOM(read('resources/views/web/admin-screen.blade.php'));
    const markup = template.window.document.querySelector('[data-admin-products]').outerHTML;
    template.window.close();
    const dom = new JSDOM('<button data-screen-primary-action>Nuevo</button>' + markup, { url: 'https://qa.invalid/admin-web/products', runScripts: 'outside-only' });
    const w = dom.window, requests = [], errors = [];
    const product = { id: 55, name: 'Puré Salsati', ingredient_id: 491, ingredient: ingredients[1], status: 'active' };
    w.addEventListener('error', event => { errors.push(event.error || event.message); event.preventDefault(); });
    w.HTMLElement.prototype.scrollIntoView = function () {};
    w.fetch = () => { throw new Error('Network forbidden'); };
    w.CCApi = { request: async (url, options = {}) => {
        requests.push({ url, ...options });
        if (options.method) return { data: { ...product, ...options.body } };
        if (url.startsWith('/api/v1/admin/ingredients?')) {
            const query = new URL(url, 'https://qa.invalid').searchParams.get('search');
            return { data: query ? ingredients.filter(item => item.name.includes(query)) : [{ id: 1, name: 'aceite' }] };
        }
        if (url.startsWith('/api/v1/admin/products?')) return { data: [product], meta: { total: 1, current_page: 1, last_page: 1 } };
        if (url === '/api/v1/products/55') return { data: product };
        return { data: [] };
    } };
    await new Promise(resolve => w.document.addEventListener('DOMContentLoaded', resolve, { once: true }));
    if (fs.existsSync(path.join(base, 'public/js/admin-ingredient-picker.js'))) w.eval(read('public/js/admin-ingredient-picker.js'));
    w.eval(read('public/js/admin-products.js'));
    w.document.dispatchEvent(new w.Event('DOMContentLoaded'));
    await flush();
    return { dom, w, d: w.document, requests, errors };
}

test('product editor finds an ingredient beyond the initial page and submits its real ID', async () => {
    const f = await fixture();
    try {
        const select = f.d.querySelector('[data-product-ingredient-select]');
        assert.ok(select.ccIngredientPicker, 'Product ingredient selector has remote search');
        const input = select.ccIngredientPicker.input;
        input.value = 'fideo'; input.dispatchEvent(new f.w.Event('input', { bubbles: true }));
        await new Promise(resolve => setTimeout(resolve, 350)); await flush();
        assert.ok([...select.options].some(option => option.value === '213'));
        select.value = '213'; select.dispatchEvent(new f.w.Event('change', { bubbles: true }));
        const form = f.d.querySelector('[data-product-form]');
        form.elements.name.value = 'Terrabusi';
        form.dispatchEvent(new f.w.Event('submit', { cancelable: true, bubbles: true })); await flush();
        const write = f.requests.find(request => request.method === 'POST');
        assert.equal(write.body.ingredient_id, 213);
        assert.ok(!('search' in write.body), 'Search input is not part of the product payload');
        assert.deepEqual(f.errors, []);
    } finally { f.dom.window.close(); }
});

test('editing preserves an existing ingredient outside the initial page; new product clears it', async () => {
    const f = await fixture();
    try {
        f.d.querySelector('[data-product-edit="55"]').click(); await flush();
        const form = f.d.querySelector('[data-product-form]');
        assert.equal(form.elements.ingredient_id.value, '491');
        assert.match(form.elements.ingredient_id.selectedOptions[0].textContent, /puré de tomate/);
        form.elements.description.value = 'Editar otro campo';
        form.dispatchEvent(new f.w.Event('submit', { cancelable: true, bubbles: true })); await flush();
        assert.equal(f.requests.find(request => request.method === 'PATCH').body.ingredient_id, 491);
        f.d.querySelector('[data-product-edit="55"]').click(); await flush();
        f.d.querySelector('[data-screen-primary-action]').click();
        assert.equal(form.elements.ingredient_id.value, '');
        assert.equal(form.elements.ingredient_id.ccIngredientPicker.input.value, '');
        assert.deepEqual(f.errors, []);
    } finally { f.dom.window.close(); }
});

test('product ingredient filter can search beyond the first page without changing the editor', async () => {
    const f = await fixture();
    try {
        const select = f.d.querySelector('[data-products-ingredient]');
        assert.ok(select.ccIngredientPicker);
        select.ccIngredientPicker.input.value = 'puré';
        select.ccIngredientPicker.input.dispatchEvent(new f.w.Event('input', { bubbles: true }));
        await new Promise(resolve => setTimeout(resolve, 350)); await flush();
        select.value = '491';
        select.dispatchEvent(new f.w.Event('change', { bubbles: true }));
        f.d.querySelector('[data-products-refresh]').click(); await flush();
        assert.ok(f.requests.some(request => /ingredient_id=491/.test(request.url)));
        assert.equal(f.d.querySelector('[data-product-ingredient-select]').value, '');
        assert.deepEqual(f.errors, []);
    } finally { f.dom.window.close(); }
});

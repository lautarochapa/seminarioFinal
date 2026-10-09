const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { JSDOM } = require('jsdom');

const base = path.resolve(__dirname, '../..');
const read = file => fs.readFileSync(path.join(base, file), 'utf8');
const clone = value => JSON.parse(JSON.stringify(value));
const units = [{ id: 1, code: 'g', name: 'Gramo' }, { id: 2, code: 'kg', name: 'Kilogramo' }, { id: 5, code: 'unit', name: 'Unidad' }];
const row = overrides => ({ id: 40, ingredient_id: 543, ingredient_name: 'Sal', quantity: '0.0000', unit_id: 5, unit_name: 'Unidad', is_optional: true, notes: 'Sal según necesites', sort_order: 0, ...overrides });
async function settle() { for (let i = 0; i < 5; i++) await new Promise(resolve => setImmediate(resolve)); }
async function fixture(ingredients = [], canEdit = true, save) {
    const dom = new JSDOM('<main id="ingredients"></main>', { url: 'https://qa.invalid/recipes', runScripts: 'outside-only' });
    const w = dom.window, d = w.document, requests = [];
    w.fetch = () => { throw new Error('Network forbidden'); };
    w.XMLHttpRequest.prototype.open = () => { throw new Error('Network forbidden'); };
    w.CCApi = { request: async (url, options = {}) => {
        requests.push({ url, options: clone(options) });
        if (url.includes('/units?')) return { data: units };
        if (url.includes('/ingredients?')) return { data: [{ id: 543, name: 'Sal' }] };
        assert.ok(options.method, 'Unexpected read request');
        return save ? save(url, options) : { data: row({ ...options.body }) };
    } };
    w.eval(read('public/js/recipe-ingredients.js'));
    w.RecipeIngredients.mount(d.getElementById('ingredients'), 8, canEdit, ingredients);
    await settle();
    return { dom, w, d, requests, change(input, checked) { input.checked = checked; input.dispatchEvent(new w.Event('change', { bubbles: true })); } };
}

test('shared admin/user reader labels only optional zero and escapes the original note', async () => {
    const f = await fixture([row({ notes: '<img src=x> Sal al gusto' }), row({ id: 41, quantity: '2.0000', unit_name: 'Gramo', is_optional: true })], false);
    try {
        const text = f.d.querySelector('[data-ingr-list]').textContent;
        assert.equal((text.match(/A gusto \/ cantidad necesaria/g) || []).length, 1);
        assert.match(text, /2\s*Gramo.*\(opc\.\)/);
        assert.doesNotMatch(text, /0\s*Unidad/);
        assert.equal(f.d.querySelectorAll('img').length, 0);
        assert.match(text, /<img src=x> Sal al gusto/);
    } finally { f.dom.window.close(); }
});

test('add free quantity sends zero/optional with the existing generic unit and keeps literal notes', async () => {
    const f = await fixture();
    try {
        f.d.querySelector('[data-ingr-toggle]').click();
        const search = f.d.querySelector('[data-ingr-search]');
        search.value = 'sal'; search.dispatchEvent(new f.w.Event('input'));
        await new Promise(resolve => setTimeout(resolve, 380)); await settle();
        f.d.querySelector('[data-ingr-pick]').click();
        f.d.querySelector('[data-ingr-unit]').value = '1';
        f.change(f.d.querySelector('[data-ingr-free-quantity]'), true);
        assert.equal(f.d.querySelector('[data-ingr-qty]').disabled, true);
        assert.equal(f.d.querySelector('[data-ingr-unit]').disabled, true);
        f.d.querySelector('[data-ingr-notes]').value = 'Sal según necesites';
        f.d.querySelector('[data-ingr-submit]').click(); await settle();
        const write = f.requests.find(r => r.options.method === 'POST');
        assert.deepEqual(write.options.body, { ingredient_id: 543, quantity: 0, unit_id: 5, notes: 'Sal según necesites', is_optional: true });
        assert.match(f.d.querySelector('[data-ingr-list]').textContent, /A gusto \/ cantidad necesaria/);
        assert.equal(f.d.querySelector('[data-ingr-free-quantity]').checked, false, 'Next ingredient starts with a measured quantity');
    } finally { f.dom.window.close(); }
});

test('editing free quantity retains its original unit and note', async () => {
    const f = await fixture([row({ unit_id: 2, unit_name: 'Kilogramo' })]);
    try {
        f.d.querySelector('[data-ingr-edit]').click();
        assert.equal(f.d.querySelector('[name=edit_free_quantity]').checked, true);
        assert.equal(f.d.querySelector('[name=edit_unit]').value, '2');
        assert.equal(f.d.querySelector('[name=edit_qty]').value, '');
        f.d.querySelector('[data-ingr-save]').click(); await settle();
        assert.deepEqual(f.requests.find(r => r.options.method === 'PATCH').options.body, { quantity: 0, unit_id: 2, notes: 'Sal según necesites', is_optional: true });
    } finally { f.dom.window.close(); }
});

test('positive optional ingredients stay measured and can be changed to free only explicitly', async () => {
    const f = await fixture([row({ quantity: '2.0000', unit_id: 1, unit_name: 'Gramo', notes: 'Opcional' })]);
    try {
        f.d.querySelector('[data-ingr-edit]').click();
        assert.equal(f.d.querySelector('[name=edit_free_quantity]').checked, false);
        assert.equal(f.d.querySelector('[name=edit_qty]').disabled, false);
        f.change(f.d.querySelector('[name=edit_free_quantity]'), true);
        f.change(f.d.querySelector('[name=edit_free_quantity]'), false);
        assert.equal(f.d.querySelector('[name=edit_qty]').value, '2', 'Toggling does not discard entered quantity');
        f.d.querySelector('[data-ingr-save]').click(); await settle();
        assert.deepEqual(f.requests.find(r => r.options.method === 'PATCH').options.body, { quantity: 2, unit_id: 1, notes: 'Opcional', is_optional: true });
    } finally { f.dom.window.close(); }
});

test('switching off free quantity requires a positive value and feedback stays visible in editor', async () => {
    const f = await fixture([row()]);
    try {
        f.d.querySelector('[data-ingr-edit]').click();
        f.change(f.d.querySelector('[name=edit_free_quantity]'), false);
        f.d.querySelector('[data-ingr-save]').click(); await settle();
        assert.equal(f.requests.filter(r => r.options.method).length, 0);
        assert.match(f.d.querySelector('[data-ingr-edit-msg]').textContent, /Cantidad inválida/);
        assert.equal(f.d.querySelector('[data-ingr-edit-msg]').style.display, 'block');
        f.d.querySelector('[name=edit_qty]').value = '3';
        f.d.querySelector('[data-ingr-save]').click(); await settle();
        assert.equal(f.requests.find(r => r.options.method === 'PATCH').options.body.quantity, 3);
    } finally { f.dom.window.close(); }
});

test('a rejected save preserves the free quantity selection and original text', async () => {
    const f = await fixture([row()], true, async () => { throw { payload: { error: { message: 'Revisar la aclaración' } } }; });
    try {
        f.d.querySelector('[data-ingr-edit]').click();
        f.d.querySelector('[data-ingr-save]').click(); await settle();
        assert.equal(f.d.querySelector('[name=edit_free_quantity]').checked, true);
        assert.equal(f.d.querySelector('[name=edit_notes]').value, 'Sal según necesites');
        assert.match(f.d.querySelector('[data-ingr-edit-msg]').textContent, /Revisar la aclaración/);
    } finally { f.dom.window.close(); }
});

test('candidate mapping form uses the same free quantity payload and reloads saved zero as free', async () => {
    const template = new JSDOM(read('resources/views/web/admin-screen.blade.php'));
    const markup = template.window.document.querySelector('[data-admin-recipe-import-candidates]').outerHTML;
    template.window.close();
    const dom = new JSDOM(markup, { url: 'https://qa.invalid/admin-web/imported-recipes', runScripts: 'outside-only' });
    const w = dom.window, d = w.document, writes = [], errors = [];
    // JSDOM omits the browser's form named-property lookup, including name=id.
    for (const form of d.forms) {
        for (const control of form.elements) {
            if (control.name) Object.defineProperty(form, control.name, { configurable: true, get: () => form.elements.namedItem(control.name) });
        }
    }
    let candidate = { id: 7, status: 'parsed', source_site: 'Archivo local', raw_title: 'Receta local', raw_description: '', raw_ingredients_json: ['Sal según necesites'], raw_steps_json: [{ step_number: 1, description: 'Mezclar' }], parsed_recipe_json: {}, ingredient_suggestions: [] };
    w.fetch = () => { throw new Error('Network forbidden'); };
    w.XMLHttpRequest.prototype.open = () => { throw new Error('Network forbidden'); };
    w.addEventListener('error', event => { errors.push(String(event.error)); event.preventDefault(); });
    w.CCApi = { request: async (url, options = {}) => {
        if (options.method) {
            writes.push(clone(options.body));
            candidate.parsed_recipe_json.ingredient_mappings = [{ ...options.body }];
            return { data: clone(candidate) };
        }
        if (url.includes('/units?')) return { data: units };
        if (url.includes('/ingredients?')) return { data: [{ id: 543, name: 'Sal' }] };
        if (/import-candidates\/7$/.test(url)) return { data: clone(candidate) };
        return { data: [clone(candidate)], meta: { current_page: 1, last_page: 1, total: 1 } };
    } };
    await new Promise(resolve => d.addEventListener('DOMContentLoaded', resolve, { once: true }));
    w.eval(read('public/js/admin-recipe-import-candidates.js')); d.dispatchEvent(new w.Event('DOMContentLoaded')); await settle();
    try {
        d.querySelector('[data-import-candidates-show]').click(); await settle();
        const form = d.querySelector('[data-import-candidates-map-form]');
        form.ingredient_index.value = '0'; form.ingredient_index.dispatchEvent(new w.Event('change'));
        form.ingredient_id.value = '543'; form.free_quantity.checked = true; form.free_quantity.dispatchEvent(new w.Event('change'));
        assert.equal(form.quantity.disabled, true); assert.equal(form.unit_id.disabled, true);
        form.dispatchEvent(new w.Event('submit', { bubbles: true, cancelable: true })); await settle();
        assert.deepEqual(writes[0], { ingredient_index: 0, ingredient_id: 543, unit_id: 5, quantity: 0, notes: 'Sal según necesites', is_optional: true });
        form.ingredient_index.value = '0'; form.ingredient_index.dispatchEvent(new w.Event('change'));
        assert.equal(form.free_quantity.checked, true); assert.equal(form.notes.value, 'Sal según necesites');
        assert.match(d.querySelector('[data-import-candidates-detail]').textContent, /a gusto \/ cantidad necesaria/);
        assert.deepEqual(errors, []);
    } finally { dom.window.close(); }
});

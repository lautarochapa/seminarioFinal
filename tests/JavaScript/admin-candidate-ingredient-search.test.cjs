const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { JSDOM } = require('jsdom');

const base = path.resolve(__dirname, '../..');
const read = file => fs.readFileSync(path.join(base, file), 'utf8');
const markup = new JSDOM(read('resources/views/web/admin-screen.blade.php'));
const flush = async () => {
    for (let i = 0; i < 6; i++) await new Promise(resolve => setImmediate(resolve));
};
const candidate = (id, extras = {}) => ({
    id, raw_name: 'Producto ' + id, review_status: 'pending',
    enrichment: { detected: {}, suggested: {}, unresolved: [] }, ...extras,
});

async function fixture(candidates, options = {}) {
    const feature = markup.window.document.querySelector('[data-admin-scraped-products]');
    const dom = new JSDOM('<main data-admin-ui data-admin-screen="scraped-products">' + feature.outerHTML + '</main>', { url: 'https://qa.invalid', runScripts: 'outside-only' });
    const w = dom.window;
    const requests = [];
    const attached = [];
    const errors = [];
    w.addEventListener('error', event => { errors.push(event.error || event.message); event.preventDefault(); });
    w.fetch = () => { throw new Error('Network access is forbidden'); };
    w.XMLHttpRequest.prototype.open = () => { throw new Error('Network access is forbidden'); };
    w.HTMLElement.prototype.scrollIntoView = function () {};
    w.HTMLDialogElement.prototype.showModal = function () { this.open = true; };
    w.HTMLDialogElement.prototype.close = function () { this.open = false; this.dispatchEvent(new w.Event('close')); };
    w.CCIngredientPicker = {
        attach(select) {
            attached.push(select);
            const picker = {
                setSelected(entity) {
                    select.replaceChildren(new w.Option('Ingrediente', ''));
                    if (entity) select.add(new w.Option(entity.name, entity.id));
                    select.value = entity ? String(entity.id) : '';
                },
                reset() { this.setSelected(null); },
            };
            select.ccIngredientPicker = picker;
            return picker;
        },
    };
    w.CCApi = {
        async request(url, options = {}) {
            requests.push({ url, options: JSON.parse(JSON.stringify(options)) });
            if (url.includes('/admin/ingredients?')) {
                const query = new URL(url, 'https://qa.invalid').searchParams.get('search');
                const rows = query === 'fideo' ? [{ id: 213, name: 'Fideo seco' }] :
                    query === 'puré de tomate' ? [{ id: 491, name: 'Puré de tomate' }] : [{ id: 1, name: 'Aceite' }];
                return { data: rows, meta: { total: rows.length } };
            }
            const match = url.match(/product-candidates\/(\d+)(?:\/([^?]+))?$/);
            if (match) {
                const found = candidates.find(row => row.id === Number(match[1]));
                if (match[2] === 'assign-ingredient') {
                    return { data: { ...found, suggested_ingredient_id: options.body.ingredient_id,
                        suggested_ingredient: { id: options.body.ingredient_id, name: options.body.ingredient_id === 213 ? 'Fideo seco' : 'Puré de tomate' } } };
                }
                return { data: options.method ? { ...found, review_status: 'approved' } : found };
            }
            return { data: url.includes('/product-candidates?') ? candidates : [], meta: { total: candidates.length, current_page: 1, last_page: 1 } };
        },
    };
    await new Promise(resolve => w.document.addEventListener('DOMContentLoaded', resolve, { once: true }));
    if (options.realPicker) {
        for (const script of ['admin-labels', 'panel-ui', 'admin-panel-ui', 'admin-ingredient-picker']) {
            w.eval(read('public/js/' + script + '.js'));
        }
    }
    w.eval(read('public/js/admin-scraped-products.js'));
    w.document.dispatchEvent(new w.Event('DOMContentLoaded'));
    await flush();
    return {
        dom, w, d: w.document, requests, attached, errors,
        async open(id) {
            w.document.querySelector('[data-candidate-view="' + id + '"]').click();
            await flush();
        },
    };
}

test('candidate review initializes both remote ingredient pickers without a truncated ingredient lookup', async () => {
    const f = await fixture([candidate(1)]);
    try {
        assert.equal(f.attached.length, 2);
        assert.ok(f.d.querySelector('[data-candidate-ingredient]').ccIngredientPicker);
        assert.ok(f.d.querySelector('[data-candidate-create-ingredient]').ccIngredientPicker);
        assert.equal(f.requests.filter(row => /\/ingredients\?/.test(row.url)).length, 0);
        assert.deepEqual(f.errors, []);
    } finally { f.dom.window.close(); }
});

test('candidate suggestion outside the first ingredient page is hydrated and sent as numeric ingredient_id', async () => {
    const ingredient = { id: 491, name: 'Puré de tomate' };
    const f = await fixture([candidate(1, { suggested_ingredient_id: 491, suggested_ingredient: ingredient })]);
    try {
        await f.open(1);
        const select = f.d.querySelector('[data-candidate-create-ingredient]');
        assert.equal(select.value, '491');
        assert.equal(select.selectedOptions[0].textContent, ingredient.name);
        assert.equal(f.d.querySelector('[data-candidate-ingredient]').value, '491');
        f.d.querySelector('[data-candidate-create-and-approve]').click();
        await flush();
        const write = f.requests.find(row => row.options.method);
        assert.equal(write.url, '/api/v1/admin/scraping/product-candidates/1/create-and-approve');
        assert.equal(write.options.body.ingredient_id, 491);
        assert.deepEqual(f.errors, []);
    } finally { f.dom.window.close(); }
});

test('enrichment suggestion is selectable and a later candidate cannot inherit a manual ingredient choice', async () => {
    const f = await fixture([
        candidate(1, { enrichment: { detected: {}, suggested: { ingredient_id: 213, ingredient_name: 'Fideo seco' }, unresolved: [] } }),
        candidate(2),
    ]);
    try {
        await f.open(1);
        const create = f.d.querySelector('[data-candidate-create-ingredient]');
        const assign = f.d.querySelector('[data-candidate-ingredient]');
        assert.equal(create.value, '213');
        assert.equal(assign.value, '213');
        create.ccIngredientPicker.setSelected({ id: 491, name: 'Puré de tomate' });
        assign.ccIngredientPicker.setSelected({ id: 491, name: 'Puré de tomate' });
        await f.open(2);
        assert.equal(f.attached.length, 2, 'Reuse the two pickers when changing candidates');
        assert.equal(create.value, '');
        assert.equal(assign.value, '');
        f.d.querySelector('[data-candidate-create-and-approve]').click();
        await flush();
        const write = f.requests.find(row => row.options.method);
        assert.equal(write.url, '/api/v1/admin/scraping/product-candidates/2/create-and-approve');
        assert.equal(Object.hasOwn(write.options.body, 'ingredient_id'), false, 'Blank selection preserves server fallback');
        assert.deepEqual(f.errors, []);
    } finally { f.dom.window.close(); }
});

test('a searched ingredient choice is sent by the existing assign endpoint', async () => {
    const f = await fixture([candidate(1)]);
    try {
        await f.open(1);
        const assign = f.d.querySelector('[data-candidate-ingredient]');
        assert.ok(assign.ccIngredientPicker);
        assign.ccIngredientPicker.setSelected({ id: 213, name: 'Fideo seco' });
        f.d.querySelector('[data-candidate-ingredient-form]').dispatchEvent(new f.w.Event('submit', { bubbles: true, cancelable: true }));
        await flush();
        const write = f.requests.find(row => row.options.method);
        assert.equal(write.url, '/api/v1/admin/scraping/product-candidates/1/assign-ingredient');
        assert.equal(write.options.body.ingredient_id, 213);
        assert.deepEqual(f.errors, []);
    } finally { f.dom.window.close(); }
});

test('real remote picker survives the review modal and submits searched ingredients through both candidate actions', async () => {
    const f = await fixture([candidate(1), candidate(2)], { realPicker: true });
    async function searchAndChoose(select, query, id) {
        const picker = select.ccIngredientPicker;
        assert.ok(picker.input.isConnected, 'Search input remains mounted inside the moved review panel');
        assert.ok(picker.input.closest('dialog').open, 'The real review dialog is open');
        picker.input.value = query;
        picker.input.dispatchEvent(new f.w.Event('input', { bubbles: true }));
        await new Promise(resolve => setTimeout(resolve, 350));
        await flush();
        assert.ok([...select.options].some(option => option.value === String(id)), 'Remote result is available beyond the initial page');
        select.value = String(id);
        select.dispatchEvent(new f.w.Event('change', { bubbles: true }));
    }
    try {
        await f.open(1);
        const create = f.d.querySelector('[data-candidate-create-ingredient]');
        const dialog = create.closest('dialog');
        await searchAndChoose(create, 'fideo', 213);
        f.d.querySelector('[data-candidate-create-and-approve]').click();
        await flush();
        let writes = f.requests.filter(row => row.options.method);
        assert.equal(writes.length, 1);
        assert.equal(writes[0].url, '/api/v1/admin/scraping/product-candidates/1/create-and-approve');
        assert.equal(writes[0].options.body.ingredient_id, 213);
        assert.equal(dialog.open, false, 'Successful approval closes the real review modal');

        await f.open(2);
        assert.equal(create.value, '', 'Opening a second candidate clears the first search selection');
        const assign = f.d.querySelector('[data-candidate-ingredient]');
        assign.closest('details').open = true;
        await searchAndChoose(assign, 'puré de tomate', 491);
        f.d.querySelector('[data-candidate-ingredient-form]').dispatchEvent(new f.w.Event('submit', { bubbles: true, cancelable: true }));
        await flush();
        writes = f.requests.filter(row => row.options.method);
        assert.equal(writes.length, 2);
        assert.equal(writes[1].url, '/api/v1/admin/scraping/product-candidates/2/assign-ingredient');
        assert.equal(writes[1].options.body.ingredient_id, 491);
        assert.equal(assign.value, '491', 'Saved suggestion is hydrated after the action refresh');
        assert.equal(create.value, '491');
        assert.ok(f.requests.some(row => row.url.includes('search=fideo&per_page=20')));
        assert.ok(f.requests.some(row => row.url.includes('search=pur%C3%A9%20de%20tomate&per_page=20')));
        assert.equal(f.requests.some(row => /\/ingredients\?[^]*per_page=100/.test(row.url)), false);
        assert.deepEqual(f.errors, []);
    } finally { f.dom.window.close(); }
});

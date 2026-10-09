const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { JSDOM } = require('jsdom');

const source = path.resolve(__dirname, '../../public/js/admin-ingredient-picker.js');
const flush = async () => { for (let i = 0; i < 4; i++) await new Promise(resolve => setImmediate(resolve)); };

function fixture(t, { lifecycle = true } = {}) {
    const dom = new JSDOM('<form><label for="ingredient">Ingrediente principal</label><select id="ingredient" name="ingredient_id"><option value="">Ingrediente principal</option></select></form>', { url: 'https://qa.invalid', runScripts: 'outside-only' });
    t.after(() => dom.window.close());
    const w = dom.window, d = w.document, select = d.querySelector('select');
    const requests = [], timers = new Map(), disposers = [];
    let clock = 0, timerId = 0;
    w.setTimeout = (fn, delay) => { const id = ++timerId; timers.set(id, { fn, at: clock + delay }); return id; };
    w.clearTimeout = id => timers.delete(id);
    w.fetch = () => { throw new Error('Network forbidden'); };
    w.CCApi = { request(url) { return new Promise((resolve, reject) => requests.push({ url, resolve, reject })); } };
    if (lifecycle) w.CCPage = { onDispose: fn => disposers.push(fn) };
    w.eval(fs.readFileSync(source, 'utf8'));
    const picker = w.CCIngredientPicker.attach(select);
    return {
        w, d, select, picker, requests, disposers, timers,
        input: () => d.querySelector('[data-ingredient-search]'),
        status: () => d.querySelector('[role="status"]'),
        type(value) { const input = this.input(); input.value = value; input.dispatchEvent(new w.Event('input', { bubbles: true })); },
        async advance(ms) { clock += ms; for (const [id, timer] of [...timers]) if (timer.at <= clock) { timers.delete(id); timer.fn(); } await flush(); },
        choose(id) { select.value = String(id); select.dispatchEvent(new w.Event('change', { bubbles: true })); },
    };
}

test('initial lookup is bounded, accessible and idempotent, and never chooses the first result', async t => {
    const h = fixture(t); await flush();
    assert.equal(h.requests.length, 1);
    assert.equal(h.requests[0].url, '/api/v1/admin/ingredients?status=active&search=&per_page=20&sort=name&order=asc');
    assert.equal(h.w.CCIngredientPicker.attach(h.select), h.picker);
    assert.equal(h.select.ccIngredientPicker, h.picker);
    assert.equal(h.d.querySelectorAll('[data-ingredient-search]').length, 1);
    assert.equal(h.input().labels[0].textContent, 'Buscar ingrediente');
    assert.equal(h.input().name, '');
    assert.equal(h.input().getAttribute('aria-describedby'), h.status().id);
    h.requests[0].resolve({ data: [{ id: 1, name: 'Aceite' }] }); await flush();
    assert.equal(h.select.value, '');
    assert.equal(h.select.options.length, 2);
    assert.equal(h.input().getAttribute('aria-busy'), 'false');
});

test('search, original label and select share one field without replacing the original form control', async t => {
    const h = fixture(t); await flush();
    const originalLabel = h.select.labels[0];
    const group = h.input().parentElement;
    assert.equal(h.select.parentElement, group);
    assert.equal(originalLabel.parentElement, group);
    assert.equal(originalLabel.textContent, 'Ingrediente principal');
    assert.equal(group.style.minWidth, '0');
    assert.equal(group.style.maxWidth, '100%');
    h.picker.setSelected({ id: 491, name: 'puré de tomate' });
    h.disposers.forEach(fn => fn());
    assert.equal(h.d.querySelector('select'), h.select);
    assert.equal(h.select.parentElement, h.d.querySelector('form'));
    assert.equal(h.select.previousElementSibling, originalLabel);
    assert.equal(h.select.value, '491');
});

test('debounced searches reach ingredients beyond the first page and preserve selection and form payload', async t => {
    const h = fixture(t); await flush();
    h.requests[0].resolve({ data: [] }); await flush();
    h.type('fi'); await h.advance(200); h.type('fideo'); await h.advance(299);
    assert.equal(h.requests.length, 1);
    await h.advance(1);
    assert.equal(new URL(h.requests[1].url, h.w.location.href).searchParams.get('search'), 'fideo');
    h.requests[1].resolve({ data: [{ id: 213, name: 'fideo' }] }); await flush();
    h.choose(213); h.type('puré de tomate'); await h.advance(300);
    h.requests[2].resolve({ data: [{ id: 491, name: 'puré de tomate' }] }); await flush();
    assert.equal(h.select.value, '213');
    h.choose(491);
    assert.deepEqual([...new h.w.FormData(h.d.querySelector('form')).entries()], [['ingredient_id', '491']]);
    assert.equal(h.select.selectedOptions[0].textContent, 'puré de tomate');
});

test('Enter searches immediately without submitting the product form or repeating the queued search', async t => {
    const h = fixture(t); await flush();
    let submitted = 0;
    h.d.querySelector('form').addEventListener('submit', event => { submitted++; event.preventDefault(); });
    h.type('fideo');
    const enter = new h.w.KeyboardEvent('keydown', { key: 'Enter', bubbles: true, cancelable: true });
    const defaultAllowed = h.input().dispatchEvent(enter);
    // JSDOM does not implement implicit submission, so emulate the browser's default action.
    if (defaultAllowed) h.d.querySelector('form').dispatchEvent(new h.w.Event('submit', { bubbles: true, cancelable: true }));
    await flush();
    assert.equal(enter.defaultPrevented, true);
    assert.equal(submitted, 0);
    assert.equal(h.requests.length, 2);
    assert.equal(new URL(h.requests[1].url, h.w.location.href).searchParams.get('search'), 'fideo');
    await h.advance(300);
    assert.equal(h.requests.length, 2);
    const arrow = new h.w.KeyboardEvent('keydown', { key: 'ArrowDown', bubbles: true, cancelable: true });
    h.input().dispatchEvent(arrow);
    assert.equal(arrow.defaultPrevented, false);
});

test('a response from an older query cannot replace newer results, even during debounce', async t => {
    const h = fixture(t); await flush();
    h.type('fideo');
    h.requests[0].resolve({ data: [{ id: 1, name: 'Viejo' }] }); await flush();
    assert.equal(h.select.querySelector('option[value="1"]'), null);
    await h.advance(300); h.type('puré'); await h.advance(300);
    h.requests[2].resolve({ data: [{ id: 491, name: 'puré de tomate' }] }); await flush();
    h.requests[1].resolve({ data: [{ id: 213, name: 'fideo' }] }); await flush();
    assert.equal(h.select.querySelector('option[value="213"]'), null);
    assert.ok(h.select.querySelector('option[value="491"]'));
});

test('hydration preserves an existing selection outside search results and invalidates pending work', async t => {
    const h = fixture(t); await flush();
    h.type('fideo'); h.picker.setSelected({ id: 491, name: 'puré de tomate <natural>' });
    await h.advance(300);
    assert.equal(h.requests.length, 1);
    h.requests[0].resolve({ data: [{ id: 1, name: 'Aceite' }] }); await flush();
    assert.equal(h.select.value, '491');
    assert.equal(h.select.selectedOptions[0].textContent, 'puré de tomate <natural>');
    assert.equal(h.select.querySelector('natural'), null);
    h.picker.setSelected(null);
    assert.equal(h.select.value, '');
});

test('errors preserve selection, announce retry instructions and recover on another search', async t => {
    const h = fixture(t); await flush(); h.picker.setSelected({ id: 213, name: 'fideo' });
    h.type('puré'); await h.advance(300); h.requests[1].reject(new Error('Offline')); await flush();
    assert.equal(h.select.value, '213');
    assert.match(h.status().textContent, /No se pudieron buscar/);
    assert.match(h.status().textContent, /escrib/i);
    h.type('puré de tomate'); await h.advance(300);
    h.requests[2].resolve({ data: [{ id: 491, name: 'puré de tomate' }] }); await flush();
    assert.doesNotMatch(h.status().textContent, /No se pudieron/);
    assert.equal(h.select.value, '213');
    assert.ok(h.select.querySelector('option[value="491"]'));
});

test('reset clears the selection and query without old responses or form.reset selecting an ingredient', async t => {
    const h = fixture(t); await flush(); h.picker.setSelected({ id: 491, name: 'puré de tomate' });
    h.type('fideo'); await h.advance(300); h.picker.reset(); await flush();
    assert.equal(h.input().value, ''); assert.equal(h.select.value, '');
    h.requests[1].resolve({ data: [{ id: 213, name: 'fideo' }] }); await flush();
    assert.equal(h.select.querySelector('option[value="213"]'), null);
    h.requests.at(-1).resolve({ data: [{ id: 1, name: 'Aceite' }] }); await flush();
    h.d.querySelector('form').reset();
    assert.equal(h.select.value, '');
});

test('disposal cancels queued searches and ignores in-flight work', async t => {
    const h = fixture(t); await flush(); h.type('fideo');
    h.disposers.forEach(fn => fn()); await h.advance(300);
    assert.equal(h.requests.length, 1); assert.equal(h.timers.size, 0);
    h.requests[0].resolve({ data: [{ id: 213, name: 'fideo' }] }); await flush();
    assert.equal(h.select.querySelector('option[value="213"]'), null);
    assert.equal(h.d.querySelector('[data-ingredient-search]'), null);
});

test('removal from the document also prevents requests and late rendering without a page lifecycle', async t => {
    const h = fixture(t, { lifecycle: false }); await flush(); h.type('fideo');
    h.select.remove(); await h.advance(300);
    assert.equal(h.requests.length, 1);
    h.requests[0].resolve({ data: [{ id: 213, name: 'fideo' }] }); await flush();
    assert.equal(h.select.querySelector('option[value="213"]'), null);
});

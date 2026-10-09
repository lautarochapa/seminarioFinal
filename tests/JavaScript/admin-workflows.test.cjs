const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { JSDOM } = require('jsdom');

const base = path.resolve(__dirname, '../..');
const read = file => fs.readFileSync(path.join(base, file), 'utf8');
const template = new JSDOM(read('resources/views/web/admin-screen.blade.php'));
const flush = async () => {
    for (let i = 0; i < 6; i++) await new Promise(resolve => setImmediate(resolve));
};
const clone = value => JSON.parse(JSON.stringify(value));
function visible(element) {
    assert.ok(element, 'Expected element exists');
    for (let parent = element; parent; parent = parent.parentElement) {
        if (parent.hidden || parent.style.display === 'none' || (parent.tagName === 'DIALOG' && !parent.open)) return false;
    }
    return true;
}

// Use the repository's real feature markup and scripts. No generated HTML,
// Laravel environment, database, credentials or network are required.
async function fixture(screen, request) {
    const feature = template.window.document.querySelector('[data-admin-' + screen + ']');
    assert.ok(feature, 'Real feature markup exists: ' + screen);
    const dom = new JSDOM('<main data-admin-ui data-admin-screen="' + screen + '">' + feature.outerHTML + '</main>', {
        url: 'https://qa.invalid/admin-web/' + screen,
        runScripts: 'outside-only',
    });
    const w = dom.window;
    const errors = [];
    w.addEventListener('error', event => { errors.push(event.error || event.message); event.preventDefault(); });
    w.HTMLElement.prototype.scrollIntoView = function () {};
    w.HTMLDialogElement.prototype.showModal = function () { this.open = true; };
    w.HTMLDialogElement.prototype.close = function () { this.open = false; this.dispatchEvent(new w.Event('close')); };
    w.fetch = () => { throw new Error('Network access is forbidden in this test'); };
    w.XMLHttpRequest.prototype.open = () => { throw new Error('Network access is forbidden in this test'); };
    w.CCApi = { request };
    w.confirm = () => true;
    await new Promise(resolve => w.document.addEventListener('DOMContentLoaded', resolve, { once: true }));
    for (const script of ['panel-ui', 'admin-panel-ui', 'admin-' + screen]) w.eval(read('public/js/' + script + '.js'));
    w.document.dispatchEvent(new w.Event('DOMContentLoaded'));
    await flush();
    assert.deepEqual(errors, [], 'No initialization exceptions');
    return { dom, w, d: w.document, errors };
}

test('settings: redacted secrets have no editor and cannot be opened through the delegated handler', async () => {
    const records = [{ key: 'service.secret', value: '[REDACTED]', type: 'string', is_public: false }];
    const requests = [];
    const f = await fixture('settings', async (url, options = {}) => {
        requests.push({ url, options });
        assert.ok(!options.method, 'This scenario must not write');
        return { data: records, meta: { total: 1, current_page: 1, last_page: 1 } };
    });
    try {
        assert.equal(f.d.querySelectorAll('[data-settings-edit]').length, 0);
        const syntheticAction = f.d.createElement('button');
        syntheticAction.setAttribute('data-settings-edit', 'service.secret');
        f.d.querySelector('[data-admin-settings]').appendChild(syntheticAction);
        syntheticAction.click();
        assert.equal(f.d.querySelectorAll('[data-settings-edit-form]').length, 0, 'The handler itself refuses redacted data');
        assert.equal(requests.length, 1, 'Only the initial list was requested');
        assert.deepEqual(f.errors, []);
    } finally { f.dom.window.close(); }
});

test('settings: dynamic modal preserves a rejected value, blocks duplicate saves and closes only after success', async () => {
    const record = { key: 'app.caption', value: 'Inicial', type: 'string', description: 'Texto local', is_public: true };
    const requests = [];
    let pending;
    const f = await fixture('settings', (url, options = {}) => {
        requests.push({ url, options: clone(options) });
        if (!options.method) return Promise.resolve({ data: [record], meta: { total: 1, current_page: 1, last_page: 1 } });
        return new Promise((resolve, reject) => { pending = { resolve, reject }; });
    });
    try {
        f.d.querySelector('[data-settings-edit="app.caption"]').click();
        const form = f.d.querySelector('[data-settings-edit-form]');
        const dialog = form.closest('dialog');
        let input = form.querySelector('[data-edit-input]');
        assert.ok(visible(form));
        assert.equal(input.value, 'Inicial');
        assert.equal(f.d.activeElement, input);
        assert.equal(input.labels[0].textContent, 'Valor');
        input.value = 'Conservar al fallar';
        form.dispatchEvent(new f.w.Event('submit', { bubbles: true, cancelable: true }));
        assert.deepEqual(requests.at(-1), {
            url: '/api/v1/admin/settings/app.caption',
            options: { method: 'PATCH', body: { value: 'Conservar al fallar' } },
        });
        assert.equal(form.getAttribute('aria-busy'), 'true');
        assert.ok([...dialog.querySelectorAll('button')].every(button => button.disabled));
        form.dispatchEvent(new f.w.Event('submit', { bubbles: true, cancelable: true }));
        assert.equal(requests.filter(request => request.options.method).length, 1, 'No duplicate PATCH while pending');
        pending.reject({ status: 422, message: 'Valor inválido de prueba' });
        await flush();
        assert.ok(visible(form));
        assert.equal(input.value, 'Conservar al fallar');
        assert.match(dialog.textContent, /Valor inválido de prueba/);
        assert.equal(form.getAttribute('aria-busy'), 'false');
        assert.equal(form.querySelector('[type=submit]').disabled, false);
        form.dispatchEvent(new f.w.Event('submit', { bubbles: true, cancelable: true }));
        pending.resolve({ data: { ...record, value: input.value } });
        await flush();
        assert.equal(dialog.open, false);
        assert.match(f.d.querySelector('[data-settings-row="app.caption"]').textContent, /Conservar al fallar/);
        assert.equal(requests.filter(request => request.options.method).length, 2);
        f.d.querySelector('[data-settings-edit="app.caption"]').click();
        assert.equal(f.d.querySelectorAll('[data-settings-edit-form]').length, 1, 'Editor is reused');
        input = form.querySelector('[data-edit-input]');
        input.value = 'Descartar esta edición';
        const beforeCancel = requests.length;
        form.querySelector('[data-settings-cancel]').click();
        await flush();
        assert.equal(dialog.open, false);
        assert.equal(requests.length, beforeCancel, 'Cancel sends no request');
        assert.deepEqual(f.errors, []);
    } finally { f.dom.window.close(); }
});

test('official recipes: detail, editor dismissal and save keep the correct tab and form visible', async () => {
    const recipe = { id: 88, name: 'Receta QA', status: 'active', source_type: 'official', is_official: true, servings: 2, ingredients: [], steps: [] };
    let pending;
    const f = await fixture('official-recipes', (url, options = {}) => {
        if (options.method) return new Promise((resolve, reject) => { pending = { resolve, reject }; });
        return Promise.resolve({ data: url.endsWith('/88') ? recipe : url.includes('categories') ? [] : [recipe], meta: { current_page: 1, last_page: 1, total: 1 } });
    });
    try {
        f.d.querySelector('[data-recipe-adm-show="88"]').click();
        await flush();
        assert.ok(visible(f.d.querySelector('[data-recipes-adm-detail]')));
        f.d.querySelector('[data-recipe-adm-edit="88"]').click();
        await flush();
        const form = f.d.querySelector('[data-recipes-adm-form]');
        const dialog = form.closest('dialog');
        assert.ok(visible(form));
        assert.equal(form.elements.name.value, 'Receta QA');
        dialog.querySelector('.dialog-close').click();
        assert.equal(dialog.open, false);
        assert.equal(f.d.querySelector('[data-recipes-adm-form-panel]').style.display, 'none');
        f.d.querySelector('[data-recipes-adm-new]').click();
        assert.ok(visible(form));
        form.elements.name.value = 'Receta nueva QA';
        form.dispatchEvent(new f.w.Event('submit', { bubbles: true, cancelable: true }));
        pending.reject({ status: 422, message: 'Receta inválida' });
        await flush();
        assert.ok(visible(form));
        assert.equal(form.elements.name.value, 'Receta nueva QA');
        assert.match(dialog.textContent, /Receta inválida/);
        form.dispatchEvent(new f.w.Event('submit', { bubbles: true, cancelable: true }));
        pending.resolve({ data: recipe });
        await flush();
        assert.equal(dialog.open, false);
        assert.ok(visible(f.d.querySelector('[data-recipes-adm-detail]')));
        assert.deepEqual(f.errors, []);
    } finally { f.dom.window.close(); }
});

for (const screen of ['recipe-import', 'recipe-import-text']) {
    test(screen + ': failed input remains editable and successful parsing reveals the result tab', async () => {
        const textImport = screen === 'recipe-import-text';
        const fieldSelector = textImport ? '[data-importtxt-body]' : '[data-import-url]';
        const buttonSelector = textImport ? '[data-importtxt-btn]' : '[data-import-btn]';
        const detailSelector = textImport ? '[data-importtxt-detail]' : '[data-import-detail]';
        const value = textImport ? 'Receta local de arroz. Ingredientes: arroz. Preparación: hervirlo.' : 'https://recipes.example.invalid/local';
        const requests = [];
        let pending;
        const f = await fixture(screen, (url, options = {}) => {
            requests.push({ url, options: clone(options) });
            return new Promise((resolve, reject) => { pending = { resolve, reject }; });
        });
        try {
            const field = f.d.querySelector(fieldSelector);
            const dialog = field.closest('dialog');
            f.w.CCUI.reveal(field);
            field.value = value;
            f.d.querySelector(buttonSelector).click();
            assert.equal(requests.at(-1).url, '/api/v1/admin/recipes/import/' + (textImport ? 'text' : 'url'));
            assert.equal(requests.at(-1).options.method, 'POST');
            assert.deepEqual(JSON.parse(requests.at(-1).options.body), { [textImport ? 'text' : 'url']: value });
            pending.reject({ status: 422, payload: { error: { message: 'Entrada inválida local' } } });
            await flush();
            assert.ok(visible(field));
            assert.equal(field.value, value);
            assert.match(dialog.textContent, /Entrada inválida local/);
            f.d.querySelector(buttonSelector).click();
            pending.resolve({ data: { id: 90, raw_title: 'Receta importada QA', status: 'parsed', parsed_recipe_json: { title: 'Receta importada QA', ingredients: [], steps: [] } } });
            await flush();
            assert.equal(dialog.open, false);
            assert.ok(visible(f.d.querySelector(detailSelector)));
            assert.match(f.d.querySelector(detailSelector).textContent, /Receta importada QA/);
            assert.equal(requests.length, 2);
            assert.deepEqual(f.errors, []);
        } finally { f.dom.window.close(); }
    });
}

test('health preferences: modal editing preserves the selected native catalog tab', async () => {
    const requests = [];
    const item = { id: 22, code: 'qa', name: 'Item QA', description: 'Local', status: 'active' };
    let pending;
    const f = await fixture('health-preferences', (url, options = {}) => {
        requests.push({ url, options: clone(options) });
        if (options.method) return new Promise((resolve, reject) => { pending = { resolve, reject }; });
        return Promise.resolve({ data: [item], meta: { total: 1 } });
    });
    try {
        const tab = f.d.querySelector('[data-health-tab="allergies"]');
        tab.click();
        await flush();
        assert.equal(tab.getAttribute('aria-selected'), 'true');
        assert.match(requests.at(-1).url, /\/allergies\?/);
        f.d.querySelector('[data-health-edit="22"]').click();
        const form = f.d.querySelector('[data-health-form]');
        const dialog = form.closest('dialog');
        assert.ok(visible(form));
        form.elements.name.value = 'Alergia editada';
        form.dispatchEvent(new f.w.Event('submit', { bubbles: true, cancelable: true }));
        assert.equal(requests.at(-1).url, '/api/v1/admin/allergies/22');
        pending.reject({ status: 422, message: 'Error de alergia' });
        await flush();
        assert.ok(visible(form));
        assert.equal(form.elements.name.value, 'Alergia editada');
        form.dispatchEvent(new f.w.Event('submit', { bubbles: true, cancelable: true }));
        pending.resolve({ data: item });
        await flush();
        assert.equal(dialog.open, false);
        assert.equal(tab.getAttribute('aria-selected'), 'true');
        assert.deepEqual(f.errors, []);
    } finally { f.dom.window.close(); }
});

test.after(() => template.window.close());

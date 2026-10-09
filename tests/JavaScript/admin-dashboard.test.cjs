const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { execFileSync } = require('node:child_process');
const { JSDOM } = require('jsdom');
const base = path.join(__dirname, '../..');
const read = file => fs.readFileSync(path.join(base, file), 'utf8');
const card = (key, label, value, description, url) => ({ key, label, value, description, url });
const catalog = {
    key: 'catalog', title: 'Catálogo y supermercados', description: 'Productos e ingredientes del catálogo general.',
    cards: [
        card('supermarkets', 'Supermercados', 0, 'Cadenas activas.', '/admin-web/supermarkets'),
        card('branches', 'Sucursales', 3, 'Sucursales activas de las cadenas.', '/admin-web/branches'),
        card('ingredients', 'Ingredientes', 1234, 'Ingredientes activos del catálogo.', '/admin-web/ingredients'),
        card('products', 'Productos', 227, 'Productos globales no eliminados.', '/admin-web/products'),
    ],
};
const recipes = {
    key: 'recipes', title: 'Recetas', description: 'Contenido oficial y revisión.',
    cards: [card('official_recipes', 'Recetas oficiales', 8, 'Recetas oficiales publicadas.', '/admin-web/official-recipes')],
};
async function fixture(sections) {
    const html = execFileSync(process.env.PHP_BINARY || 'php', [path.join(__dirname, 'fixtures/render-admin-dashboard.php'), JSON.stringify({ dashboard: { sections } })], { encoding: 'utf8' });
    const dom = new JSDOM(html, { url: 'https://qa.invalid/admin-web', runScripts: 'outside-only' });
    const w = dom.window;
    const calls = [];
    w.CCApi = { request: (...args) => { calls.push(args); throw new Error('Dashboard must use server counts'); } };
    await new Promise(resolve => w.document.addEventListener('DOMContentLoaded', resolve, { once: true }));
    w.eval(read('public/js/panel-ui.js'));
    w.eval(read('public/js/admin-panel-ui.js'));
    w.document.dispatchEvent(new w.Event('DOMContentLoaded'));
    return { dom, d: w.document, calls };
}

test('real dashboard branch shows precise counts and all sections without the old metrics or tabs', async () => {
    const f = await fixture([catalog, recipes]);
    try {
        assert.equal(f.d.querySelectorAll('h1').length, 1);
        assert.equal(f.d.querySelectorAll('[data-dashboard-section]').length, 2);
        assert.equal(f.d.querySelectorAll('[data-dashboard-card]').length, 5);
        assert.equal(f.d.querySelector('[data-dashboard-card="supermarkets"] dd').textContent, '0');
        assert.equal(f.d.querySelector('[data-dashboard-card="ingredients"] dd').textContent, '1.234');
        assert.match(f.d.querySelector('[data-dashboard-card="products"]').textContent, /227[\s\S]*Productos globales no eliminados/);
        assert.equal(f.d.querySelector('[data-admin-ui],.metrics,[role="tab"],[hidden],[data-screen-primary-action],[data-screen-secondary-action],a[href="#"]'), null);
        assert.doesNotMatch(f.d.body.textContent, /PostgreSQL|Estado\s*Listo|\bABM\b|999999/);
        assert.deepEqual(f.calls, []);
        const ids = [...f.d.querySelectorAll('[id]')].map(el => el.id);
        assert.equal(new Set(ids).size, ids.length);
        for (const section of f.d.querySelectorAll('[aria-labelledby]')) assert.ok(f.d.getElementById(section.getAttribute('aria-labelledby')));
        const navigation = f.d.querySelector('nav[aria-label="Secciones del resumen"]');
        assert.ok(navigation);
        assert.deepEqual([...navigation.querySelectorAll('a')].map(link => link.textContent), [catalog.title, recipes.title]);
        for (const link of navigation.querySelectorAll('a')) {
            assert.match(link.getAttribute('href'), /^#admin-dashboard-section-/);
            const destination = f.d.getElementById(link.hash.slice(1));
            assert.ok(destination);
            assert.equal(destination.textContent, link.textContent);
            assert.equal(destination.getAttribute('tabindex'), '-1');
        }
        for (const link of f.d.querySelectorAll('.admin-dashboard-card a')) {
            assert.match(link.getAttribute('href'), /^\/admin-web\//);
            assert.ok(link.getAttribute('aria-label').includes('Ver listado:'));
            assert.ok(f.d.getElementById(link.getAttribute('aria-describedby')));
        }
    } finally { f.dom.window.close(); }
});

test('view renders only the sections and destination links provided by the permission-filtered contract', async () => {
    const f = await fixture([recipes]);
    try {
        assert.equal(f.d.querySelector('[data-dashboard-section="catalog"]'), null);
        assert.equal(f.d.querySelector('.admin-dashboard-navigation'), null);
        assert.deepEqual([...f.d.querySelectorAll('a')].map(a => a.getAttribute('href')), ['/admin-web/official-recipes']);
        assert.doesNotMatch(f.d.body.textContent, /Promociones|Métodos de pago|Supermercados/);
    } finally { f.dom.window.close(); }
});

test('empty permissions state has no invented counts, actions or placeholder cards', async () => {
    const f = await fixture([]);
    try {
        assert.match(f.d.body.textContent, /No hay resúmenes disponibles para tus permisos actuales/);
        assert.equal(f.d.querySelector('[data-dashboard-card],a,button,[role="tab"]'), null);
        assert.deepEqual(f.calls, []);
    } finally { f.dom.window.close(); }
});

test('labels and descriptions are escaped and a missing destination never becomes a fake action', async () => {
    const f = await fixture([{
        key: 'safe', title: '<script>alert(1)</script>', description: 'A & B',
        cards: [card('one', '<img src=x onerror=alert(1)>', 0, '<strong>Descripción</strong>', null)],
    }]);
    try {
        assert.equal(f.d.querySelector('script,img,a'), null);
        assert.match(f.d.body.textContent, /<strong>Descripción<\/strong>/);
        assert.equal(f.d.querySelector('dd').textContent, '0');
    } finally { f.dom.window.close(); }
});

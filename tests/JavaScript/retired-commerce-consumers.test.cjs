'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { JSDOM } = require(require.resolve('jsdom', { paths: [path.join(__dirname, '../../mobile')] }));
const read = file => fs.readFileSync(path.join(__dirname, '../..', file), 'utf8');
const settle = async () => {
    for (let i = 0; i < 8; i++) await new Promise(resolve => setImmediate(resolve));
};

function makeDom(markup) {
    const dom = new JSDOM(markup, { url: 'https://qa.invalid/web/branches', runScripts: 'outside-only' });
    dom.window.fetch = () => { throw new Error('Network requests are forbidden in this fixture'); };
    dom.window.XMLHttpRequest = function () { throw new Error('Network requests are forbidden in this fixture'); };
    return dom;
}

test('branch detail keeps its products and location without requesting or rendering promotions', async t => {
    const parsed = new JSDOM(read('resources/views/web/user-screen.blade.php'));
    const markup = parsed.window.document.querySelector('[data-user-branches]').outerHTML;
    parsed.window.close();
    const dom = makeDom(markup);
    t.after(() => dom.window.close());
    const w = dom.window;
    const calls = [];
    const unexpected = [];
    const branch = {
        id: 12, name: 'Centro QA', address: 'Belgrano 123',
        chain: { id: 2, name: 'Supermercado QA' }, city: { id: 3, name: 'Rosario' },
        delivery_available: true, pickup_available: true,
        opening_hours: 'Lunes a viernes 8:00 a 20:00', latitude: '-32.95', longitude: '-60.64',
        promotions: [{ name: 'Promoción antigua que no debe aparecer' }],
    };
    const responses = {
        '/api/v1/cities': { data: [{ id: 3, name: 'Rosario' }] },
        '/api/v1/supermarkets': { data: [{ id: 2, name: 'Supermercado QA' }] },
        '/api/v1/supermarket-branches': { data: [branch] },
        '/api/v1/supermarket-branches/12': { data: branch },
        '/api/v1/supermarket-branches/12/products': {
            data: [{ product: { name: 'Arroz QA' }, current_price: { currency: 'ARS', price: '1500.00' } }],
        },
    };
    w.CCApi = { request: url => {
        calls.push(url);
        const response = responses[new URL(url, 'https://qa.invalid').pathname];
        if (response) return Promise.resolve(response);
        unexpected.push(url);
        return Promise.reject(new Error('Unexpected endpoint: ' + url));
    } };
    w.eval(read('public/js/user-branches.js'));
    await settle();
    w.document.querySelector('[data-branches-filter-city]').value = '3';
    w.document.querySelector('[data-branches-load]').click();
    await settle();
    assert.equal(w.document.querySelector('[data-branches-count]').textContent, '1 sucursal');
    w.document.querySelector('[data-branch-card="12"]').click();
    await settle();

    const detail = w.document.querySelector('[data-branches-detail]');
    for (const text of ['Centro QA', 'Supermercado QA', 'Rosario', 'Belgrano 123', 'Delivery', 'Pickup', 'Lunes a viernes', 'Arroz QA', 'ARS 1500.00']) {
        assert.ok(detail.textContent.includes(text), 'preserves ' + text);
    }
    assert.match(detail.querySelector('a').href, /mlat=-32\.95&mlon=-60\.64/);
    assert.equal(detail.querySelector('[data-branch-promotions-list]'), null);
    assert.doesNotMatch(detail.textContent, /promoci[oó]n|descuento/i);
    assert.deepEqual(unexpected, []);
    assert.equal(calls.length, 5);
    assert.ok(calls.includes('/api/v1/supermarket-branches/12/products?per_page=12'));
    assert.ok(calls.some(url => url.includes('city_id=3')));
    assert.equal(calls.some(url => /promotion|payment-method/.test(url)), false);
});

async function compareFixture(t, compareData, optimizeData) {
    const dom = makeDom('<div data-compare-panel></div>');
    t.after(() => dom.window.close());
    const w = dom.window;
    const calls = [];
    const unexpected = [];
    const base = '/api/v1/family-groups/8/shopping-lists/16';
    w.CCApi = { request: url => {
        calls.push(url);
        if (url === base + '/compare-supermarkets') return Promise.resolve({ data: compareData });
        if (url === base + '/optimize') return Promise.resolve({ data: optimizeData });
        unexpected.push(url);
        return Promise.reject(new Error('Unexpected endpoint: ' + url));
    } };
    w.eval(read('public/js/shopping-compare.js'));
    const panel = w.document.querySelector('[data-compare-panel]');
    w.ShoppingCompare.mount(panel, 8, 16);
    panel.querySelector('[data-compare-load]').click();
    await settle();
    return { panel, calls, unexpected, optimize: async () => {
        panel.querySelector('[data-optimize-load]').click();
        await settle();
    } };
}

test('legacy comparison ignores stale promotion fields and preserves backend totals and optimization savings', async t => {
    const h = await compareFixture(t, {
        items_total: 2,
        results: [{
            supermarket_id: 2, supermarket_name: 'Supermercado QA', branch: { id: 12, name: 'Centro QA' },
            items_found: 2, items_not_found: 0, total: '1500.00', subtotal: '1500.00',
            promotions_discount: '125.00', promotions: [{ name: 'Oferta obsoleta' }],
        }],
    }, {
        strategy: 'cheapest_single', savings: '100.00', total: '1400.00',
        routes: [{ supermarket_name: 'Supermercado QA', subtotal: '1400.00', items: [{ item_id: 21, item_name: 'Arroz QA', price: '1400.00' }] }],
    });
    assert.match(h.panel.textContent, /Supermercado QACentro QA/);
    assert.match(h.panel.textContent, /2\/2 ítems \(100%\)/);
    assert.match(h.panel.textContent, /Total\$1500\.00/);
    assert.match(h.panel.textContent, /Mejor precio/);
    assert.doesNotMatch(h.panel.textContent, /descuento|promo|oferta|subtotal|\$125\.00|\$1375\.00/i);
    await h.optimize();
    assert.match(h.panel.textContent, /Un solo supermercado/);
    assert.match(h.panel.textContent, /Arroz QA\$1400\.00/);
    assert.match(h.panel.textContent, /Ahorro estimado: \$100\.00/);
    assert.match(h.panel.textContent, /Total optimizado: \$1400\.00/);
    assert.doesNotMatch(h.panel.textContent, /ARS|USD/);
    assert.deepEqual(h.unexpected, []);
    assert.equal(h.calls.length, 2);
});

test('current comparison and optimization contracts render server totals without promotion adjustments', async t => {
    const firstBranch = { id: 12, name: 'Centro QA', chain: 'Mercado A' };
    const secondBranch = { id: 13, name: 'Norte QA', chain: 'Mercado B' };
    const h = await compareFixture(t, {
        branches: [
            { branch: { id: 14, name: 'Sin precios QA', chain: 'Mercado vacío' }, found_count: 0, missing_count: 2, total: 0, currency: 'ARS' },
            { branch: { id: 15, name: 'Parcial QA', chain: 'Mercado parcial' }, found_count: 1, missing_count: 1, total: 600, currency: 'ARS' },
            { branch: firstBranch, found_count: 2, missing_count: 0, total: 1800, currency: 'ARS', promotions: [{ name: 'Oferta vieja' }] },
            { branch: secondBranch, found_count: 2, missing_count: 0, total: 1500, currency: 'ARS', promotions_discount: 300 },
        ],
    }, {
        cheapest_complete: { branch: secondBranch, total: 1500, currency: 'ARS' },
        combined: {
            total: 1400, currency: 'ARS',
            items: [
                { item_id: 21, branch: firstBranch, product: { id: 101, name: 'Arroz QA' }, total: 900, currency: 'ARS' },
                { item_id: 22, branch: secondBranch, product: { id: 102, name: 'Aceite QA' }, total: 500, currency: 'ARS' },
            ],
        },
        estimated_savings: 100,
    });
    assert.match(h.panel.textContent, /Mercado ACentro QA/);
    assert.match(h.panel.textContent, /Mercado BNorte QAMejor precio/);
    assert.equal((h.panel.textContent.match(/Mejor precio/g) || []).length, 1);
    assert.match(h.panel.textContent, /0\/2 ítems \(0%\)/);
    assert.match(h.panel.textContent, /1\/2 ítems \(50%\)/);
    assert.equal((h.panel.textContent.match(/2\/2 ítems \(100%\)/g) || []).length, 2);
    assert.match(h.panel.textContent, /Total\$1800\.00 ARS/);
    assert.match(h.panel.textContent, /Total\$1500\.00 ARS/);
    assert.doesNotMatch(h.panel.textContent, /descuento|promo|oferta|\$1200\.00/i);
    await h.optimize();
    assert.match(h.panel.textContent, /Compra dividida/);
    assert.match(h.panel.textContent, /Mercado A · Centro QAArroz QA\$900\.00 ARS/);
    assert.match(h.panel.textContent, /Mercado B · Norte QAAceite QA\$500\.00 ARS/);
    assert.match(h.panel.textContent, /Ahorro estimado: \$100\.00 ARS/);
    assert.match(h.panel.textContent, /Total de artículos con precio: \$1400\.00 ARS/);
    assert.match(h.panel.textContent, /Incluye únicamente artículos con precio\. Revisá la cobertura de cada sucursal en la comparación\./);
    assert.doesNotMatch(h.panel.textContent, /Total optimizado/);
    assert.doesNotMatch(h.panel.textContent, /descuento|promo|oferta/i);
    assert.deepEqual(h.unexpected, []);
    assert.equal(h.calls.length, 2);
});

test('complete quotes in different currencies have no winner and a mixed combined result has no comparable total', async t => {
    const branch = { id: 12, name: 'Centro QA', chain: 'Mercado QA' };
    const otherBranch = { id: 13, name: 'Norte QA', chain: 'Otro mercado QA' };
    const h = await compareFixture(t, { branches: [
        { branch, total: 1500, currency: 'ARS', found_count: 2, missing_count: 0 },
        { branch: otherBranch, total: 2, currency: 'USD', found_count: 2, missing_count: 0 },
    ] }, {
        cheapest_complete: null,
        combined: {
            total: null, currency: 'ARS',
            items: [
                { item_id: 21, branch, product: { name: 'Arroz QA' }, total: 1500, currency: 'ARS' },
                { item_id: 22, branch: otherBranch, product: { name: 'Aceite QA' }, total: 2, currency: 'USD' },
            ],
        },
        estimated_savings: null,
    });
    assert.equal((h.panel.textContent.match(/2\/2 ítems \(100%\)/g) || []).length, 2);
    assert.match(h.panel.textContent, /Total\$1500\.00 ARS/);
    assert.match(h.panel.textContent, /Total\$2\.00 USD/);
    assert.doesNotMatch(h.panel.textContent, /Mejor precio/);
    await h.optimize();
    assert.match(h.panel.textContent, /Arroz QA\$1500\.00 ARS/);
    assert.match(h.panel.textContent, /Aceite QA\$2\.00 USD/);
    assert.match(h.panel.textContent, /No hay un total comparable para estos artículos\./);
    assert.doesNotMatch(h.panel.textContent, /Total optimizado|Total de artículos con precio|Ahorro estimado|\$1502\.00/);
    assert.deepEqual(h.unexpected, []);
});

for (const format of ['canonical', 'legacy']) {
    test(format + ' complete quotes with known and unknown currencies have no global best-price badge', async t => {
        const first = { id: 12, name: 'Centro QA', chain: 'Mercado QA' };
        const second = { id: 13, name: 'Norte QA', chain: 'Otro mercado QA' };
        const quotes = [
            { branch: first, total: 1500, currency: 'ARS', found_count: 2, missing_count: 0 },
            { branch: second, total: 2, found_count: 2, missing_count: 0 },
        ];
        const data = format === 'canonical' ? { branches: quotes } : {
            items_total: 2,
            results: quotes.map(row => ({ ...row, supermarket_name: row.branch.chain, items_found: row.found_count, items_not_found: row.missing_count })),
        };
        const h = await compareFixture(t, data, null);
        assert.match(h.panel.textContent, /Total\$1500\.00 ARS/);
        assert.match(h.panel.textContent, /Total\$2\.00/);
        assert.doesNotMatch(h.panel.textContent, /Mejor precio|\$2\.00 ARS/);
        assert.deepEqual(h.unexpected, []);
    });
}

test('combined result without coverage metadata labels a partial total only as priced articles', async t => {
    const branch = { id: 12, name: 'Centro QA', chain: 'Mercado QA' };
    const h = await compareFixture(t, {
        branches: [{ branch, total: 1000, found_count: 1, missing_count: 1 }],
    }, {
        cheapest_complete: null,
        combined: {
            total: 1000, currency: 'ARS',
            items: [{ item_id: 21, branch, product: { name: 'Arroz QA' }, total: 1000, currency: 'ARS' }],
        },
        estimated_savings: null,
    });
    await h.optimize();
    const optimization = h.panel.lastElementChild.textContent;
    assert.match(optimization, /Arroz QA\$1000\.00/);
    assert.match(optimization, /Total de artículos con precio: \$1000\.00/);
    assert.match(optimization, /Incluye únicamente artículos con precio\. Revisá la cobertura de cada sucursal en la comparación\./);
    assert.doesNotMatch(optimization, /Total optimizado|100%|compra completa|faltan|sin precio: \d/i);
    assert.deepEqual(h.unexpected, []);
});

test('empty combined result displays no priced articles instead of a zero purchase total', async t => {
    const branch = { id: 12, name: 'Centro QA', chain: 'Mercado QA' };
    const h = await compareFixture(t, {
        branches: [{ branch, total: 0, found_count: 0, missing_count: 2 }],
    }, {
        cheapest_complete: null,
        combined: { total: 0, currency: 'ARS', items: [] },
        estimated_savings: null,
    });
    await h.optimize();
    const optimization = h.panel.lastElementChild.textContent;
    assert.match(optimization, /No hay artículos con precio para optimizar\./);
    assert.doesNotMatch(optimization, /Total|\$0\.00|Ahorro estimado|Compra dividida|Un solo supermercado/);
    assert.deepEqual(h.unexpected, []);
});

const test = require('node:test');
const assert = require('node:assert/strict');
const path = require('node:path');
const { execFileSync } = require('node:child_process');
const { JSDOM } = require('jsdom');
const php = process.env.PHP_BINARY || 'php';
const fixture = JSON.parse(execFileSync(php, [path.join(__dirname, 'fixtures/render-admin-labels.php')], { encoding: 'utf8', maxBuffer: 4 * 1024 * 1024 }));
const documentFor = screen => new JSDOM(fixture.rendered[screen]).window.document;

test('all 39 active screens expose Spanish metadata and explicit metric labels, including zero counts', () => {
    assert.equal(Object.keys(fixture.screens).length, 39);
    assert.equal(Object.keys(fixture.rendered).length, 38);
    const english = /\b(?:scraping|scrapeados?|jobs?|tags?|feature flags?|settings|provider|dashboard|refresh|password|username|barcode|preview|parseo|parseadas|ABM)\b/i;
    for (const [key, screen] of Object.entries(fixture.screens)) {
        assert.doesNotMatch([screen.title, screen.module, screen.description, screen.primary, screen.secondary, ...screen.panels].join(' '), english, key);
        if (key === 'dashboard') continue;
        const d = documentFor(key);
        const actual = [...d.querySelectorAll('.metric span')].map(el => el.textContent.trim());
        assert.deepEqual(actual, screen.metrics.map(metric => fixture.labels[metric]), key);
        for (const metric of screen.metrics) assert.ok(fixture.labels[metric] && fixture.labels[metric] !== metric.replaceAll('_', ' '), key + ': ' + metric);
        for (const value of d.querySelectorAll('.metric strong')) assert.equal(value.textContent.trim(), '0');
        assert.doesNotMatch(d.body.textContent, english, key);
    }
});

test('source, execution and alert controls translate labels while preserving submitted enum values', () => {
    const d = documentFor('supermarket-scraping');
    const source = d.querySelector('[data-scraping-source-form]');
    assert.ok(source);
    const types = source.querySelector('select[name="type"]');
    assert.deepEqual([...types.options].map(o => [o.value, o.textContent]), [
        ['web_scraper', 'Importación web'], ['api', 'Interfaz de datos (API)'], ['feed', 'Fuente de datos'],
    ]);
    types.value = 'web_scraper';
    assert.equal(new d.defaultView.FormData(source).get('type'), 'web_scraper');
    const levels = d.querySelector('[data-scraping-log-level]');
    assert.deepEqual([...levels.options].map(o => [o.value, o.textContent]), [
        ['', 'Todos los niveles'], ['debug', 'Depuración'], ['info', 'Información'], ['warning', 'Advertencia'], ['error', 'Error'],
    ]);
    assert.equal(d.querySelector('option[value="cancel_requested"]').textContent, 'Cancelación solicitada');
    const alerts = documentFor('scraping-alerts');
    assert.equal(alerts.querySelector('option[value="parser_error"]').textContent, 'Error al interpretar datos');
    assert.equal(alerts.querySelector('option[value="network_error"]').textContent, 'Error de conexión');
    const imports = documentFor('imported-recipes');
    assert.equal(imports.querySelector('option[value="parsed"]').textContent, 'Analizadas');
    assert.equal(imports.querySelector('[name="raw_ingredients_json"]').name, 'raw_ingredients_json');
});

test('navigation translates labels without broadening the allowed destinations', () => {
    const html = execFileSync(php, [path.join(__dirname, 'fixtures/render-navbar.php'), JSON.stringify({ permissions: ['web.admin.dashboard', 'web.admin.supermarket-scraping', 'web.admin.feature-flags', 'web.admin.settings', 'web.admin.recipe-tags'] })], { encoding: 'utf8' });
    const d = new JSDOM(html).window.document;
    assert.deepEqual([...d.querySelectorAll('#portalMenuAdmin a')].map(a => [a.getAttribute('href'), a.textContent]), [
        ['/admin-web', 'Resumen'], ['/admin-web/supermarket-scraping', 'Importación web de productos'], ['/admin-web/recipe-tags', 'Etiquetas de recetas'], ['/admin-web/feature-flags', 'Funciones'], ['/admin-web/settings', 'Configuración'],
    ]);
    assert.equal(d.querySelector('#portalDropdownAdmin').getAttribute('aria-controls'), 'portalMenuAdmin');
});

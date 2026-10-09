const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { execFileSync } = require('node:child_process');
const { JSDOM } = require('jsdom');
const base = path.join(__dirname, '../..');
const read = file => fs.readFileSync(path.join(base, file), 'utf8');

test('actual Blade navigation omits retired commerce modules for every permission scope', () => {
    for (const permissions of [['*'], ['catalog.manage', 'web.admin.dashboard', 'web.admin.products'], ['web.user.dashboard', 'web.user.purchases', 'web.user.profile-objectives']]) {
        const html = execFileSync(process.env.PHP_BINARY || 'php', [path.join(__dirname, 'fixtures/render-navbar.php'), JSON.stringify({ permissions })], { encoding: 'utf8' });
        const dom = new JSDOM(html);
        const links = [...dom.window.document.querySelectorAll('a[href]')].map(a => a.getAttribute('href'));
        assert.equal(links.some(href => /\/(promotions|payment-methods)(?:[/?#]|$)/.test(href)), false);
        if (permissions.includes('*')) {
            for (const href of ['/admin-web/products', '/admin-web/ingredients', '/admin-web/supermarket-scraping']) assert.ok(links.includes(href), href + ' remains available');
        }
        dom.window.close();
    }
});

for (const screen of ['shopping-list', 'shopping-session', 'purchases', 'supermarkets', 'branches']) {
    test(screen + ': actual section navigation preserves shopping and history without payment setup', async () => {
        const dom = new JSDOM('<section class="hero"></section>', { url: 'https://qa.invalid/web/' + screen, runScripts: 'outside-only' });
        const w = dom.window;
        await new Promise(resolve => w.document.addEventListener('DOMContentLoaded', resolve, { once: true }));
        w.eval(read('public/js/panel-ui.js'));
        w.document.dispatchEvent(new w.Event('DOMContentLoaded'));
        const links = [...w.document.querySelectorAll('.module-links a')].map(a => a.getAttribute('href'));
        assert.deepEqual(links, ['/web/shopping-list', '/web/shopping-session', '/web/purchases', '/web/supermarkets', '/web/branches']);
        assert.equal(w.document.querySelector('[aria-current="page"]').getAttribute('href'), '/web/' + screen);
        dom.window.close();
    });
}

test('retired editors cannot be rendered or loaded from current web templates', () => {
    const admin = new JSDOM(read('resources/views/web/admin-screen.blade.php'));
    const user = new JSDOM(read('resources/views/web/user-screen.blade.php'));
    assert.equal(admin.window.document.querySelector('[data-admin-promotions],[data-admin-payment-methods]'), null);
    assert.equal(user.window.document.querySelector('[data-user-payment-methods],[name="payment_method_id"]'), null);
    assert.ok(user.window.document.querySelector('[data-user-purchases]'), 'purchase history markup is retained');
    assert.doesNotMatch(read('resources/views/layouts/admin-web.blade.php'), /admin-(?:promotions|payment-methods)\.js/);
    assert.doesNotMatch(read('resources/views/partials/panel-assets.blade.php'), /user-payment-methods/);
    for (const file of ['admin-promotions.js', 'admin-payment-methods.js', 'user-payment-methods.js']) {
        assert.equal(fs.existsSync(path.join(base, 'public/js', file)), false, 'retired executable asset is removed: ' + file);
    }
    admin.window.close(); user.window.close();
});

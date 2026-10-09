const assert = require('node:assert/strict');
const test = require('node:test');
const fs = require('node:fs');
const path = require('node:path');
const { execFileSync } = require('node:child_process');
const { JSDOM } = require('jsdom');
const base = path.join(__dirname, '../..');
const controller = fs.readFileSync(path.join(base, 'public/js/panel-menu.js'), 'utf8');
function fixture(options = {}) {
    const html = execFileSync(process.env.PHP_BINARY || 'php', [path.join(__dirname, 'fixtures/render-navbar.php'), JSON.stringify(options)], { encoding: 'utf8' });
    const dom = new JSDOM(html, { url: 'https://qa.invalid/admin-web/products', runScripts: 'outside-only' });
    dom.window.eval(controller);
    return dom;
}
function key(window, element, value) {
    element.dispatchEvent(new window.KeyboardEvent('keydown', { key: value, bubbles: true, cancelable: true }));
}
test('real Blade preserves separate catalog, recipe, user and guest permissions', () => {
    for (const [permissions, expected, absent] of [
        [['web.admin.dashboard', 'web.admin.products', 'catalog.manage'], '/admin-web/products', '/admin-web/official-recipes'],
        [['web.admin.dashboard', 'web.admin.official-recipes', 'recipes.manage'], '/admin-web/official-recipes', '/admin-web/products'],
        [['web.user.dashboard', 'web.user.stock', 'web.user.profile-objectives'], '/web/stock', '/admin-web'],
    ]) {
        const dom = fixture({ permissions });
        const d = dom.window.document;
        assert.ok(d.querySelector(`a[href="${expected}"]`));
        assert.equal(d.querySelector(`a[href="${absent}"]`), null);
        assert.ok(d.querySelector('[data-api-logout][data-fallback-form="#logout-form-navbar"]'));
        assert.equal(d.querySelector('#logout-form-navbar').method, 'post');
        dom.window.close();
    }
    const guest = fixture({ guest: true });
    assert.equal(guest.window.document.querySelector('[data-toggle="dropdown"]'), null);
    assert.ok(guest.window.document.querySelector('[data-mobile-entry="register"]'));
    guest.window.close();
});
test('administration and account share native controls; current page remains identified', () => {
    const dom = fixture();
    const d = dom.window.document;
    const toggles = [...d.querySelectorAll('[data-toggle="dropdown"]')];
    assert.equal(toggles.length, 2);
    for (const toggle of toggles) {
        assert.equal(toggle.tagName, 'BUTTON');
        assert.equal(toggle.type, 'button');
        assert.ok(toggle.classList.contains('navbar-menu-toggle'));
        assert.equal(d.getElementById(toggle.getAttribute('aria-controls')).getAttribute('aria-labelledby'), toggle.id);
    }
    assert.equal(d.querySelector('a[aria-current="page"]').getAttribute('href'), '/admin-web/products');
    assert.equal(d.querySelector('#portalDropdownAdmin').textContent.trim(), 'Administración');
    dom.window.close();
});
test('one menu opens at a time and repeated loading or legacy delegated handlers cannot double-toggle', () => {
    const dom = fixture();
    const w = dom.window, d = w.document;
    let legacy = 0;
    d.addEventListener('click', e => { if (e.target.closest('[data-toggle="dropdown"]')) legacy++; });
    w.eval(controller);
    const admin = d.getElementById('portalDropdownAdmin'), account = d.getElementById('userNavbarMenu');
    admin.click();
    assert.equal(admin.getAttribute('aria-expanded'), 'true');
    account.click();
    assert.equal(admin.getAttribute('aria-expanded'), 'false');
    assert.equal(account.getAttribute('aria-expanded'), 'true');
    assert.equal(legacy, 0);
    account.click();
    assert.equal(account.getAttribute('aria-expanded'), 'false');
    dom.window.close();
});
test('keyboard opens, traverses and closes with focus restoration; tab focus closes previous menu', () => {
    const dom = fixture();
    const w = dom.window, d = w.document;
    const toggle = d.getElementById('portalDropdownAdmin');
    const links = [...d.querySelectorAll('#portalMenuAdmin a')];
    toggle.focus(); key(w, toggle, 'ArrowDown');
    assert.equal(d.activeElement, links[0]);
    key(w, links[0], 'End'); assert.equal(d.activeElement, links.at(-1));
    key(w, links.at(-1), 'ArrowDown'); assert.equal(d.activeElement, links[0]);
    key(w, links[0], 'Home'); assert.equal(d.activeElement, links[0]);
    key(w, links[0], 'Escape');
    assert.equal(d.activeElement, toggle);
    assert.equal(toggle.getAttribute('aria-expanded'), 'false');
    key(w, toggle, ' '); assert.equal(toggle.getAttribute('aria-expanded'), 'true');
    d.getElementById('userNavbarMenu').focus();
    assert.equal(toggle.getAttribute('aria-expanded'), 'false');
    key(w, d.activeElement, 'Enter');
    assert.equal(d.activeElement.getAttribute('aria-expanded'), 'true');
    d.getElementById('outside').focus();
    assert.equal(d.querySelector('.dropdown.show'), null);
    dom.window.close();
});
test('outside click, navigation lifecycle and logout close without consuming link action', () => {
    const dom = fixture();
    const w = dom.window, d = w.document, toggle = d.getElementById('userNavbarMenu');
    for (const event of ['turbo:before-render', 'turbo:before-cache']) {
        toggle.click(); d.dispatchEvent(new w.Event(event));
        assert.equal(toggle.getAttribute('aria-expanded'), 'false');
    }
    toggle.click(); d.getElementById('outside').click();
    assert.equal(toggle.getAttribute('aria-expanded'), 'false');
    let logoutCalls = 0;
    d.addEventListener('click', e => {
        if (e.target.closest('[data-api-logout]')) {
            assert.equal(e.defaultPrevented, false);
            e.preventDefault(); logoutCalls++;
        }
    });
    toggle.click(); d.querySelector('[data-api-logout]').click();
    assert.equal(logoutCalls, 1);
    assert.equal(toggle.getAttribute('aria-expanded'), 'false');
    dom.window.close();
});

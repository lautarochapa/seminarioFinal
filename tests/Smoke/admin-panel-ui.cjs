const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { JSDOM } = require(require.resolve('jsdom', { paths: [path.join(__dirname, '../../mobile')] }));
const shared = fs.readFileSync(path.join(__dirname, '../../public/js/panel-ui.js'), 'utf8');
const adapter = fs.readFileSync(path.join(__dirname, '../../public/js/admin-panel-ui.js'), 'utf8');
const blade = fs.readFileSync(path.join(__dirname, '../../resources/views/web/admin-screen.blade.php'), 'utf8');
// Dashboard is a server-rendered summary, covered by admin-dashboard.test.cjs.
const branches = [...blade.matchAll(/@(?:if|elseif)\(\$screenKey === '([^']+)'\)/g)].filter(branch => branch[1] !== 'dashboard');

function boot(html, screen, hash = '') {
    const dom = new JSDOM('<body><button id="return-focus">Volver</button><section class="hero"><a href="#" data-screen-primary-action>Crear</a><a href="#" data-screen-secondary-action>Secundaria</a></section><div data-admin-ui data-admin-screen="' + screen + '">' + html + '</div></body>', { url: 'http://localhost/admin-web/' + screen + hash, runScripts: 'outside-only' });
    const w = dom.window;
    w.HTMLDialogElement.prototype.showModal = function () { this.setAttribute('open', ''); };
    w.HTMLDialogElement.prototype.close = function () { this.removeAttribute('open'); this.dispatchEvent(new w.Event('close')); };
    const root = w.document.querySelector('[data-admin-ui]').firstElementChild;
    const forms = [...root.querySelectorAll('form')];
    const controls = [...root.querySelectorAll('input,select,textarea,button')];
    let submits = 0;
    forms.forEach(form => form.addEventListener('submit', event => { event.preventDefault(); submits++; }));
    w.eval(shared); w.eval(adapter); w.document.dispatchEvent(new w.Event('DOMContentLoaded'));
    return { dom, w, root, forms, controls, submits: () => submits };
}

(async () => {
    let screenCount = 0;
    let formCount = 0;
    for (const [index, branch] of branches.entries()) {
        const screen = branch[1];
        let html = blade.slice(branch.index + branch[0].length, branches[index + 1]?.index ?? blade.lastIndexOf('@else'));
        const { dom, w, root, forms, controls, submits } = boot(html, screen);
        const tabs = [...root.querySelectorAll('[role="tab"]')];
        assert.ok(tabs.length, screen + ': has accessible sections');
        const ids = [...root.querySelectorAll('[id]')].map(node => node.id);
        assert.equal(new Set(ids).size, ids.length, screen + ': no duplicate IDs');
        for (const control of controls) { assert.ok(root.contains(control), screen + ': original control and delegation preserved'); }
        for (const form of forms) {
            const dialog = form.closest('dialog');
            assert.ok(dialog, screen + ': static form is in a dialog');
            assert.ok(root.contains(dialog), screen + ': dialog belongs to feature root');
            const returnFocus = w.document.getElementById('return-focus'); returnFocus.focus();
            w.CCUI.reveal(form);
            assert.ok(dialog.open, screen + ': explicit edit hook opens dialog');
            assert.ok(w.document.getElementById(dialog.getAttribute('aria-labelledby')), 'dialog has accessible name');
            const before = submits();
            form.dispatchEvent(new w.Event('submit', { bubbles: true, cancelable: true }));
            assert.equal(submits(), before + 1, screen + ': bound submit handler preserved');
            dialog.querySelector('.dialog-close').click();
            assert.ok(!dialog.open); assert.equal(w.document.activeElement, returnFocus, 'closing returns focus');
            w.CCUI.reveal(form);
            form.dispatchEvent(new w.Event('invalid', { bubbles: false, cancelable: true }));
            assert.ok(dialog.open, 'invalid field remains visible');
            w.CCUI.saved(form, 'Guardado de prueba');
            assert.ok(!dialog.open, 'successful explicit hook closes dialog');
        }
        for (const bar of root.querySelectorAll('[role="tablist"]')) {
            const buttons = [...bar.querySelectorAll('[role="tab"]')];
            buttons[buttons.length - 1].click();
            assert.equal(buttons.filter(button => button.getAttribute('aria-selected') === 'true').length, 1, screen + ': one selected tab');
            buttons[buttons.length - 1].dispatchEvent(new w.KeyboardEvent('keydown', { key: 'Home', bubbles: true }));
            assert.equal(buttons[0].getAttribute('aria-selected'), 'true', 'Home selects first tab');
            buttons[0].dispatchEvent(new w.KeyboardEvent('keydown', { key: 'End', bubbles: true }));
            assert.equal(buttons[buttons.length - 1].getAttribute('aria-selected'), 'true', 'End selects last tab');
            for (const button of buttons) { assert.ok(w.document.getElementById(button.getAttribute('aria-controls')), 'tab controls existing panel'); }
        }
        const alert = [...root.querySelectorAll('.alert')].find(node => !node.closest('dialog') && !node.hasAttribute('data-ui-notice'));
        if (alert && forms.length) {
            const form = forms[0]; const dialog = form.closest('dialog'); w.CCUI.reveal(form);
            alert.className = 'alert alert-danger'; alert.style.display = 'block'; alert.textContent = 'Validación visible';
            await new Promise(resolve => w.setTimeout(resolve, 0));
            assert.ok(dialog.textContent.includes('Validación visible'), screen + ': API validation is visible in dialog');
            dialog.dispatchEvent(new w.Event('cancel', { cancelable: true })); assert.ok(!dialog.open, 'Escape/cancel closes dialog');
        }
        if (screen === 'scraped-products') {
            assert.equal(new Set(forms.map(form => form.closest('dialog'))).size, 1, 'candidate review forms keep shared context');
            assert.ok(forms[0].closest('dialog').querySelector('[data-candidate-detail]'));
        }
        if (screen === 'imported-recipes') {
            assert.equal(new Set(forms.map(form => form.closest('dialog'))).size, 1, 'recipe review stays together');
            assert.ok(forms[0].closest('dialog').querySelector('[data-import-candidates-apply-suggestions]'));
        }
        if (screen === 'branches') {
            assert.equal(root.querySelector('[data-branches-map-preview]').closest('dialog'), forms[0].closest('dialog'), 'map preview stays with its original editor');
        }
        if (screen === 'health-preferences') {
            assert.ok(root.querySelector('aside.panel').hidden, 'native tab screen hides extracted empty aside');
        }
        if (screen === 'products') {
            assert.equal(tabs.length, 6, 'products keep six usable sections without an empty editor tab');
            assert.ok(root.querySelector('[data-product-images]').closest('[role="tabpanel"]'), 'dynamic image area remains reachable');
            const productDialog = forms[0].closest('dialog');
            assert.ok(root.querySelector('[aria-controls="' + productDialog.id + '"]').hidden, 'existing hero handler keeps only one create action');
            assert.ok(w.document.querySelector('[data-screen-secondary-action]').hidden, 'unavailable import placeholder is hidden');
        }
        if (screen === 'recipe-scraping') { assert.ok(!w.document.querySelector('[data-screen-secondary-action]').hidden, 'existing job-section action is preserved'); }
        if (['users', 'branches', 'supermarket-scraping', 'recipe-import', 'ai-foundation'].includes(screen)) {
            const primary = w.document.querySelector('[data-screen-primary-action]');
            assert.ok(!primary.hidden, 'create hero has a concrete action');
            const before = submits(); primary.click();
            assert.equal(root.querySelectorAll('dialog[open]').length, 1, 'hero opens exactly one editor without submitting');
            assert.equal(submits(), before);
            if (screen === 'supermarket-scraping') {
                assert.ok(root.querySelector('[data-scraping-job-form]').closest('dialog').open, 'execute scraping opens job form, not source form');
            }
            assert.ok(w.document.querySelector('[data-screen-secondary-action]').hidden, 'unimplemented secondary link is hidden');
            root.querySelector('dialog[open]').close();
        }
        if (screen === 'users') {
            const dialog = forms[0].closest('dialog'); w.CCUI.reveal(forms[0]);
            dialog.addEventListener('cancel', event => event.preventDefault(), { capture: true, once: true });
            dialog.dispatchEvent(new w.Event('cancel', { cancelable: true }));
            assert.ok(dialog.open, 'dialog respects feature cancellation guard');
            dialog.close();
        }
        if (screen === 'audit') {
            const accessTab = root.querySelectorAll('[data-audit-tab]')[1];
            const restored = boot(html, screen, '#' + accessTab.getAttribute('aria-controls'));
            const restoredTab = restored.root.querySelectorAll('[data-audit-tab]')[1];
            let nativeLoads = 0;
            // Real feature handlers bind after the adapter's DOMContentLoaded.
            restoredTab.addEventListener('click', () => { nativeLoads++; });
            await new Promise(resolve => restored.w.setTimeout(resolve, 0));
            assert.equal(nativeLoads, 1, 'restored native audit tab invokes its data-loading handler once');
            assert.equal(restoredTab.getAttribute('aria-selected'), 'true');
            restored.dom.window.close();
        }
        // Initialization is idempotent even if a fallback DOMContentLoaded repeats.
        const dialogCount = root.querySelectorAll('dialog').length;
        w.document.dispatchEvent(new w.Event('DOMContentLoaded'));
        assert.equal(root.querySelectorAll('dialog').length, dialogCount);
        console.log('PASS ' + screen + ': ' + tabs.length + ' tabs, ' + forms.length + ' original forms');
        formCount += forms.length; screenCount++; dom.window.close();
    }
    assert.ok(screenCount >= 35, 'covers every explicit admin screen in template');
    console.log('PASS ' + screenCount + ' admin screens / ' + formCount + ' forms');
})().catch(error => { console.error(error); process.exitCode = 1; });

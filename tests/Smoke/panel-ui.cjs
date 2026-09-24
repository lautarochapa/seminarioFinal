const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { JSDOM } = require(require.resolve('jsdom', { paths: [path.join(__dirname, '../../mobile')] }));
const source = fs.readFileSync(path.join(__dirname, '../../public/js/panel-ui.js'), 'utf8');
const template = fs.readFileSync(path.join(__dirname, '../../resources/views/web/user-screen.blade.php'), 'utf8');
const parsed = new JSDOM(template);
const cases = [
 ['.workspace-profile', 6, 0],
 ['[data-user-stock-locations]', 5, 4],
 ['[data-user-meal-plans]', 5, 4],
 ['[data-user-shopping-lists]', 2, 4],
 ['[data-family-groups]', 3, 5],
 ['[data-user-recipes]', 0, 1],
 ['[data-user-budget]', 0, 3],
 ['[data-user-purchases]', 0, 1]
];
for (const [selector, tabCount, modalCount] of cases) {
 const root = parsed.window.document.querySelector(selector);
 assert.ok(root, selector);
 const dom = new JSDOM('<body>' + root.outerHTML + '</body>', { url:'http://localhost/web/stock', runScripts:'outside-only' });
 const w = dom.window;
 w.HTMLDialogElement.prototype.showModal = function() { this.setAttribute('open', ''); };
 w.HTMLDialogElement.prototype.close = function() { this.removeAttribute('open'); this.dispatchEvent(new w.Event('close')); };
 w.eval(source); w.document.dispatchEvent(new w.Event('DOMContentLoaded'));
 const tabs = [...w.document.querySelectorAll('[role=tab]')];
 assert.equal(tabs.length, tabCount, selector + ' tabs');
 assert.equal(w.document.querySelectorAll('dialog').length, modalCount, selector + ' dialogs');
 if (tabCount) {
   tabs[tabCount - 1].click();
   assert.equal(w.document.querySelectorAll('[role=tabpanel]:not([hidden])').length, 1);
   tabs[tabCount - 1].dispatchEvent(new w.KeyboardEvent('keydown', {key:'Home', bubbles:true}));
   assert.equal(tabs[0].getAttribute('aria-selected'), 'true');
   w.CCUI.reveal(w.document.getElementById(tabs[1].getAttribute('aria-controls')));
   assert.equal(tabs[1].getAttribute('aria-selected'), 'true');
 }
 for (const dialog of w.document.querySelectorAll('dialog')) {
   assert.ok(dialog.querySelector('form'), 'Dialog keeps original form: ' + selector);
   assert.ok(dialog.closest(selector), 'Event delegation remains inside feature root');
   const form = dialog.querySelector('form');
   w.CCUI.reveal(form); assert.ok(dialog.open);
   dialog.querySelector('.dialog-close').click(); assert.ok(!dialog.open);
   w.CCUI.reveal(form);
   w.CCUI.saved(form, 'Cambios guardados.');
   assert.ok(!dialog.open, 'Successful save closes the dialog');
   assert.equal(w.document.querySelector('[data-ui-notice]').textContent, 'Cambios guardados.');
 }
 assert.equal(w.document.querySelectorAll('aside[hidden] form').length, 0, 'No inaccessible forms in ' + selector);
 console.log('PASS ' + selector + ': tabs, keyboard, dialogs and original form ownership');
 w.close();
}
parsed.window.close();

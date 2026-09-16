const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const source = fs.readFileSync(path.join(__dirname, '../../public/js/user-budget.js'), 'utf8');
const ending = '})(window, document);';
assert.ok(source.includes(ending));
const context = {
    window: {},
    document: { addEventListener() {} },
};
vm.runInNewContext(source.replace(ending,
    'window.testBudget = {state, buildPayload, fillForm, renderCurrentBudget, renderSummaryPanel, renderProjection};' + ending), context);
const ui = context.window.testBudget;
const form = { elements: {
    id: {value: ''}, month: {value: '9'}, year: {value: '2026'},
    amount: {value: '10000'}, currency: {value: 'ARS'},
}};
const payload = JSON.parse(JSON.stringify(ui.buildPayload(form)));
assert.deepEqual(payload, {month: 9, year: 2026, total_amount: 10000, currency: 'ARS'});
const nodes = {};
const root = {querySelector(selector) {
    if (selector === '[data-budget-form]') return form;
    return nodes[selector] ||= {innerHTML: '', textContent: '', style: {}};
}};
ui.state.currentBudget = {id: 1, year: 2026, month: 9, total_amount: 10000, used_amount: 1600, available_amount: 8400, currency: 'ARS'};
ui.renderCurrentBudget(root);
assert.match(nodes['[data-budget-current]'].innerHTML, /10\.000,00/);
assert.match(nodes['[data-budget-current]'].innerHTML, /1\.600,00/);
assert.match(nodes['[data-budget-current]'].innerHTML, /8\.400,00/);
ui.fillForm(root, ui.state.currentBudget);
assert.equal(form.elements.amount.value, 10000);
ui.state.summaryBudgetId = 1;
ui.state.summary = {year: 2026, month: 9, total_amount: 10000, spent_amount: 1600, available_amount: 8400};
ui.state.projection = {total_amount: 10000, spent_amount: 1600, planned_amount: 2000, available_projected: 6400};
ui.renderSummaryPanel(root);
ui.renderProjection(root);
const html = Object.values(nodes).map(n => n.innerHTML).join('\n');
assert.match(html, /2\.000,00/);
assert.match(html, /6\.400,00/);
assert.match(html, /3\.600,00/);
ui.state.projection = {total_amount: 10000, spent_amount: 11000, planned_amount: null, available_projected: 0};
ui.renderProjection(root);
assert.match(nodes['[data-budget-projection-content]'].innerHTML, /superan el presupuesto/);
console.log('OK: presupuesto envia y representa el contrato real de la API.');

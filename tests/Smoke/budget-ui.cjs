const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const source = fs.readFileSync(path.join(__dirname, '../../public/js/user-budget.js'), 'utf8');
const ending = '})(window, document);';
assert.ok(source.includes(ending));
const context = {
    window: {},
    URLSearchParams,
    document: { addEventListener() {} },
};
vm.runInNewContext(source.replace(ending,
    'window.testBudget = {state, buildPayload, fillForm, renderList, renderCurrentBudget, renderSummaryPanel, renderProjection, saveAdjustment};' + ending), context);
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
ui.state.currentGroupId = 1;
ui.state.budgets = [ui.state.currentBudget, {id: 2, year: 2025, month: 1, total_amount: 200, used_amount: 0, available_amount: 200}];
ui.renderList(root);
assert.match(nodes['[data-budget-body]'].innerHTML, /1\.600,00/);
assert.match(nodes['[data-budget-body]'].innerHTML, /8\.400,00/);
assert.match(nodes['[data-budget-body]'].innerHTML, /\$0,00/);
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
ui.state.currentBudget = {id: 1, total_amount: 10000, used_amount: -125.5, available_amount: 10125.5};
ui.renderCurrentBudget(root);
assert.doesNotMatch(nodes['[data-budget-current]'].innerHTML, /width:-/);
assert.match(nodes['[data-budget-current]'].innerHTML, /10\.125,50/);
console.log('OK: presupuesto envia y representa el contrato real de la API.');

async function testAdjustmentRefresh() {
    const calls = [];
    let fail = false;
    let resets = 0;
    const adjustment = {elements: {amount: {value: '-100'}, description: {value: 'Gasto QA'}}, reset() { resets++; }};
    const budget = {id: 1, month: 9, year: 2026, total_amount: 10000, used_amount: 2200, available_amount: 7800};
    context.window.CCApi = {request(url, options) {
        calls.push({url, options});
        if (options?.method === 'POST') return fail ? Promise.reject(new Error('Fallo controlado')) : Promise.resolve({data: {id: 8}});
        if (url.endsWith('/current')) return Promise.resolve({data: budget});
        if (url.includes('/budgets?page=')) return Promise.resolve({data: [budget]});
        if (url.includes('/movements?')) return Promise.resolve({data: []});
        return Promise.resolve({data: {total_amount: 10000, spent_amount: 2200, available_amount: 7800, available_projected: 7800}});
    }};
    ui.state.movsBudgetId = 1;
    ui.state.summaryBudgetId = 1;
    ui.saveAdjustment(root, adjustment);
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(resets, 1);
    assert.equal(calls.length, 6, 'Guardar debe refrescar movimientos, listado, actual, resumen y proyeccion.');
    assert.equal(calls[0].options.body.amount, -100);
    assert.equal(ui.state.currentBudget.available_amount, 7800);
    assert.equal(ui.state.summary.available_amount, 7800);
    assert.match(nodes['[data-movs-adj-message]'].textContent, /guardado/);
    assert.equal(nodes['[data-movs-adj-save]'].disabled, false);
    calls.length = 0;
    ui.state.summaryBudgetId = 2;
    ui.saveAdjustment(root, adjustment);
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(calls.length, 4, 'No cambiar el resumen de otro presupuesto.');
    assert.equal(ui.state.summaryBudgetId, 2);
    calls.length = 0;
    fail = true;
    ui.saveAdjustment(root, adjustment);
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(calls.length, 1);
    assert.equal(resets, 2, 'Conservar el formulario si falla el guardado.');
    assert.equal(nodes['[data-movs-adj-save]'].disabled, false);
    assert.match(nodes['[data-movs-adj-message]'].textContent, /Fallo controlado/);
    console.log('OK: ajuste refresca todos los saldos sin desplazar la pantalla; error conserva los datos.');
}
testAdjustmentRefresh().catch(error => { console.error(error); process.exitCode = 1; });

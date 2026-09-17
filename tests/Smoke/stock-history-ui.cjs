const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const source = fs.readFileSync(path.join(__dirname, '../../public/js/user-stock-locations.js'), 'utf8');
const ending = '})(window, document);';
assert.ok(source.includes(ending));
const context = {window: {}, document: {addEventListener() {}}};
vm.runInNewContext(source.replace(ending,
    'window.testStock = {state, movementTypeLabel, movementReasonLabel, movementDateLabel, renderMovements};' + ending), context);
const ui = context.window.testStock;
assert.equal(ui.movementTypeLabel('manual_product_created'), 'Carga manual');
assert.equal(ui.movementReasonLabel('recipe_cook'), 'Preparación de receta');
assert.equal(ui.movementReasonLabel('shopping_list_completion'), 'Compra confirmada');
assert.equal(ui.movementReasonLabel('Control manual'), 'Control manual');
assert.equal(ui.movementDateLabel(null), '-');
assert.equal(ui.movementDateLabel('fecha inválida'), 'fecha inválida');
assert.doesNotMatch(ui.movementDateLabel('2026-09-17T21:19:32.000000Z'), /T|000000Z/);
const nodes = {};
const root = {querySelector(selector) { return nodes[selector] ||= {innerHTML: '', style: {}}; }};
ui.state.currentGroupId = 2;
ui.state.movements = [{created_at: '2026-09-17T21:19:32Z', movement_type: 'consumption',
    quantity: '200.0000', unit: {symbol: 'g'}, reason: 'recipe_cook', product: {name: '<Arroz>'}}];
ui.renderMovements(root);
const html = nodes['[data-stock-movements-body]'].innerHTML;
assert.match(html, /200\.0000 g/);
assert.match(html, /Preparación de receta/);
assert.match(html, /&lt;Arroz&gt;/);
assert.doesNotMatch(html, /recipe_cook|T21:/);
console.log('OK: historial con unidad, fechas legibles y motivos en espanol.');

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

function load(file, exports, document) {
    const source = fs.readFileSync(path.join(__dirname, '../../public/js/', file), 'utf8');
    const ending = '})(window, document);';
    assert.ok(source.includes(ending));
    const context = { window: {}, document };
    vm.runInNewContext(source.replace(ending, 'window.testUi = {' + exports + '};' + ending), context);
    return context.window.testUi;
}

const nodes = {};
const document = {
    addEventListener() {},
    querySelector(selector) { return nodes[selector] ||= {innerHTML: '', textContent: '', style: {}}; },
};
const onboarding = load('user-onboarding.js', 'render, stepDetail', document);
assert.equal(onboarding.stepDetail('basic_profile', {missing: ['height_cm']}), 'Falta: altura');
onboarding.render(document, {required_steps: [1, 2, 3, 4], completed_count: 3, next_step: 'family_group', complete: false, steps: {}});
assert.equal(nodes['[data-screen-primary-action]'].href, '/web/family-group');
onboarding.render(document, {required_steps: [1, 2, 3, 4], completed_count: 4, next_step: null, complete: true, steps: {}});
assert.equal(nodes['[data-screen-primary-action]'].href, '/web');
assert.match(nodes['[data-onboarding-progress]'].textContent, /Configuracion inicial completa/);

const planning = load('user-meal-plans.js', 'state, statusLabel, modeLabel, renderShoppingPreview', document);
assert.equal(planning.statusLabel('draft'), 'Borrador');
assert.equal(planning.statusLabel('planned'), 'Planificado');
assert.equal(planning.statusLabel('cooked'), 'Cocinado');
assert.equal(planning.modeLabel('auto'), 'Automatico');
planning.state.selectedPlan = {id: 1};
planning.state.generatedShoppingList = {id: 12, status: 'draft'};
planning.renderShoppingPreview(document);
assert.ok(Object.values(nodes).some(node => node.innerHTML.includes('#12 - Borrador')));
console.log('OK: etiquetas de planificacion, lista generada y navegacion de configuracion inicial.');

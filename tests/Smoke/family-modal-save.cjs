const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { JSDOM } = require(require.resolve('jsdom', { paths: [path.join(__dirname, '../../mobile')] }));
const read = file => fs.readFileSync(path.join(__dirname, '../..', file), 'utf8');
const template = new JSDOM(read('resources/views/web/user-screen.blade.php'));
const markup = template.window.document.querySelector('[data-family-groups]').outerHTML;
template.window.close();
(async () => {
  const dom = new JSDOM(markup, {url:'http://localhost/web/family-group',runScripts:'outside-only'});
  const w = dom.window;
  w.HTMLDialogElement.prototype.showModal = function () { this.setAttribute('open',''); };
  w.HTMLDialogElement.prototype.close = function () { this.removeAttribute('open'); this.dispatchEvent(new w.Event('close')); };
  let group = {id:1,name:'Hogar local',status:'active',owner_user_id:1}, writes = 0;
  w.CCApi = {request: async (url, options = {}) => {
    if (options.method === 'PATCH') { writes++; group = {...group,...options.body}; return {data:group}; }
    if (url === '/api/v1/family-groups') return {data:[group]};
    if (url.endsWith('/preferences')) return {data:{}};
    return {data:[]};
  }};
  await new Promise(resolve => w.document.addEventListener('DOMContentLoaded',resolve,{once:true}));
  w.eval(read('public/js/panel-ui.js'));
  w.eval(read('public/js/family-groups.js'));
  w.document.dispatchEvent(new w.Event('DOMContentLoaded'));
  await new Promise(resolve => setImmediate(resolve));
  const form = w.document.querySelector('[data-family-edit-form]');
  w.CCUI.reveal(form);
  form.elements.name.value = 'Hogar actualizado';
  form.dispatchEvent(new w.Event('submit',{bubbles:true,cancelable:true}));
  await new Promise(resolve => setImmediate(resolve));
  assert.equal(writes,1);
  assert.equal(group.name,'Hogar actualizado');
  assert.ok(!form.closest('dialog').open);
  assert.equal(w.document.querySelector('[data-ui-notice]').textContent,'Grupo actualizado correctamente.');
  assert.equal(w.document.querySelector('[data-family-select] option').textContent,'Hogar actualizado');
  assert.ok(!w.document.body.textContent.includes('form is not defined'));
  dom.window.close();
  console.log('PASS: family save keeps form reference, closes modal and refreshes household.');
})().catch(error => {console.error(error);process.exitCode=1;});

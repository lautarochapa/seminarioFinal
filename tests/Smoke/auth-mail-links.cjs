const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const source = fs.readFileSync(path.join(__dirname, '../../public/js/auth-api.js'), 'utf8');

async function redirect(destination, permissions) {
  const form = { dataset: { redirect: destination } };
  const window = {
    location: { origin: 'https://cocina.example.com', href: '' },
    CCApi: {
      request: async () => ({ token_payload: { permissions } }),
      setSession() {},
    },
  };
  const document = {
    querySelector: (selector) => selector === '[data-auth-session="true"]' ? form : null,
    addEventListener() {},
  };
  vm.runInNewContext(source, { window, document, URL });
  await window.CCAuth.loginWithGoogle('fake-token');
  return window.location.href;
}

(async () => {
  const user = ['web.user.dashboard', 'web.user.family-group'];
  assert.equal(await redirect('https://cocina.example.com/web/family-group?invitation=12', user), '/web/family-group?invitation=12');
  assert.equal(await redirect('/web/family-group?invitation=12', user.concat('web.admin.dashboard')), '/web/family-group?invitation=12');
  assert.equal(await redirect('/web/family-group?invitation=12', ['web.admin.dashboard']), '/admin-web');
  assert.equal(await redirect('/web/family-group?invitation=12', []), '/web');
  assert.equal(await redirect('https://evil.example/web/family-group?invitation=12', user), '/web');
  assert.equal(await redirect('https://evil.example', []), '/web');
  assert.equal(await redirect('/web/family-group?invitation=-2', user), '/web');
  assert.equal(await redirect('/web/family-group?invitation=texto', user), '/web');
  assert.equal(await redirect('/web', ['web.admin.dashboard']), '/admin-web');
  console.log('OK: invitaciones tras autenticacion, origen seguro, permiso requerido y dashboards conservados.');
})().catch((error) => { console.error(error); process.exitCode = 1; });

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { JSDOM } = require(require.resolve('jsdom', { paths: [path.join(__dirname, '../../mobile')] }));
const source = fs.readFileSync(path.join(__dirname, '../../public/js/mobile-entry.js'), 'utf8');
function page(ua, invitation = false, touch = 0) {
  const dom = new JSDOM(`<body data-mobile-invitation="${invitation ? 1 : 0}">
    <a data-mobile-entry="login" href="/login">Ingresar</a>
    <a data-mobile-entry="register" href="/register">Registrate</a>
    <a data-open-app href="cccontrol://login">Abrir app</a>
    <div data-ios-entry hidden>Escritorio</div><div data-android-entry>Android</div>
    <a id="reset" href="/password/reset">Recuperar</a></body>`, {url:'https://cocina.example.com/',runScripts:'outside-only'});
  Object.defineProperty(dom.window.navigator, 'userAgent', {value:ua});
  Object.defineProperty(dom.window.navigator, 'maxTouchPoints', {value:touch});
  dom.window.eval(source);
  dom.window.document.dispatchEvent(new dom.window.Event('DOMContentLoaded'));
  return dom;
}
for (const ua of ['Android', 'iPhone', 'Macintosh', 'Desktop']) {
  const dom = page(ua, false, ua === 'Macintosh' ? 5 : 0);
  const d = dom.window.document;
  const href = d.querySelector('[data-mobile-entry="login"]').getAttribute('href');
  if (ua === 'Android') {
    assert.match(href, /^intent:\/\/login\/#Intent;scheme=cccontrol;package=com\.cccontrol\.mobile;/);
    assert.ok(href.includes(encodeURIComponent('https://cocina.example.com/abrir-app')));
    assert.match(d.querySelector('[data-mobile-entry="register"]').href, /^intent:\/\/register\//);
    assert.ok(d.querySelector('[data-ios-entry]').hidden);
  } else if (ua === 'Desktop') {
    assert.equal(href, '/login');
  } else {
    assert.equal(href, '/abrir-app');
    assert.ok(d.querySelector('[data-android-entry]').hidden);
    assert.ok(!d.querySelector('[data-ios-entry]').hidden);
  }
  assert.equal(d.querySelector('#reset').getAttribute('href'), '/password/reset');
  assert.equal(dom.window.location.href, 'https://cocina.example.com/', 'No automatic redirects');
  dom.window.close();
}
const invite = page('Android', true);
assert.equal(invite.window.document.querySelector('[data-mobile-entry="login"]').getAttribute('href'), '/login');
assert.equal(invite.window.document.querySelector('[data-mobile-entry="register"]').getAttribute('href'), '/register');
invite.window.close();
console.log('PASS: Android intents, fallback, iOS/iPad notice, desktop and invitation exceptions.');

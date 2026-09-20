const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const css = fs.readFileSync(path.join(__dirname, '../../public/css/navbar.css'), 'utf8');
const smallScreen = css.slice(css.indexOf('@media only screen and (max-width: 800px)'));
assert.ok(smallScreen.startsWith('@media'));
assert.doesNotMatch(smallScreen, /display\s*:\s*none\b/, 'No ocultar el login ni los dropdowns en ventanas angostas.');
assert.match(smallScreen, /\.site-navbar\s*\{[^}]*flex-wrap\s*:\s*wrap/s);
assert.match(smallScreen, /\.site-navbar\s+\.nav__links\s*\{[^}]*flex-wrap\s*:\s*wrap/s);
assert.match(smallScreen, /\.site-navbar\s+\.cta:not\(\.menu\)\s*\{[^}]*display\s*:\s*inline-flex/s);
assert.doesNotMatch(css, /\bheader\b/, 'El navbar no debe modificar los encabezados del contenido.');
assert.match(smallScreen, /max-width\s*:\s*calc\(100vw - 32px\)/);
assert.doesNotMatch(smallScreen, /\.menu\s*\{[^}]*display\s*:\s*(?:initial|block|flex)/s);

for (const layout of ['app', 'web-user', 'admin-web', 'teacher-web']) {
  const blade = fs.readFileSync(path.join(__dirname, `../../resources/views/layouts/${layout}.blade.php`), 'utf8');
  assert.ok(blade.includes('<header class="site-navbar">'));
  assert.ok(blade.includes("asset('css/navbar.css') }}?v={{ filemtime(public_path('css/navbar.css'))"),
    `El layout ${layout} debe invalidar la cache del navbar cuando cambia el CSS.`);
}

console.log('OK: navbar adaptable, accesos visibles y CSS versionado.');

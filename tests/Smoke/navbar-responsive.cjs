const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const css = fs.readFileSync(path.join(__dirname, '../../public/css/navbar.css'), 'utf8');
const smallScreen = css.slice(css.indexOf('@media only screen and (max-width: 800px)'));
assert.ok(smallScreen.startsWith('@media'));
assert.doesNotMatch(smallScreen, /display\s*:\s*none\b/, 'No ocultar el login ni los dropdowns en ventanas angostas.');
assert.match(smallScreen, /header\s*\{[^}]*flex-wrap\s*:\s*wrap/s);
assert.match(smallScreen, /header\s+\.nav__links\s*\{[^}]*flex-wrap\s*:\s*wrap/s);
assert.match(smallScreen, /header\s+\.cta:not\(\.menu\)\s*\{[^}]*display\s*:\s*inline-flex/s);
assert.match(smallScreen, /max-width\s*:\s*calc\(100vw - 32px\)/);
assert.doesNotMatch(smallScreen, /\.menu\s*\{[^}]*display\s*:\s*(?:initial|block|flex)/s);

console.log('OK: navbar adaptable sin ocultar login ni menus del usuario.');

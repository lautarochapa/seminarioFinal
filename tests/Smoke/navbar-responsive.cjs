const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const read = file => fs.readFileSync(path.join(__dirname, '../..', file), 'utf8');
const css = read('public/css/navbar.css');
assert.match(css, /max-width:1180px/);
assert.match(css, /@media \(max-width:680px\)/);
assert.match(css, /\.logo img \{ width:170px/);
assert.match(css, /\.nav-auth-actions \.cta \{ padding:8px 11px/);
assert.doesNotMatch(css, /\bheader\s*\{/);
for (const layout of ['app', 'web-user', 'admin-web']) {
 const blade = read('resources/views/layouts/' + layout + '.blade.php');
 assert.ok(blade.includes("@include('partials.site-header')"));
 assert.ok(blade.includes("@include('partials.site-footer')"));
 assert.ok(blade.includes("filemtime(public_path('css/navbar.css'))"));
}
const header = read('resources/views/partials/user-navbar-menu.blade.php');
assert.ok(header.includes('Registrate'));
assert.ok(header.includes('data-mobile-entry="login"'));
assert.ok(header.includes('data-mobile-entry="register"'));
console.log('PASS: shared header/footer, registration and compact public mobile navbar');

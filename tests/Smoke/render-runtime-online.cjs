const assert = require('node:assert/strict');

// Explicit URL required. Sequential, read-only checks; not a stress test.
const base = new URL(process.argv[2]);
assert.ok(['https:', 'http:'].includes(base.protocol));
async function main() {
    for (const route of ['/healthz', '/', '/login', '/js/auth-api.js', '/.env', '/.git/FETCH_HEAD', '/.well-known/.env']) {
        const start = Date.now();
        const response = await fetch(new URL(route, base), {signal: AbortSignal.timeout(90000)});
        const body = await response.text();
        const blocked = route.includes('/.');
        assert.equal(response.status, blocked ? 404 : 200, route);
        if (blocked) {
            assert.equal(response.headers.has('set-cookie'), false, route + ' started an app session');
            assert.equal(response.headers.has('x-powered-by'), false, route + ' reached PHP');
        }
        if (route === '/healthz') assert.equal(JSON.parse(body).status, 'ok');
        console.log(JSON.stringify({at: new Date().toISOString(), host: base.host, route, status: response.status, elapsed_ms: Date.now() - start, bytes: Buffer.byteLength(body)}));
    }
}
main().catch(error => { console.error(error.message); process.exitCode = 1; });

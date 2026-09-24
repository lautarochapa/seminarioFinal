const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../../public/js/api-client.js'), 'utf8');
function client(response) {
    const storage = new Map();
    const window = { localStorage: { getItem: key => storage.get(key) || null, setItem: (key, value) => storage.set(key, value), removeItem: key => storage.delete(key) },
        fetch: async () => ({ ok: true, status: 200, headers: { get: () => 'test-trace' }, text: async () => '{"data":[]}', ...response }) };
    vm.runInNewContext(source, { window, FormData: class FormData {} });
    window.CCApi.setSession({ token: 'test-token', user: {} });
    return window.CCApi;
}
(async () => {
    const valid = client({});
    assert.equal((await valid.request('/api/test')).data.length, 0);
    for (const status of [200, 502, 503]) {
        await assert.rejects(client({ status, ok: status === 200, text: async () => '<!DOCTYPE html><h1>Unavailable</h1>' }).request('/api/test'),
            error => error.code === 'API_INVALID_RESPONSE' && !error.message.includes('Unexpected token') && error.status === status);
    }
    const redirected = client({ redirected: true, url: 'https://cocina.example/login', text: async () => '<html>Login</html>' });
    await assert.rejects(redirected.request('/api/test'), error => error.status === 401);
    assert.equal(redirected.getToken(), null);
    await assert.rejects(client({ ok: false, status: 422, text: async () => '{"error":{"message":"Cantidad requerida"}}' }).request('/api/test'), /Cantidad requerida/);
    assert.equal((await client({ status: 204 }).request('/api/test')).data, null);
    console.log('PASS: API JSON, HTML errors, redirects, validation and empty responses');
})().catch(error => { console.error(error); process.exitCode = 1; });

const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const {spawnSync} = require('node:child_process');

const root = path.resolve(__dirname, '../..');
const bash = process.env.BASH_EXE || (process.platform === 'win32' ? 'C:/Program Files/Git/bin/bash.exe' : 'bash');
const read = name => fs.readFileSync(path.join(root, name), 'utf8');
const config = read('docker/apache/mpm_prefork.conf');
for (const [directive, value] of Object.entries({StartServers: 2, MinSpareServers: 1, MaxSpareServers: 2, ServerLimit: 2, MaxRequestWorkers: 2, MaxConnectionsPerChild: 500})) {
    assert.match(config, new RegExp(`^\\s*${directive} ${value}\\s*$`, 'm'));
}
assert.match(read('docker/php/runtime.ini'), /^memory_limit = 128M$/m);
assert.match(read('docker/apache/runtime.conf'), /^KeepAliveTimeout 1$/m);
const docker = read('Dockerfile');
assert.match(docker, /COPY docker\/apache\/mpm_prefork.conf \/etc\/apache2\/mods-available\/mpm_prefork.conf/);
assert.match(docker, /COPY docker\/php\/runtime.ini \/usr\/local\/etc\/php\/conf.d\/zz-runtime.ini/);
assert.match(docker, /RUN bash \/tmp\/apache-smoke.sh/);
assert.match(read('docker/entrypoint.sh'), /render-memory-log &/);

for (const file of ['docker/entrypoint.sh', 'docker/memory-log.sh', 'docker/tests/apache-smoke.sh']) {
    const result = spawnSync(bash, ['-n', file], {cwd: root, encoding: 'utf8'});
    assert.equal(result.status, 0, result.error?.message || result.stderr);
}

const fixture = fs.mkdtempSync(path.join(os.tmpdir(), 'cccontrol-cgroup-'));
try {
    function sample() {
        const result = spawnSync(bash, ['-c', 'source docker/memory-log.sh; log_memory "$1"', 'test', fixture.replaceAll('\\', '/')], {cwd: root, encoding: 'utf8'});
        assert.equal(result.status, 0, result.stderr);
        return result.stdout.trim();
    }
    assert.equal(sample(), '', 'Unsupported cgroup should not break the server');
    fs.writeFileSync(path.join(fixture, 'memory.current'), '314572800\n');
    fs.writeFileSync(path.join(fixture, 'memory.max'), '536870912\n');
    fs.writeFileSync(path.join(fixture, 'memory.stat'), 'anon 250000000\ninactive_file 10485760\n');
    assert.equal(sample(), '[runtime-memory] used_bytes=314572800 working_set_bytes=304087040 limit_bytes=536870912');
    fs.writeFileSync(path.join(fixture, 'memory.max'), 'max\n');
    assert.match(sample(), /limit_bytes=max$/);
    fs.unlinkSync(path.join(fixture, 'memory.current'));
    fs.mkdirSync(path.join(fixture, 'memory'));
    fs.writeFileSync(path.join(fixture, 'memory/memory.usage_in_bytes'), '314572800\n');
    fs.writeFileSync(path.join(fixture, 'memory/memory.limit_in_bytes'), '536870912\n');
    fs.writeFileSync(path.join(fixture, 'memory/memory.stat'), 'inactive_file 100\ntotal_inactive_file 10485760\n');
    assert.equal(sample(), '[runtime-memory] used_bytes=314572800 working_set_bytes=304087040 limit_bytes=536870912');
    fs.writeFileSync(path.join(fixture, 'memory/memory.usage_in_bytes'), 'not-a-number\n');
    assert.equal(sample(), '');
} finally {
    for (const file of ['memory.current', 'memory.max', 'memory.stat', 'memory/memory.usage_in_bytes', 'memory/memory.limit_in_bytes', 'memory/memory.stat']) {
        const target = path.join(fixture, file);
        if (fs.existsSync(target)) fs.unlinkSync(target);
    }
    if (fs.existsSync(path.join(fixture, 'memory'))) fs.rmdirSync(path.join(fixture, 'memory'));
    fs.rmdirSync(fixture);
}
console.log('Render runtime: configuration, shell syntax and cgroup v1/v2 checks passed.');

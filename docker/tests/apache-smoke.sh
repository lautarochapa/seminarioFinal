#!/usr/bin/env bash
set -euo pipefail

root=/var/www/html/public
cleanup() {
    apache2ctl -k stop || true
    rm -f "$root/index.php" "$root/infra-smoke.js" "$root/.well-known/acme-challenge/infra-smoke"
}
trap cleanup EXIT
mkdir -p "$root/.well-known/acme-challenge"
printf '%s' '<?php header("X-Infra-PHP: reached"); echo json_encode(["memory_limit" => ini_get("memory_limit"), "authorization" => $_SERVER["HTTP_AUTHORIZATION"] ?? null]);' > "$root/index.php"
printf 'static-ok' > "$root/infra-smoke.js"
printf 'acme-ok' > "$root/.well-known/acme-challenge/infra-smoke"
apache2ctl -t
apache2ctl -M | grep -q mpm_prefork_module
apache2ctl -k start

php <<'PHP'
<?php
function getPage($path) {
    $context = stream_context_create(['http' => [
        'ignore_errors' => true, 'timeout' => 5,
        'header' => "Authorization: Bearer infra-smoke-not-a-secret\r\nConnection: close\r\n",
    ]]);
    $body = file_get_contents('http://127.0.0.1'.$path, false, $context);
    return [$body, implode("\n", $http_response_header ?? [])];
}
function check($condition, $message) {
    if (!$condition) { file_put_contents('php://stderr', $message."\n"); exit(1); }
}
for ($attempt = 0; $attempt < 20; $attempt++) {
    $socket = @fsockopen('127.0.0.1', 80, $errno, $error, 0.2);
    if ($socket) { fclose($socket); break; }
    usleep(100000);
}
foreach (['/', '/healthz', '/api/v1/users/me/profile', '/login', '/password/reset/test'] as $path) {
    [$body, $headers] = getPage($path);
    check(strpos($headers, ' 200 ') !== false, 'Normal route rejected: '.$path);
    $data = json_decode($body, true);
    check(($data['memory_limit'] ?? null) === '128M', 'PHP memory limit is not active');
    check(($data['authorization'] ?? null) === 'Bearer infra-smoke-not-a-secret', 'Authorization header lost');
}
foreach (['/.env', '/.env.old', '/.git/FETCH_HEAD', '/.aws/config', '/.config/gcloud/credentials.db', '/nested/.env', '/%2eenv', '/.well-known/.env', '/.well-known-other/test'] as $path) {
    [$body, $headers] = getPage($path);
    check(strpos($headers, ' 404 ') !== false, 'Scanner route not rejected: '.$path);
    check(stripos($headers, 'X-Infra-PHP:') === false, 'Rejected route reached PHP: '.$path);
}
foreach (['/infra-smoke.js' => 'static-ok', '/.well-known/acme-challenge/infra-smoke' => 'acme-ok'] as $path => $expected) {
    [$body, $headers] = getPage($path);
    check(strpos($headers, ' 200 ') !== false && $body === $expected, 'Static/ACME route rejected: '.$path);
}
echo "Apache runtime smoke: 16 HTTP checks passed; PHP limit and Authorization verified.\n";
PHP

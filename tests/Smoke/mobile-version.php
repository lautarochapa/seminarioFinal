<?php

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->instance('request', Illuminate\Http\Request::create('http://localhost'));
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();
config(['session.driver' => 'array', 'cache.default' => 'array']);

function checkMobileVersion($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$route = app('router')->getRoutes()->getByName('api.mobile.android.version');
checkMobileVersion($route && $route->uri() === 'api/v1/mobile/android/version', 'Falta la ruta publica de version.');
foreach (['web', 'auth', 'api_token'] as $middleware) {
    checkMobileVersion(!in_array($middleware, $route->gatherMiddleware(), true), 'La version no debe requerir sesion/autenticacion.');
}
$queryCount = 0;
Illuminate\Support\Facades\DB::listen(function () use (&$queryCount) { $queryCount++; });
$published = config('mobile.android');
$request = function () use ($kernel) {
    $request = Illuminate\Http\Request::create('/api/v1/mobile/android/version', 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/json']);
    $response = $kernel->handle($request);
    $kernel->terminate($request, $response);
    return $response;
};

$response = $request();
checkMobileVersion($response->getStatusCode() === 200, 'La consulta anonima debe responder 200.');
$json = json_decode($response->getContent(), true);
checkMobileVersion($json['data'] === [
    'platform' => 'android',
    'version' => $published['version'],
    'build' => $published['build'],
    'download_page_url' => $published['download_page_url'],
], 'La API debe usar la misma version publicada que la landing.');
checkMobileVersion(!empty($json['trace_id']) && $response->headers->get('X-Trace-Id') === $json['trace_id'], 'Falta trazabilidad.');
checkMobileVersion(strpos($response->headers->get('Cache-Control'), 'no-store') !== false, 'No cachear una version antigua.');
checkMobileVersion(count($response->headers->getCookies()) === 0, 'La consulta no debe crear cookies de sesion.');

config(['mobile.android.version' => '1.0.10', 'mobile.android.build' => 11]);
$next = json_decode($request()->getContent(), true);
checkMobileVersion($next['data']['version'] === '1.0.10' && $next['data']['build'] === 11, 'La respuesta no refleja la nueva release configurada.');

foreach ([
    ['download_url', null], ['download_url', 'http://example.test/app.apk'],
    ['download_page_url', 'javascript:alert(1)'], ['download_page_url', '/descargas/android'],
    ['download_page_url', 'https://user:secret@example.test'], ['download_page_url', ''],
    ['version', ''], ['version', null], ['version', 'sin publicar'],
    ['build', 0], ['build', -1], ['build', '7'], ['build', 7.5],
] as [$key, $value]) {
    config(['mobile.android' => array_merge($published, [$key => $value])]);
    $invalid = $request();
    checkMobileVersion($invalid->getStatusCode() === 503, 'Configuracion invalida debe desactivar el aviso: '.$key);
    $payload = json_decode($invalid->getContent(), true);
    checkMobileVersion(!isset($payload['data']), 'No anunciar una descarga invalida.');
    checkMobileVersion(strpos($invalid->headers->get('Cache-Control'), 'no-store') !== false, 'No cachear errores de configuracion.');
}
checkMobileVersion($queryCount === 0, 'La version no debe consultar la base de datos.');
config(['mobile.android' => $published]);
echo 'OK: version Android publica, sin sesion/BD, datos compartidos con landing, nueva version, no-cache y configuraciones invalidas.'.PHP_EOL;

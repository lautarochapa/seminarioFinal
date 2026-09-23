<?php

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['session.driver' => 'array', 'cache.default' => 'array']);

function checkLanding($condition, $message)
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

$route = app('router')->getRoutes()->getByName('downloads.android');
checkLanding($route && $route->uri() === 'descargas/android', 'Falta la ruta estable de descarga.');
checkLanding(! in_array('auth', $route->gatherMiddleware(), true), 'La descarga debe ser publica.');
$controller = new App\Http\Controllers\AndroidDownloadController;
$url = 'https://github.com/lautarochapa/seminarioFinal/releases/download/android-v1.0.6-7/CocinaComidaControl-1.0.6-7.apk';
config(['mobile.android.download_url' => $url]);
$response = $controller();
checkLanding($response->getStatusCode() === 302, 'La descarga debe redirigir sin cache permanente.');
checkLanding($response->headers->get('Location') === $url, 'El destino no coincide con el archivo versionado.');
checkLanding(strpos($response->headers->get('Cache-Control'), 'no-store') !== false, 'No cachear el enlace a versiones anteriores.');
$html = view('welcome')->render();
checkLanding(strpos($html, 'Descargar APK 1.0.6') !== false, 'Falta version en el boton.');
checkLanding(config('mobile.android.build') === 7, 'Compilacion incorrecta.');
checkLanding(config('mobile.android.size_bytes') === 129110371, 'Tamano de APK incorrecto.');
checkLanding(config('mobile.android.sha256') === '7d6258973e7270f91c5472f1ad0c930464711b275c6dfdc41110132e91d9a81e', 'Huella de APK incorrecta.');
checkLanding(strpos($html, '129 MB') !== false, 'Falta tamano de la descarga.');
checkLanding(strpos($html, 'Espacio reservado') === false, 'Quedan capturas sin incorporar.');
foreach (['web-dashboard-martin.jpg', 'mobile-home-preview-martin.jpg'] as $image) {
    checkLanding(strpos($html, $image) !== false && is_file(public_path('images/landing/'.$image)), 'Falta captura '.$image);
    $dimensions = getimagesize(public_path('images/landing/'.$image));
    checkLanding($dimensions !== false, 'La captura debe ser una imagen valida.');
    checkLanding((bool) preg_match('/<img[^>]+'.preg_quote($image, '/').'[^>]+width="'.$dimensions[0].'" height="'.$dimensions[1].'"/', $html), 'Dimensiones incorrectas para '.$image);
}
checkLanding(strpos($html, 'Vista previa móvil simulada en navegador, con datos de ejemplo.') !== false, 'La simulacion movil debe estar identificada.');
checkLanding(strpos($html, 'images/landing/android-home.png') === false && strpos($html, 'images/landing/web-dashboard.png') === false, 'No mostrar las capturas anteriores de cuentas de prueba.');
foreach ([null, '', 'http://example.com/app.apk', 'javascript:alert(1)'] as $invalidUrl) {
    config(['mobile.android.download_url' => $invalidUrl]);
    try {
        $controller();
        throw new RuntimeException('No debe redirigir a una URL ausente o insegura.');
    } catch (Symfony\Component\HttpKernel\Exception\HttpException $exception) {
        checkLanding($exception->getStatusCode() === 503, 'Estado incorrecto cuando no hay descarga valida.');
    }
}
config(['mobile.android.download_url' => null]);
checkLanding(strpos(view('welcome')->render(), 'Descargar APK') === false, 'No ofrecer una descarga sin archivo publicado.');
echo "OK: landing con capturas y descarga publica versionada; HTTPS, sin cache y sin enlace cuando no esta disponible.\n";

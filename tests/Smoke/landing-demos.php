<?php

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['session.driver' => 'array', 'cache.default' => 'array']);

function checkDemo($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function demoDocument()
{
    $document = new DOMDocument;
    $previous = libxml_use_internal_errors(true);
    $document->loadHTML(view('welcome')->render());
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    return new DOMXPath($document);
}

$web = config('demos.videos.web');
checkDemo(is_file(public_path($web['file'])), 'Falta el MP4 web.');
checkDemo(getimagesize(public_path($web['poster'])) !== false, 'Falta el poster valido.');
checkDemo($web['file'] === 'videos/demo-web-guiada-20260923.mp4', 'Debe publicarse la demo narrada con guia visual.');
checkDemo($web['duration'] === '2:09', 'La duracion debe corresponder al montaje narrado.');
checkDemo(strpos($web['description'], 'sin audio') === false, 'La descripcion no debe anunciar una demo sin audio.');
checkDemo(hash_file('sha256', public_path($web['file'])) === '2b6d074fb543059598895d7bf865de09364ff1212d29f1e2be28094e8030641e', 'El video debe ser el montaje aprobado sin alteraciones.');
$document = demoDocument();
checkDemo($document->query('//section[@id="demos"]//video')->length === 1, 'Mostrar solo el video disponible.');
checkDemo($document->query('//section[@id="demos"]//video[@controls and @playsinline and @preload="none" and not(@autoplay) and not(@loop)]')->length === 1, 'No precargar ni reproducir automaticamente el video.');
checkDemo($document->query('//a[@href="#demos"]')->length === 2, 'Faltan los accesos a las demos.');
checkDemo($document->query('//section[contains(@class,"home-hero")]//a[@href="#demos" and normalize-space(.)="Ver demo"]')->length === 1, 'Falta el acceso a la demo desde el inicio.');
checkDemo($document->query('//section[@id="demos"]//a[@download]')->length === 1, 'Falta la descarga alternativa.');
checkDemo($document->query('//section[@id="demos"]//details//li')->length === 5, 'Falta la alternativa de lectura.');
checkDemo($document->query('//*[@id="demo-android-title"]')->length === 0, 'No anunciar una demo Android inexistente.');
config(['demos.videos.android.file' => $web['file'], 'demos.videos.android.poster' => $web['poster']]);
checkDemo(demoDocument()->query('//section[@id="demos"]//video')->length === 2, 'La segunda demo debe aparecer al publicarse.');
config(['demos.videos.web.file' => 'videos/no-existe.mp4', 'demos.videos.android.file' => null]);
$document = demoDocument();
checkDemo($document->query('//section[@id="demos"]')->length === 0, 'Ocultar la seccion si faltan los videos.');
checkDemo($document->query('//a[@href="#demos"]')->length === 0, 'No dejar un enlace interno roto.');
checkDemo($document->query('//section[contains(@class,"home-hero")]//a[@href="#producto"]')->length === 1, 'Conservar la navegacion al producto si falta el video.');
echo "OK: videos disponibles, controles nativos, sin precarga, lectura alternativa y estados sin archivos.\n";

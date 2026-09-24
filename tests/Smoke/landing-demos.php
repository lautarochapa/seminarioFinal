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
$android = config('demos.videos.android');
checkDemo(is_file(public_path($android['file'])), 'Falta el MP4 Android.');
checkDemo(getimagesize(public_path($android['poster'])) !== false, 'Falta el poster Android valido.');
checkDemo($android['duration'] === '1:32', 'La duracion Android debe corresponder al montaje aprobado.');
checkDemo(hash_file('sha256', public_path($android['file'])) === '432e857f58e6660d8857467774d9c7c5d97c2c37151005b0f04600b13d2959c5', 'El video Android debe ser el montaje aprobado sin alteraciones.');
checkDemo(is_file(public_path($android['subtitles'])), 'Faltan los subtitulos Android.');
checkDemo(strpos(file_get_contents(public_path($android['subtitles'])), 'WEBVTT') === 0, 'Los subtitulos deben ser WebVTT.');
$document = demoDocument();
checkDemo($document->query('//section[@id="demos"]//video')->length === 2, 'Mostrar ambas demos disponibles.');
checkDemo($document->query('//section[@id="demos"]//video[@controls and @playsinline and @preload="none" and not(@autoplay) and not(@loop)]')->length === 2, 'No precargar ni reproducir automaticamente los videos.');
checkDemo($document->query('//a[@href="#demos"]')->length === 1, 'Conservar un acceso directo a las demos sin repetir el salto.');
checkDemo($document->query('//section[contains(@class,"home-hero")]//a[@href="#demos" and normalize-space(.)="Ver demo"]')->length === 1, 'Falta el acceso a la demo desde el inicio.');
checkDemo($document->query('//section[@id="demos"]//a[@download]')->length === 2, 'Faltan las descargas alternativas.');
checkDemo($document->query('//section[@id="demos"]//details//li')->length === 24, 'Falta el recorrido completo de lectura.');
checkDemo($document->query('//*[@id="demo-android-title"]')->length === 1, 'Falta el titulo Android.');
// DOMDocument usa el parser HTML4, que trata source como un elemento contenedor.
checkDemo($document->query('//video[@aria-labelledby="demo-android-title"]//track[@kind="captions" and @srclang="es"]')->length === 1, 'Faltan los subtitulos en espanol.');
checkDemo($document->query('//video[@aria-labelledby="demo-android-title"]//track')->item(0)->getAttribute('src') === asset($android['subtitles']), 'El enlace de subtitulos debe apuntar al archivo publicado.');
config(['demos.videos.android.subtitles' => 'videos/no-existe.vtt']);
checkDemo(demoDocument()->query('//section[@id="demos"]//track')->length === 0, 'No enlazar subtitulos inexistentes.');
config(['demos.videos.android' => $android, 'demos.videos.android.file' => null]);
checkDemo(demoDocument()->query('//section[@id="demos"]//video')->length === 1, 'Conservar la demo web sin Android.');
config(['demos.videos.android' => $android, 'demos.videos.web.file' => 'videos/no-existe.mp4']);
checkDemo(demoDocument()->query('//section[@id="demos"]//video')->length === 1, 'Conservar la demo Android sin web.');
config(['demos.videos.web.file' => 'videos/no-existe.mp4', 'demos.videos.android.file' => null]);
$document = demoDocument();
checkDemo($document->query('//section[@id="demos"]')->length === 0, 'Ocultar la seccion si faltan los videos.');
checkDemo($document->query('//a[@href="#demos"]')->length === 0, 'No dejar un enlace interno roto.');
checkDemo($document->query('//section[contains(@class,"home-hero")]//a[@href="#producto"]')->length === 1, 'Conservar la navegacion al producto si falta el video.');
echo "OK: dos montajes aprobados, subtitulos Android, controles sin precarga, lectura y estados 0/1/2 videos.\n";

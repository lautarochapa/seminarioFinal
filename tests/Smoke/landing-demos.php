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
$document = demoDocument();
checkDemo($document->query('//section[@id="demos"]//video')->length === 1, 'Mostrar solo el video disponible.');
checkDemo($document->query('//section[@id="demos"]//video[@controls and @playsinline and @preload="none" and not(@autoplay) and not(@loop)]')->length === 1, 'No precargar ni reproducir automaticamente el video.');
checkDemo($document->query('//a[@href="#demos"]')->length === 1, 'Falta el acceso a las demos.');
checkDemo($document->query('//section[@id="demos"]//a[@download]')->length === 1, 'Falta la descarga alternativa.');
checkDemo($document->query('//section[@id="demos"]//details//li')->length === 5, 'Falta la alternativa de lectura.');
checkDemo($document->query('//*[@id="demo-android-title"]')->length === 0, 'No anunciar una demo Android inexistente.');
config(['demos.videos.android.file' => $web['file'], 'demos.videos.android.poster' => $web['poster']]);
checkDemo(demoDocument()->query('//section[@id="demos"]//video')->length === 2, 'La segunda demo debe aparecer al publicarse.');
config(['demos.videos.web.file' => 'videos/no-existe.mp4', 'demos.videos.android.file' => null]);
$document = demoDocument();
checkDemo($document->query('//section[@id="demos"]')->length === 0, 'Ocultar la seccion si faltan los videos.');
checkDemo($document->query('//a[@href="#demos"]')->length === 0, 'No dejar un enlace interno roto.');
echo "OK: videos disponibles, controles nativos, sin precarga, lectura alternativa y estados sin archivos.\n";

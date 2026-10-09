<?php
// Render the actual active admin screens without booting Laravel, making requests or opening a database.
require dirname(__DIR__, 3).'/vendor/autoload.php';
function url($path) { return $path; }
$controller = new App\Http\Controllers\AdminWebScreenController;
$method = new ReflectionMethod($controller, 'screens');
$method->setAccessible(true);
$screens = $method->invoke($controller);
unset($screens['thesis-docs'], $screens['demo-scenarios']);
$metricLabels = [];
if (method_exists($controller, 'metricLabels')) {
    $labels = new ReflectionMethod($controller, 'metricLabels');
    $labels->setAccessible(true);
    $metricLabels = $labels->invoke($controller);
}
$compiler = new Illuminate\View\Compilers\BladeCompiler(new Illuminate\Filesystem\Filesystem, __DIR__);
$__env = new class { use Illuminate\View\Concerns\ManagesLoops; };
$source = file_get_contents(dirname(__DIR__, 3).'/resources/views/web/admin-screen.blade.php');
$start = strpos($source, "@section('content')") + strlen("@section('content')");
$content = substr($source, $start, strrpos($source, '@endsection') - $start);
$compiled = $compiler->compileString($content);
$rendered = [];
foreach ($screens as $screenKey => $screen) {
    if ($screenKey === 'dashboard') continue; // Its include is covered by render-admin-dashboard.php.
    $stats = array_fill_keys($screen['metrics'], 0);
    ob_start();
    eval('?>'.$compiled);
    $rendered[$screenKey] = ob_get_clean();
}
echo json_encode(['screens' => $screens, 'labels' => $metricLabels, 'rendered' => $rendered], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

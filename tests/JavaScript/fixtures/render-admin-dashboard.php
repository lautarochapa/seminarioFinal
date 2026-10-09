<?php
// Compile the actual dashboard branch and partial without booting Laravel or a database.
require dirname(__DIR__, 3).'/vendor/autoload.php';
$options = json_decode($argv[1] ?? '{}', true);
$screenKey = 'dashboard';
$screen = ['title' => 'Resumen de administración', 'description' => 'Cantidades de los módulos disponibles para tu rol.'];
$dashboard = $options['dashboard'] ?? ['sections' => []];
$stats = ['products' => 999999, 'scraping_jobs' => 999999];
$compiler = new Illuminate\View\Compilers\BladeCompiler(new Illuminate\Filesystem\Filesystem, __DIR__);
$__env = new class {
    use Illuminate\View\Concerns\ManagesLoops;

    public function make($name, $data = [])
    {
        if ($name !== 'web.admin-dashboard') {
            throw new RuntimeException('Unexpected included view: '.$name);
        }
        return new class($data, $this) {
            private $data;
            private $environment;

            public function __construct(array $data, $environment)
            {
                $this->data = $data;
                $this->environment = $environment;
            }

            public function render()
            {
                extract($this->data, EXTR_SKIP);
                $__env = $this->environment;
                $compiler = new Illuminate\View\Compilers\BladeCompiler(new Illuminate\Filesystem\Filesystem, __DIR__);
                $source = file_get_contents(dirname(__DIR__, 3).'/resources/views/web/admin-dashboard.blade.php');
                ob_start();
                eval('?>'.$compiler->compileString($source));
                return ob_get_clean();
            }
        };
    }
};
$source = file_get_contents(dirname(__DIR__, 3).'/resources/views/web/admin-screen.blade.php');
$start = strpos($source, "@section('content')") + strlen("@section('content')");
$content = substr($source, $start, strrpos($source, '@endsection') - $start);
eval('?>'.$compiler->compileString($content));

<?php
// Compile the actual navigation partials without booting Laravel or a database.
$options = json_decode($argv[1] ?? '{}', true);
class NavbarUser {
    public $name = 'Lautaro QA';
    public $permissions;
    public function __construct($permissions) { $this->permissions = $permissions; }
    public function hasPermission($permission) { return in_array('*', $this->permissions, true) || in_array($permission, $this->permissions, true); }
}
class Auth {
    public static $currentUser;
    public static function user() { return self::$currentUser; }
}
Auth::$currentUser = !empty($options['guest']) ? null : new NavbarUser($options['permissions'] ?? ['*']);
function auth() { return new class {
    public function guard($name = null) { return $this; }
    public function check() { return Auth::user() !== null; }
    public function guest() { return !$this->check(); }
}; }
function request() { return new class {
    public function is($pattern) { global $options; return fnmatch($pattern, trim($options['path'] ?? '/admin-web/products', '/')); }
    public function segment($index) { global $options; return explode('/', trim($options['path'] ?? '/admin-web/products', '/'))[$index - 1] ?? null; }
}; }
function url($path) { return $path; }
function route($name) { return '/' . $name; }
function csrf_field() { return '<input type="hidden" name="_token" value="fixture">'; }
require dirname(__DIR__, 3) . '/vendor/autoload.php';
$__env = new class { use Illuminate\View\Concerns\ManagesLoops; };
$compiler = new Illuminate\View\Compilers\BladeCompiler(new Illuminate\Filesystem\Filesystem, __DIR__);
$render = function ($name) use ($compiler, $__env) {
    eval('?>' . $compiler->compileString(file_get_contents(dirname(__DIR__, 3) . '/resources/views/partials/' . $name . '.blade.php')));
};
echo '<header class="site-navbar site-navbar-authenticated"><div class="navbar-inner"><nav class="primary-navigation"><ul class="nav__links">';
$render('portal-navbar');
echo '</ul></nav>';
$render('user-navbar-menu');
echo '</div></header><button id="outside">Fuera</button>';

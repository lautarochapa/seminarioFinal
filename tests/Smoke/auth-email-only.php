<?php

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$app->instance('env', 'production');
config(['session.driver' => 'array', 'cache.default' => 'array']);
view()->share('errors', new Illuminate\Support\ViewErrorBag);

foreach (['login', 'register'] as $screen) {
    $html = view('auth.'.$screen)->render();
    foreach (['g-signin', 'apis.google.com', 'google-signin', '/redirect', 'Facebook', 'onApiGoogleSignIn', 'Usuarios demo'] as $unsupported) {
        if (strpos($html, $unsupported) !== false) {
            throw new RuntimeException($screen.' still exposes '.$unsupported);
        }
    }
    foreach (['name="email"', 'name="password"', 'type="submit"', 'data-auth-session="true"', 'data-api-endpoint="/api/v1/auth/'.$screen.'"'] as $required) {
        if (strpos($html, $required) === false) {
            throw new RuntimeException($screen.' is missing '.$required);
        }
    }
}
echo "OK: login and registration render email/password forms without social sign-in or production demo accounts.\n";

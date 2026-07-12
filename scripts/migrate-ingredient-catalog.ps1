$ErrorActionPreference = "Stop"

$root = Split-Path -Parent $PSScriptRoot
$php = Join-Path $root ".runtime\php8229\php.exe"

if (-not (Test-Path $php)) {
    $php = "php"
}

Push-Location $root
try {
    & $php -d error_reporting=8191 -d display_errors=0 artisan config:clear
    & $php -d error_reporting=8191 -d display_errors=0 artisan migrate --path=database/migrations/2026_06_15_000004_create_ingredient_catalog_tables.php --force
    $code = @"
require 'vendor/autoload.php';
`$app = require 'bootstrap/app.php';
`$kernel = `$app->make(Illuminate\Contracts\Console\Kernel::class);
`$kernel->bootstrap();
require database_path('seeds/IngredientCatalogSeeder.php');
(new IngredientCatalogSeeder)->run();
echo 'IngredientCatalogSeeder completado'.PHP_EOL;
"@
    & $php -d error_reporting=8191 -d display_errors=0 -r $code
} finally {
    Pop-Location
}

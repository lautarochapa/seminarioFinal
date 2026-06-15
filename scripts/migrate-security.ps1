$ErrorActionPreference = "Stop"

$root = Split-Path -Parent $PSScriptRoot
$php = Join-Path $root ".runtime\php8229\php.exe"

if (-not (Test-Path $php)) {
    $php = "php"
}

Push-Location $root
try {
    & $php -d error_reporting=8191 -d display_errors=0 artisan config:clear
    & $php -d error_reporting=8191 -d display_errors=0 artisan migrate --path=database/migrations/2026_06_15_000001_create_security_tables.php --force
    & $php -d error_reporting=8191 -d display_errors=0 artisan db:seed --class=SecuritySeeder --force
} finally {
    Pop-Location
}

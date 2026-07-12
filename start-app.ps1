$ErrorActionPreference = "Stop"

$php = Join-Path $PSScriptRoot ".runtime\php8229\php.exe"

if (-not (Test-Path $php)) {
    $php = "php"
}

& $php -d error_reporting=8191 -d display_errors=0 artisan serve --host=127.0.0.1 --port=8000

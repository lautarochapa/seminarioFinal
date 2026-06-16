param(
    [string] $PhpPath = ".\.runtime\php8229\php.exe"
)

$ErrorActionPreference = "Stop"
$root = Split-Path -Parent $PSScriptRoot
Push-Location $root

try {
    & $PhpPath artisan migrate --path=database/migrations/2026_06_15_000019_seed_web_screen_permissions.php --force
}
finally {
    Pop-Location
}

param(
    [string] $PhpPath = ".\.runtime\php8229\php.exe"
)

$ErrorActionPreference = "Stop"
$root = Split-Path -Parent $PSScriptRoot
Push-Location $root

try {
    & $PhpPath artisan migrate --path=database/migrations/2026_06_15_000018_seed_demo_role_users.php --force
}
finally {
    Pop-Location
}

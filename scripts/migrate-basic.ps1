$ErrorActionPreference = "Stop"

$root = Split-Path -Parent $PSScriptRoot
$php = Join-Path $root ".runtime\php8229\php.exe"

if (-not (Test-Path $php)) {
    $php = "php"
}

Push-Location $root
try {
    & $php -d error_reporting=8191 -d display_errors=0 artisan config:clear
    & $php -d error_reporting=8191 -d display_errors=0 artisan migrate --path=database/migrations/2014_10_12_000000_create_users_table.php --force
    & $php -d error_reporting=8191 -d display_errors=0 artisan migrate --path=database/migrations/2014_10_12_100000_create_password_resets_table.php --force
    & $php -d error_reporting=8191 -d display_errors=0 artisan migrate --path=database/migrations/2020_07_02_230832_create_profiles_table.php --force
    & $php -d error_reporting=8191 -d display_errors=0 artisan db:seed --class=ProfileSeeder --force
} finally {
    Pop-Location
}

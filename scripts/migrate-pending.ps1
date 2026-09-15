$ErrorActionPreference = "Stop"

$root = Split-Path -Parent $PSScriptRoot
$php = Join-Path $root ".runtime\php8229\php.exe"
$migrationsDir = Join-Path $root "database\migrations"

if (-not (Test-Path $php)) {
    $php = "php"
}

Push-Location $root
try {
    & $php -d error_reporting=8191 -d display_errors=0 artisan config:clear
    if ($LASTEXITCODE -ne 0) {
        throw "No se pudo limpiar la configuracion de Laravel."
    }

    # Las migraciones 2020 pertenecen al esquema legacy y colisionan con las
    # tablas modernas. Bootstrap aplica las bases necesarias por separado;
    # desde aqui avanzamos solamente la linea actual del esquema.
    $migrations = Get-ChildItem $migrationsDir -File -Filter "2026_*.php" |
        Sort-Object Name

    foreach ($migration in $migrations) {
        $relativePath = "database/migrations/$($migration.Name)"
        Write-Host "Migrando $relativePath"
        & $php -d error_reporting=8191 -d display_errors=0 artisan migrate --path=$relativePath --force

        if ($LASTEXITCODE -ne 0) {
            throw "Fallo la migracion $relativePath"
        }
    }
} finally {
    Pop-Location
}

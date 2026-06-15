param(
    [string] $Database = "cccontrol",
    [string] $Username = "postgres",
    [string] $HostName = "127.0.0.1",
    [int] $Port = 5432,
    [string] $PostgresBin = "C:\Program Files\PostgreSQL\18\bin"
)

$ErrorActionPreference = "Stop"

$createdb = Join-Path $PostgresBin "createdb.exe"
$psql = Join-Path $PostgresBin "psql.exe"

if (-not (Test-Path $createdb)) {
    throw "No se encontro createdb.exe en $PostgresBin"
}

if (-not (Test-Path $psql)) {
    throw "No se encontro psql.exe en $PostgresBin"
}

$exists = & $psql -h $HostName -p $Port -U $Username -d postgres -tAc "SELECT 1 FROM pg_database WHERE datname = '$Database'"

if ($LASTEXITCODE -ne 0) {
    throw "No se pudo consultar PostgreSQL. Verifica usuario/password y que el servicio este iniciado."
}

if ($exists -eq "1") {
    Write-Host "La base '$Database' ya existe."
} else {
    & $createdb -h $HostName -p $Port -U $Username $Database
    Write-Host "Base '$Database' creada."
}

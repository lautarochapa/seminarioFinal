param(
    [Parameter(Mandatory = $true)]
    [ValidateSet("local", "qa")]
    [string] $Environment
)

$ErrorActionPreference = "Stop"

$root = Split-Path -Parent $PSScriptRoot
$source = Join-Path $root ".env.$Environment.example"
$target = Join-Path $root ".env"

if (-not (Test-Path $source)) {
    throw "No se encontro $source"
}

Copy-Item -LiteralPath $source -Destination $target -Force
Write-Host "Configuracion activa: $Environment -> .env"

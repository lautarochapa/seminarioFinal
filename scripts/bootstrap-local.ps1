param(
    [string] $Database = "cccontrol",
    [string] $DbUsername = "postgres",
    [string] $PostgresBin = "C:\Program Files\PostgreSQL\18\bin"
)

$ErrorActionPreference = "Stop"

$root = Split-Path -Parent $PSScriptRoot

& (Join-Path $PSScriptRoot "use-env.ps1") -Environment local
& (Join-Path $PSScriptRoot "init-local-postgres.ps1") -Database $Database -Username $DbUsername -PostgresBin $PostgresBin
& (Join-Path $PSScriptRoot "migrate-basic.ps1")
& (Join-Path $PSScriptRoot "create-admin-user.ps1")

Write-Host "Setup local terminado. Ejecuta .\start-app.ps1 para levantar la app."

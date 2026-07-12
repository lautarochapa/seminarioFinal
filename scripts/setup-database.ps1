param(
    [string] $Database = "cccontrol",
    [string] $DbUsername = "postgres",
    [string] $DbPassword = "1234",
    [string] $PostgresBin = "C:\Program Files\PostgreSQL\18\bin"
)

$ErrorActionPreference = "Stop"

& (Join-Path $PSScriptRoot "bootstrap-local.ps1") -Database $Database -DbUsername $DbUsername -DbPassword $DbPassword -PostgresBin $PostgresBin

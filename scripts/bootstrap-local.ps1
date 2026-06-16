param(
    [string] $Database = "cccontrol",
    [string] $DbUsername = "postgres",
    [string] $DbPassword = "1234",
    [string] $PostgresBin = "C:\Program Files\PostgreSQL\18\bin"
)

$ErrorActionPreference = "Stop"

$root = Split-Path -Parent $PSScriptRoot

& (Join-Path $PSScriptRoot "use-env.ps1") -Environment local
& (Join-Path $PSScriptRoot "init-local-postgres.ps1") -Database $Database -Username $DbUsername -Password $DbPassword -PostgresBin $PostgresBin
& (Join-Path $PSScriptRoot "migrate-basic.ps1")
& (Join-Path $PSScriptRoot "migrate-security.ps1")
& (Join-Path $PSScriptRoot "migrate-family-groups.ps1")
& (Join-Path $PSScriptRoot "migrate-user-profiles.ps1")
& (Join-Path $PSScriptRoot "migrate-ingredient-catalog.ps1")
& (Join-Path $PSScriptRoot "migrate-ingredient-taxonomies.ps1")
& (Join-Path $PSScriptRoot "migrate-product-catalog.ps1")
& (Join-Path $PSScriptRoot "migrate-supermarkets.ps1")
& (Join-Path $PSScriptRoot "migrate-scraping.ps1")
& (Join-Path $PSScriptRoot "migrate-stock.ps1")
& (Join-Path $PSScriptRoot "migrate-recipes.ps1")
& (Join-Path $PSScriptRoot "migrate-meal-plans.ps1")
& (Join-Path $PSScriptRoot "migrate-shopping.ps1")
& (Join-Path $PSScriptRoot "migrate-budget.ps1")
& (Join-Path $PSScriptRoot "migrate-supplements.ps1")
& (Join-Path $PSScriptRoot "migrate-supporting-system.ps1")
& (Join-Path $PSScriptRoot "migrate-actor-roles.ps1")
& (Join-Path $PSScriptRoot "migrate-api-tokens.ps1")
& (Join-Path $PSScriptRoot "migrate-demo-users.ps1")
& (Join-Path $PSScriptRoot "migrate-web-screen-permissions.ps1")
& (Join-Path $PSScriptRoot "create-admin-user.ps1")

Write-Host "Setup local terminado. Ejecuta .\start-app.ps1 para levantar la app."

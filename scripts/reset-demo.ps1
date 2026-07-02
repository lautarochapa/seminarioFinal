<#
.SYNOPSIS
    Restablece el entorno demo a un estado limpio con datos de prueba.

.DESCRIPTION
    Ejecuta migrate:fresh --seed, limpia caches y verifica conteos minimos.
    ADVERTENCIA: Borra toda la base de datos y la recrea desde cero.
#>

param(
    [switch]$Force
)

$php  = 'C:\xampp\php74\php.exe'
$root = Split-Path $PSScriptRoot -Parent
$artisan = Join-Path $root 'artisan'
$demoEval = Join-Path $root 'scripts\demo-eval.php'

function Stop-WithError([string]$msg) {
    Write-Host "[ERROR] $msg" -ForegroundColor Red
    exit 1
}

function Invoke-TinkerValue([string]$expression) {
    $raw = & $php $demoEval $expression 2>&1
    if ($LASTEXITCODE -ne 0) {
        return ''
    }

    return (($raw | ForEach-Object { "$_" }) -join "`n").Trim()
}

if (-not (Test-Path $php)) { Stop-WithError "PHP 7.4 no encontrado en $php" }
if (-not (Test-Path $artisan)) { Stop-WithError "artisan no encontrado en $root" }

Write-Host ""
Write-Host "========================================================" -ForegroundColor Cyan
Write-Host "  CC Control - Reset Demo" -ForegroundColor Cyan
Write-Host "========================================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "ADVERTENCIA: esto borrara toda la base de datos." -ForegroundColor Yellow
if (-not $Force) {
    Write-Host "Presione Enter para continuar o Ctrl+C para cancelar." -ForegroundColor Yellow
    $null = Read-Host
} else {
    Write-Host "Modo -Force activo: no se solicita confirmacion interactiva." -ForegroundColor Yellow
}

Write-Host ""
Write-Host "-- 1. migrate:fresh --seed -----------------------------" -ForegroundColor Cyan
& $php $artisan migrate:fresh --seed --force 2>&1 | ForEach-Object { Write-Host "  $_" }
if ($LASTEXITCODE -ne 0) { Stop-WithError "migrate:fresh --seed fallo. Revisa el output anterior." }
Write-Host "  [OK] Migraciones y seeds completados." -ForegroundColor Green

Write-Host ""
Write-Host "-- 2. optimize:clear -----------------------------------" -ForegroundColor Cyan
& $php $artisan optimize:clear 2>&1 | ForEach-Object { Write-Host "  $_" }
if ($LASTEXITCODE -ne 0) { Stop-WithError "optimize:clear fallo. Revisa el output anterior." }
Write-Host "  [OK] Caches limpiados." -ForegroundColor Green

Write-Host ""
Write-Host "-- 3. storage:link -------------------------------------" -ForegroundColor Cyan
& $php $artisan storage:link 2>&1 | ForEach-Object { Write-Host "  $_" }
Write-Host "  [OK] Storage link verificado." -ForegroundColor Green

Write-Host ""
Write-Host "-- 4. Verificacion de datos demo -----------------------" -ForegroundColor Cyan

$checks = @(
    @{ label = 'Usuarios demo';       query = "DB::table('users')->whereIn('email',['usuario@cccontrol.test','superadmin@cccontrol.test'])->count()" },
    @{ label = 'Grupos familiares';   query = "DB::table('family_groups')->count()" },
    @{ label = 'Ingredientes';        query = "DB::table('ingredients')->count()" },
    @{ label = 'Productos';           query = "DB::table('products')->count()" },
    @{ label = 'Recetas';             query = "DB::table('recipes')->count()" },
    @{ label = 'Ubicaciones stock';   query = "DB::table('stock_locations')->count()" },
    @{ label = 'Items de stock';      query = "DB::table('stock_items')->count()" },
    @{ label = 'Listas de compras';   query = "DB::table('shopping_lists')->count()" },
    @{ label = 'Presupuestos';        query = "DB::table('budgets')->count()" },
    @{ label = 'Supermercados';       query = "DB::table('supermarket_chains')->count()" },
    @{ label = 'Sucursales';          query = "DB::table('supermarket_branches')->count()" },
    @{ label = 'Metodos de pago';     query = "DB::table('payment_methods')->count()" },
    @{ label = 'Roles';               query = "DB::table('roles')->count()" },
    @{ label = 'Permisos';            query = "DB::table('permissions')->count()" }
)

$allOk = $true
foreach ($check in $checks) {
    $count = Invoke-TinkerValue $check.query
    if ($count -match '^\d+$' -and [int]$count -gt 0) {
        Write-Host ("  [OK] {0,-25} {1}" -f $check.label, $count) -ForegroundColor Green
    } else {
        Write-Host ("  [FAIL] {0,-23} 0" -f $check.label) -ForegroundColor Red
        $allOk = $false
    }
}

Write-Host ""
Write-Host "-- 5. Credenciales demo --------------------------------" -ForegroundColor Cyan
Write-Host "  usuario@cccontrol.test             password123   user" -ForegroundColor White
Write-Host "  dietologo@cccontrol.test           password123   dietologist" -ForegroundColor White
Write-Host "  catalogo@cccontrol.test            password123   catalog_admin" -ForegroundColor White
Write-Host "  supermercados@cccontrol.test       password123   supermarket_admin" -ForegroundColor White
Write-Host "  recetas@cccontrol.test             password123   recipe_admin" -ForegroundColor White
Write-Host "  docente@cccontrol.test             password123   teacher" -ForegroundColor White
Write-Host "  superadmin@cccontrol.test          password123   super_admin" -ForegroundColor White
Write-Host "  sistema@cccontrol.test             password123   system_jobs" -ForegroundColor White
Write-Host "  admin@cccontrol.test               password123   super_admin" -ForegroundColor White
Write-Host ""
Write-Host "  URL web: http://localhost/web" -ForegroundColor Cyan
Write-Host "  URL admin: http://localhost/admin-web" -ForegroundColor Cyan
Write-Host "  URL API: http://localhost/api/v1" -ForegroundColor Cyan
Write-Host ""

Write-Host "========================================================" -ForegroundColor Cyan
if ($allOk) {
    Write-Host "  Reset completo. Entorno demo listo." -ForegroundColor Green
    Write-Host "========================================================" -ForegroundColor Cyan
    exit 0
}

Write-Host "  Reset incompleto. Revisa los datos en 0." -ForegroundColor Red
Write-Host "========================================================" -ForegroundColor Cyan
exit 1

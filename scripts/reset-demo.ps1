<#
.SYNOPSIS
    Restablece el entorno demo a un estado limpio con datos de prueba.

.DESCRIPTION
    Ejecuta migrate:fresh --seed, limpia caches y verifica conteos mínimos.
    ADVERTENCIA: Borra toda la base de datos y la recrea desde cero.

.EXAMPLE
    powershell -ExecutionPolicy Bypass -File .\scripts\reset-demo.ps1
#>

$php  = 'C:\xampp\php74\php.exe'
$root = Split-Path $PSScriptRoot -Parent

if (-not (Test-Path $php)) {
    Write-Host "[ERROR] PHP 7.4 no encontrado en $php" -ForegroundColor Red
    exit 1
}

$artisan = Join-Path $root 'artisan'
if (-not (Test-Path $artisan)) {
    Write-Host "[ERROR] artisan no encontrado en $root" -ForegroundColor Red
    exit 1
}

Write-Host ""
Write-Host "═══════════════════════════════════════════════" -ForegroundColor Cyan
Write-Host "  CC Control — Reset Demo" -ForegroundColor Cyan
Write-Host "═══════════════════════════════════════════════" -ForegroundColor Cyan
Write-Host ""
Write-Host "ADVERTENCIA: esto borrará toda la base de datos." -ForegroundColor Yellow
Write-Host "Presione Enter para continuar o Ctrl+C para cancelar." -ForegroundColor Yellow
$null = Read-Host

# ── 1. Migrate fresh + seed ───────────────────────────────────────────────────
Write-Host ""
Write-Host "── 1. migrate:fresh --seed ──────────────────────" -ForegroundColor Cyan

& $php $artisan migrate:fresh --seed --force 2>&1 | ForEach-Object { Write-Host "  $_" }

if ($LASTEXITCODE -ne 0) {
    Write-Host ""
    Write-Host "[ERROR] migrate:fresh --seed falló. Revisá el output anterior." -ForegroundColor Red
    exit 1
}

Write-Host "  [OK] Migraciones y seeds completados." -ForegroundColor Green

# ── 2. Clear caches ───────────────────────────────────────────────────────────
Write-Host ""
Write-Host "── 2. optimize:clear ────────────────────────────" -ForegroundColor Cyan

& $php $artisan optimize:clear 2>&1 | ForEach-Object { Write-Host "  $_" }

Write-Host "  [OK] Caches limpiados." -ForegroundColor Green

# ── 3. Storage link ───────────────────────────────────────────────────────────
Write-Host ""
Write-Host "── 3. storage:link ──────────────────────────────" -ForegroundColor Cyan

& $php $artisan storage:link 2>&1 | ForEach-Object { Write-Host "  $_" }

Write-Host "  [OK] Storage link verificado." -ForegroundColor Green

# ── 4. Data verification ──────────────────────────────────────────────────────
Write-Host ""
Write-Host "── 4. Verificación de datos demo ────────────────" -ForegroundColor Cyan

$checks = @(
    @{ label = 'Usuarios demo';       query = "echo DB::table('users')->whereIn('email',['usuario@cccontrol.test','superadmin@cccontrol.test'])->count();" },
    @{ label = 'Grupos familiares';   query = "echo DB::table('family_groups')->count();" },
    @{ label = 'Ingredientes';        query = "echo DB::table('ingredients')->count();" },
    @{ label = 'Productos';           query = "echo DB::table('products')->count();" },
    @{ label = 'Recetas';             query = "echo DB::table('recipes')->count();" },
    @{ label = 'Ubicaciones stock';   query = "echo DB::table('stock_locations')->count();" },
    @{ label = 'Items de stock';      query = "echo DB::table('stock_items')->count();" },
    @{ label = 'Listas de compras';   query = "echo DB::table('shopping_lists')->count();" },
    @{ label = 'Presupuestos';        query = "echo DB::table('budgets')->count();" },
    @{ label = 'Supermercados';       query = "echo DB::table('supermarket_chains')->count();" },
    @{ label = 'Sucursales';          query = "echo DB::table('supermarket_branches')->count();" },
    @{ label = 'Metodos de pago';     query = "echo DB::table('payment_methods')->count();" },
    @{ label = 'Roles';               query = "echo DB::table('roles')->count();" },
    @{ label = 'Permisos';            query = "echo DB::table('permissions')->count();" }
)

$allOk = $true
foreach ($check in $checks) {
    $result = & $php $artisan tinker --execute=$check.query 2>&1
    $match  = $result | Select-String -Pattern '\b(\d+)\b'
    $count  = if ($match) { $match.Matches[0].Groups[1].Value } else { '0' }
    if ($count -match '^\d+$' -and [int]$count -gt 0) {
        Write-Host ("  [OK] {0,-25} {1}" -f $check.label, $count) -ForegroundColor Green
    } else {
        Write-Host ("  [WARN] {0,-23} 0 (puede ser esperado)" -f $check.label) -ForegroundColor Yellow
        $allOk = $false
    }
}

# ── 5. Demo credentials ───────────────────────────────────────────────────────
Write-Host ""
Write-Host "── 5. Credenciales demo ─────────────────────────" -ForegroundColor Cyan
Write-Host ""
Write-Host "  Email                              Contraseña    Rol" -ForegroundColor White
Write-Host "  ─────────────────────────────────────────────────────────────────" -ForegroundColor Gray
Write-Host "  usuario@cccontrol.test             password123   user" -ForegroundColor White
Write-Host "  dietologo@cccontrol.test           password123   dietologist" -ForegroundColor White
Write-Host "  catalogo@cccontrol.test            password123   catalog_admin" -ForegroundColor White
Write-Host "  supermercados@cccontrol.test       password123   supermarket_admin" -ForegroundColor White
Write-Host "  recetas@cccontrol.test             password123   recipe_admin" -ForegroundColor White
Write-Host "  docente@cccontrol.test             password123   teacher" -ForegroundColor White
Write-Host "  superadmin@cccontrol.test          password123   super_admin" -ForegroundColor White
Write-Host ""
Write-Host "  URL web: http://localhost/web" -ForegroundColor Cyan
Write-Host "  URL admin: http://localhost/admin-web" -ForegroundColor Cyan
Write-Host "  URL API: http://localhost/api/v1" -ForegroundColor Cyan
Write-Host ""

# ── Summary ───────────────────────────────────────────────────────────────────
Write-Host "═══════════════════════════════════════════════" -ForegroundColor Cyan

if ($allOk) {
    Write-Host "  Reset completo. Entorno demo listo." -ForegroundColor Green
} else {
    Write-Host "  Reset completado con advertencias. Revisá los datos en 0." -ForegroundColor Yellow
}

Write-Host "═══════════════════════════════════════════════" -ForegroundColor Cyan
Write-Host ""
exit 0

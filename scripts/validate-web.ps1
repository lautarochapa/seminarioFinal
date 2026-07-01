#Requires -Version 5.1
<#
.SYNOPSIS
    Valida la capa web: rutas, screenKeys, scripts, permisos y tests.
    Exit code 0 = todo OK. Exit code 1 = algún fallo.
#>

$ErrorActionPreference = 'Continue'
$root = Split-Path -Parent $PSScriptRoot
$php  = 'C:\xampp\php74\php.exe'
$artisan = Join-Path $root 'artisan'

$pass = 0
$fail = 0

function Pass($msg) { Write-Host "[PASS] $msg" -ForegroundColor Green;  $script:pass++ }
function Fail($msg) { Write-Host "[FAIL] $msg" -ForegroundColor Red;    $script:fail++ }
function Info($msg) { Write-Host "       $msg"  -ForegroundColor Cyan }

# ────────────────────────────────────────────────────────────────
# 1. Rutas web registradas
# ────────────────────────────────────────────────────────────────
Write-Host "`n[1] Rutas web registradas" -ForegroundColor Yellow

$routeList = & $php $artisan route:list --path=web --columns=uri 2>&1 | Out-String
if ($routeList -match 'web/\{screen\}') {
    Pass "Ruta generica web/{screen} registrada"
} else {
    Fail "Ruta web/{screen} NO encontrada en route:list"
}
if ($routeList -match '\bweb\b') {
    Pass "Ruta web (dashboard) registrada"
} else {
    Fail "Ruta web (dashboard) NO encontrada"
}

# ────────────────────────────────────────────────────────────────
# 2. Screen keys en UserWebScreenController
# ────────────────────────────────────────────────────────────────
Write-Host "`n[2] Screen keys - UserWebScreenController" -ForegroundColor Yellow

$controllerUser = Get-Content (Join-Path $root 'app\Http\Controllers\UserWebScreenController.php') -Raw
$expectedUserScreens = @(
    'dashboard','stock','recipes','recipe-search','recipe-favorites','recipe-suggestions',
    'planning','shopping-list','shopping-session','purchases','notifications','supplements',
    'budget','reports','family-group','profile-objectives','payment-methods',
    'professional-permissions','catalog','barcode-scanner','supermarkets','branches'
)
foreach ($s in $expectedUserScreens) {
    if ($controllerUser -match "'$s'") {
        Pass "UserWebScreenController tiene screen key '$s'"
    } else {
        Fail "UserWebScreenController FALTA screen key '$s'"
    }
}

# ────────────────────────────────────────────────────────────────
# 3. Screen keys en AdminWebScreenController
# ────────────────────────────────────────────────────────────────
Write-Host "`n[3] Screen keys - AdminWebScreenController" -ForegroundColor Yellow

$controllerAdmin = Get-Content (Join-Path $root 'app\Http\Controllers\AdminWebScreenController.php') -Raw
$expectedAdminScreens = @(
    'dashboard','users','roles-permissions','objectives','health-preferences',
    'ingredients','ingredient-categories','nutrients','units-conversions','equivalences',
    'food-tags','meal-types','brands','product-categories','products','barcodes',
    'supermarkets','branches','prices','promotions','supermarket-products','payment-methods',
    'price-refresh-requests','scraped-products','recipe-scraping','supermarket-scraping',
    'scraping-alerts','recipe-tags','recipe-categories','official-recipes','imported-recipes',
    'recipe-import','recipe-import-text','admin-reports','audit','settings',
    'feature-flags','ai-foundation','thesis-docs','demo-scenarios','product-reports','cities'
)
foreach ($s in $expectedAdminScreens) {
    if ($controllerAdmin -match "'$s'") {
        Pass "AdminWebScreenController tiene screen key '$s'"
    } else {
        Fail "AdminWebScreenController FALTA screen key '$s'"
    }
}

# ────────────────────────────────────────────────────────────────
# 4. Blade sections - user-screen.blade.php
# ────────────────────────────────────────────────────────────────
Write-Host "`n[4] Secciones en user-screen.blade.php" -ForegroundColor Yellow

$bladeUser = Get-Content (Join-Path $root 'resources\views\web\user-screen.blade.php') -Raw
$expectedUserSections = @{
    'stock'                    = 'data-user-stock'
    'recipes'                  = 'data-user-recipes'
    'recipe-search'            = 'data-recipe-search'
    'recipe-favorites'         = 'data-user-fav'
    'recipe-suggestions'       = 'data-recipe-sugg'
    'planning'                 = 'data-user-meal-plans'
    'shopping-list'            = 'data-user-shopping-list'
    'shopping-session'         = 'data-user-shopping-session'
    'purchases'                = 'data-user-purchases'
    'notifications'            = 'data-user-notifications'
    'supplements'              = 'data-user-supplements'
    'budget'                   = 'data-user-budget'
    'reports'                  = 'data-user-reports'
    'family-group'             = 'data-family-groups'
    'profile-objectives'       = 'data-user-profile'
    'payment-methods'          = 'data-user-payment-methods'
    'professional-permissions' = 'data-professional-links'
    'catalog'                  = 'data-user-catalog'
    'barcode-scanner'          = 'data-user-barcode'
    'supermarkets'             = 'data-user-supermarkets'
    'branches'                 = 'data-user-branches'
}
foreach ($key in $expectedUserSections.Keys) {
    $dataAttr = $expectedUserSections[$key]
    if ($bladeUser -match "screenKey === '$key'") {
        if ($bladeUser -match $dataAttr) {
            Pass "user-screen '$key' tiene seccion con $dataAttr"
        } else {
            Fail "user-screen '$key' existe pero FALTA atributo $dataAttr"
        }
    } else {
        Fail "user-screen FALTA seccion para '$key'"
    }
}

# ────────────────────────────────────────────────────────────────
# 5. Scripts JS en web-user.blade.php
# ────────────────────────────────────────────────────────────────
Write-Host "`n[5] Scripts JS en web-user.blade.php" -ForegroundColor Yellow

$layout = Get-Content (Join-Path $root 'resources\views\layouts\web-user.blade.php') -Raw
$expectedScripts = @(
    'api-client.js','auth-api.js','family-groups.js','user-profile.js',
    'user-catalog.js','user-barcode.js','user-supermarkets.js','user-branches.js',
    'user-payment-methods.js','user-stock-locations.js','user-recipes.js',
    'user-recipe-favorites.js','user-recipe-search.js','user-recipe-suggestions.js',
    'user-meal-plans.js','user-notifications.js','user-reports.js','user-supplements.js',
    'user-budget.js','user-purchases.js','user-shopping-session.js',
    'user-shopping-lists.js','professional-links.js','shopping-alternatives.js','shopping-compare.js'
)
foreach ($s in $expectedScripts) {
    if ($layout -match $s) {
        Pass "Layout carga $s"
    } else {
        Fail "Layout NO carga $s"
    }
}

# ────────────────────────────────────────────────────────────────
# 6. Archivos JS existen en disco
# ────────────────────────────────────────────────────────────────
Write-Host "`n[6] Archivos JS existen en disco" -ForegroundColor Yellow

$jsDir = Join-Path $root 'public\js'
$criticalJs = @(
    'api-client.js','auth-api.js','family-groups.js','user-profile.js',
    'user-catalog.js','user-barcode.js','user-supermarkets.js','user-branches.js',
    'user-payment-methods.js','user-stock-locations.js','user-recipes.js',
    'user-recipe-favorites.js','user-recipe-search.js','user-recipe-suggestions.js',
    'user-meal-plans.js','user-notifications.js','user-reports.js','user-supplements.js',
    'user-budget.js','user-purchases.js','user-shopping-session.js',
    'user-shopping-lists.js','professional-links.js','shopping-alternatives.js','shopping-compare.js'
)
foreach ($f in $criticalJs) {
    $path = Join-Path $jsDir $f
    if (Test-Path $path) {
        Pass "Archivo JS existe: $f"
    } else {
        Fail "Archivo JS FALTANTE: $f"
    }
}

# ────────────────────────────────────────────────────────────────
# 7. Verificar que no hay fetch() directo en JS de usuario
# ────────────────────────────────────────────────────────────────
Write-Host "`n[7] Sin fetch() directo en JS de usuario" -ForegroundColor Yellow

$userJsFiles = Get-ChildItem $jsDir -Filter 'user-*.js'
$fetchFiles = @()
foreach ($f in $userJsFiles) {
    $content = Get-Content $f.FullName -Raw
    if ($content -match '\bfetch\s*\(') { $fetchFiles += $f.Name }
}
if ($fetchFiles.Count -eq 0) {
    Pass "Ningún user-*.js usa fetch() directamente"
} else {
    Fail "Archivos con fetch() directo: $($fetchFiles -join ', ')"
}

# ────────────────────────────────────────────────────────────────
# 8. Verificar /api/v1 prefix en llamadas CCApi
# ────────────────────────────────────────────────────────────────
Write-Host "`n[8] Prefijo /api/v1 en family-groups.js" -ForegroundColor Yellow

$fgJs = Get-Content (Join-Path $jsDir 'family-groups.js') -Raw
if ($fgJs -match "var API = '/api/v1'") {
    Pass "family-groups.js usa var API = '/api/v1'"
} else {
    Fail "family-groups.js NO tiene var API = '/api/v1'"
}
if ($fgJs -match "request\('/") {
    Fail "family-groups.js tiene llamadas sin prefijo /api/v1"
} else {
    Pass "family-groups.js no tiene rutas desnudas en request()"
}

# ────────────────────────────────────────────────────────────────
# 9. Links del navbar - todas las URLs existen en el controller
# ────────────────────────────────────────────────────────────────
Write-Host "`n[9] Links navbar vs screen keys" -ForegroundColor Yellow

$navbar = Get-Content (Join-Path $root 'resources\views\partials\portal-navbar.blade.php') -Raw
$navUserUrls = [regex]::Matches($navbar, "'/web/([^']+)'") | ForEach-Object { $_.Groups[1].Value }

foreach ($url in $navUserUrls) {
    if ($url -eq '') { continue }
    $screenKey = $url
    if ($controllerUser -match "'$screenKey'") {
        Pass "Navbar /web/$screenKey tiene screen key en controller"
    } else {
        Fail "Navbar /web/$screenKey NO tiene screen key en controller"
    }
}

# ────────────────────────────────────────────────────────────────
# 10. Migración de permisos clave
# ────────────────────────────────────────────────────────────────
Write-Host "`n[10] Migraciones de permisos web" -ForegroundColor Yellow

$migrationsDir = Join-Path $root 'database\migrations'
$permMigrations = @{
    '000019' = 'shopping-list'
    '000022' = 'barcode-scanner'
    '000025' = 'supermarkets'
    '000026' = 'branches'
    '000028' = 'recipe-search'
    '000029' = 'recipe-suggestions'
    '000030' = 'recipe-favorites'
    '000033' = 'shopping-session'
    '000034' = 'purchases'
    '000035' = 'supplements'
    '000036' = 'notifications'
}
foreach ($num in $permMigrations.Keys) {
    $screen = $permMigrations[$num]
    $files = Get-ChildItem $migrationsDir -Filter "*$num*"
    if ($files.Count -gt 0) {
        Pass "Migracion permiso '$screen' (${num}) existe: $($files[0].Name)"
    } else {
        Fail "Migracion permiso '$screen' (${num}) NO encontrada"
    }
}

# ────────────────────────────────────────────────────────────────
# 11. Tests web
# ────────────────────────────────────────────────────────────────
Write-Host "`n[11] Tests web" -ForegroundColor Yellow

$testFiles = @(
    'tests\Feature\WebUserScreensRouteTest.php',
    'tests\Feature\WebPurchasesRouteTest.php',
    'tests\Feature\WebShoppingSessionRouteTest.php'
)
foreach ($f in $testFiles) {
    $path = Join-Path $root $f
    if (Test-Path $path) {
        Pass "Test existe: $(Split-Path $f -Leaf)"
    } else {
        Fail "Test FALTANTE: $f"
    }
}

$phpunit = Join-Path $root 'vendor\phpunit\phpunit\phpunit'
$testOutput = & $php $phpunit 'tests\Feature\WebUserScreensRouteTest.php' 'tests\Feature\WebPurchasesRouteTest.php' 'tests\Feature\WebShoppingSessionRouteTest.php' '--colors=never' 2>&1 | Out-String

if ($testOutput -match 'FAILURES|Error') {
    Fail "Tests web FALLARON"
    Info $testOutput.Trim()
} else {
    $summary = ([regex]::Match($testOutput, 'OK \(.+\)')).Value
    Pass "Tests web pasaron: $summary"
}

# ────────────────────────────────────────────────────────────────
# 12. Hero buttons tienen data attributes y listeners
# ────────────────────────────────────────────────────────────────
Write-Host "`n[12] Hero buttons — data attributes en blades" -ForegroundColor Yellow

$adminBlade = Get-Content (Join-Path $root 'resources\views\web\admin-screen.blade.php') -Raw
if ($adminBlade -match 'data-screen-primary-action') {
    Pass "admin-screen.blade.php tiene data-screen-primary-action"
} else {
    Fail "admin-screen.blade.php NO tiene data-screen-primary-action"
}
if ($adminBlade -match 'data-screen-secondary-action') {
    Pass "admin-screen.blade.php tiene data-screen-secondary-action"
} else {
    Fail "admin-screen.blade.php NO tiene data-screen-secondary-action"
}

$userBlade = Get-Content (Join-Path $root 'resources\views\web\user-screen.blade.php') -Raw
if ($userBlade -match 'data-screen-primary-action') {
    Pass "user-screen.blade.php tiene data-screen-primary-action"
} else {
    Fail "user-screen.blade.php NO tiene data-screen-primary-action"
}
if ($userBlade -match 'data-screen-secondary-action') {
    Pass "user-screen.blade.php tiene data-screen-secondary-action"
} else {
    Fail "user-screen.blade.php NO tiene data-screen-secondary-action"
}

# ────────────────────────────────────────────────────────────────
# 13. Admin JS files wire hero button listeners
# ────────────────────────────────────────────────────────────────
Write-Host "`n[13] Admin JS — listener en data-screen-primary-action" -ForegroundColor Yellow

$adminJsFiles = @(
    'admin-products.js',
    'admin-ingredients.js',
    'admin-ingredient-categories.js',
    'admin-nutrients.js',
    'admin-food-tags.js',
    'admin-brands.js',
    'admin-product-categories.js',
    'admin-barcodes.js'
)
foreach ($f in $adminJsFiles) {
    $path = Join-Path $jsDir $f
    if (-not (Test-Path $path)) {
        Fail "$f no existe en disco"
        continue
    }
    $content = Get-Content $path -Raw
    if ($content -match 'data-screen-primary-action') {
        Pass "$f tiene listener data-screen-primary-action"
    } else {
        Fail "$f NO tiene listener data-screen-primary-action"
    }
}

# ────────────────────────────────────────────────────────────────
# 14. No href="#" sin data attributes en blades principales
# ────────────────────────────────────────────────────────────────
Write-Host "`n[14] No href='#' sin data-* en blades" -ForegroundColor Yellow

foreach ($bladePair in @(
    @{file='resources\views\web\admin-screen.blade.php'; label='admin-screen'},
    @{file='resources\views\web\user-screen.blade.php'; label='user-screen'}
)) {
    $bladeContent = Get-Content (Join-Path $root $bladePair.file) -Raw
    # Find href="#" lines that don't have data- attribute on the same line
    $lines = $bladeContent -split "`n"
    $bareHrefs = @()
    foreach ($line in $lines) {
        if ($line -match "href=['""]#['""]" -and $line -notmatch 'data-') {
            $bareHrefs += $line.Trim()
        }
    }
    if ($bareHrefs.Count -eq 0) {
        Pass "$($bladePair.label): no hay href='#' sin data-*"
    } else {
        Fail "$($bladePair.label): $($bareHrefs.Count) href='#' sin data-* encontrados"
        foreach ($l in $bareHrefs[0..2]) { Write-Host "       $l" -ForegroundColor DarkYellow }
    }
}

# ────────────────────────────────────────────────────────────────
# RESULTADO FINAL
# ────────────────────────────────────────────────────────────────
Write-Host ""
Write-Host "================================" -ForegroundColor White
Write-Host "  RESULTADO: $pass PASS / $fail FAIL" -ForegroundColor $(if ($fail -eq 0) { 'Green' } else { 'Red' })
Write-Host "================================" -ForegroundColor White

if ($fail -gt 0) { exit 1 } else { exit 0 }

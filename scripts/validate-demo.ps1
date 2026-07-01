<#
.SYNOPSIS
    Validates the demo environment: DB, users, roles, permissions, routes, JS files.

.DESCRIPTION
    Checks all static and structural requirements for the demo to work end-to-end.
    Does NOT simulate browser clicks.
    Exits with code 1 on any failure.

.EXAMPLE
    powershell -ExecutionPolicy Bypass -File .\scripts\validate-demo.ps1
#>

$php    = 'C:\xampp\php74\php.exe'
$root   = Split-Path $PSScriptRoot -Parent
$errors = [System.Collections.Generic.List[string]]::new()
$passed = 0

function Pass([string]$msg) {
    Write-Host "  [PASS] $msg" -ForegroundColor Green
    $script:passed++
}

function Fail([string]$msg) {
    Write-Host "  [FAIL] $msg" -ForegroundColor Red
    $script:errors.Add($msg)
}

function Section([string]$title) {
    Write-Host ""
    Write-Host "── $title ──────────────────────────────────────" -ForegroundColor Cyan
}

# ── 1. PHP availability ──────────────────────────────────────────────────────
Section "PHP"

if (Test-Path $php) {
    $ver = & $php -r "echo PHP_VERSION;" 2>$null
    Pass "PHP found: $ver"
} else {
    Fail "PHP not found at $php"
}

# ── 2. Laravel bootstrap ─────────────────────────────────────────────────────
Section "Laravel"

$artisan = Join-Path $root 'artisan'
if (Test-Path $artisan) {
    $lver = & $php $artisan --version 2>$null
    if ($LASTEXITCODE -eq 0) {
        Pass "Laravel bootstraps: $lver"
    } else {
        Fail "artisan failed to execute"
    }
} else {
    Fail "artisan not found"
}

# ── 3. Database connection ────────────────────────────────────────────────────
Section "Database connection"

$dbCheck = & $php $artisan tinker --execute="echo DB::connection()->getPdo() ? 'ok' : 'fail';" 2>&1
if ($dbCheck -match 'ok') {
    Pass "Database connection OK"
} else {
    Fail "Database connection FAILED. Check .env DB settings."
}

# ── 4. Demo users ─────────────────────────────────────────────────────────────
Section "Demo users"

$demoEmails = @(
    'usuario@cccontrol.test',
    'dietologo@cccontrol.test',
    'catalogo@cccontrol.test',
    'supermercados@cccontrol.test',
    'recetas@cccontrol.test',
    'docente@cccontrol.test',
    'superadmin@cccontrol.test',
    'sistema@cccontrol.test',
    'admin@cccontrol.test'
)

foreach ($email in $demoEmails) {
    $exists = & $php $artisan tinker --execute="echo DB::table('users')->where('email','$email')->exists() ? 'yes' : 'no';" 2>&1
    if ($exists -match 'yes') {
        Pass "User: $email"
    } else {
        Fail "Missing user: $email"
    }
}

# ── 4b. admin local deep check ───────────────────────────────────────────────
Section 'Admin local (admin@cccontrol.test) - deep check'

$adminEmail = 'admin@cccontrol.test'
$adminLabel = $adminEmail   # avoid @foo literal in double-quoted strings below

# exists + active
$r = & $php $artisan tinker --execute="echo DB::table('users')->where('email','$adminEmail')->where('status','active')->exists() ? 'yes' : 'no';" 2>&1
if ($r -match 'yes') { Pass "$adminLabel active" } else { Fail "$adminLabel missing or inactive" }

# password hash valid
$r = & $php $artisan tinker --execute="echo (App\User::where('email','$adminEmail')->count() && Hash::check('password123',App\User::where('email','$adminEmail')->value('password'))) ? 'yes' : 'no';" 2>&1
if ($r -match 'yes') { Pass "$adminLabel password hash valid (password123)" } else { Fail "$adminLabel password hash INVALID for 'password123'" }

# role super_admin
$r = & $php $artisan tinker --execute="echo DB::table('roles')->join('user_roles','roles.id','=','user_roles.role_id')->where('user_roles.user_id',App\User::where('email','$adminEmail')->value('id'))->where('roles.code','super_admin')->exists() ? 'yes' : 'no';" 2>&1
if ($r -match 'yes') { Pass "$adminLabel has role super_admin" } else { Fail "$adminLabel MISSING role super_admin" }

# web.admin.dashboard permission
$r = & $php $artisan tinker --execute="echo DB::table('permissions')->join('role_permissions','permissions.id','=','role_permissions.permission_id')->join('user_roles','user_roles.role_id','=','role_permissions.role_id')->where('user_roles.user_id',App\User::where('email','$adminEmail')->value('id'))->where('permissions.code','web.admin.dashboard')->exists() ? 'yes' : 'no';" 2>&1
if ($r -match 'yes') { Pass "$adminLabel has web.admin.dashboard" } else { Fail "$adminLabel MISSING web.admin.dashboard" }

# login API
$loginBody = "{`"email`":`"$adminEmail`",`"password`":`"password123`"}"
try {
    $loginResp = Invoke-RestMethod -Method Post `
        -Uri 'http://127.0.0.1:8000/api/v1/auth/login' `
        -ContentType 'application/json' `
        -Body $loginBody `
        -ErrorAction Stop
    if ($loginResp.token.access_token) {
        Pass "$adminLabel API login OK - token received"
        $adminToken = $loginResp.token.access_token

        # /me endpoint
        try {
            $meResp = Invoke-RestMethod -Method Get `
                -Uri 'http://127.0.0.1:8000/api/v1/auth/me' `
                -Headers @{ Authorization = "Bearer $adminToken" } `
                -ErrorAction Stop
            if ($meResp.data.email -eq $adminEmail) {
                Pass "$adminLabel /me returns correct identity"
            } else {
                Fail "$adminLabel /me returned unexpected email: $($meResp.data.email)"
            }
        } catch {
            Fail "$adminLabel /me failed: $_"
        }

        # first admin API endpoint
        try {
            $null = Invoke-RestMethod -Method Get `
                -Uri 'http://127.0.0.1:8000/api/v1/admin/brands' `
                -Headers @{ Authorization = "Bearer $adminToken" } `
                -ErrorAction Stop
            Pass "$adminLabel GET /api/v1/admin/brands OK"
        } catch {
            Fail "$adminLabel GET /api/v1/admin/brands failed: $_"
        }
    } else {
        Fail "$adminLabel API login: no token in response"
    }
} catch {
    $statusCode = $_.Exception.Response.StatusCode.value__
    if ($statusCode -eq 0 -or $null -eq $statusCode) {
        Write-Host "  [SKIP] $adminLabel API login - server not running (start php artisan serve)" -ForegroundColor Yellow
    } else {
        Fail "$adminLabel API login HTTP $statusCode - $_"
    }
}

# ── 5. Roles ──────────────────────────────────────────────────────────────────
Section "Roles"

$expectedRoles = @('user','dietologist','catalog_admin','supermarket_admin','recipe_admin','teacher','super_admin','system_jobs')
foreach ($role in $expectedRoles) {
    $exists = & $php $artisan tinker --execute="echo DB::table('roles')->where('code','$role')->exists() ? 'yes' : 'no';" 2>&1
    if ($exists -match 'yes') {
        Pass "Role: $role"
    } else {
        Fail "Missing role: $role"
    }
}

# ── 6. Critical permissions ───────────────────────────────────────────────────
Section "Permissions (sample)"

$criticalPerms = @(
    'web.user.shopping-session',
    'web.user.purchases',
    'web.user.supplements',
    'web.user.notifications',
    'web.admin.feature-flags',
    'web.admin.ai-foundation',
    'web.user.recipe-search',
    'web.user.recipe-suggestions',
    'web.user.recipe-favorites'
)

foreach ($perm in $criticalPerms) {
    $exists = & $php $artisan tinker --execute="echo DB::table('permissions')->where('code','$perm')->exists() ? 'yes' : 'no';" 2>&1
    if ($exists -match 'yes') {
        Pass "Permission: $perm"
    } else {
        Fail "Missing permission: $perm"
    }
}

# ── 7. Demo data ──────────────────────────────────────────────────────────────
Section "Demo data"

$dataChecks = @{
    'family_groups'       = "DB::table('family_groups')->where('name','Familia Demo')->exists() ? 'yes' : 'no'"
    'ingredients (>=5)'   = "DB::table('ingredients')->count() >= 5 ? 'yes' : 'no'"
    'products (>=3)'      = "DB::table('products')->where('is_verified',true)->count() >= 3 ? 'yes' : 'no'"
    'product_barcodes'    = "DB::table('product_barcodes')->count() > 0 ? 'yes' : 'no'"
    'recipes (>=2)'       = "DB::table('recipes')->count() >= 2 ? 'yes' : 'no'"
    'recipe_categories'   = "DB::table('recipe_categories')->count() > 0 ? 'yes' : 'no'"
    'stock_locations'     = "DB::table('stock_locations')->count() > 0 ? 'yes' : 'no'"
    'stock_items'         = "DB::table('stock_items')->count() > 0 ? 'yes' : 'no'"
    'shopping_lists'      = "DB::table('shopping_lists')->count() > 0 ? 'yes' : 'no'"
    'budgets'             = "DB::table('budgets')->count() > 0 ? 'yes' : 'no'"
    'supermarket_chains'  = "DB::table('supermarket_chains')->count() > 0 ? 'yes' : 'no'"
    'supermarket_branches'= "DB::table('supermarket_branches')->count() > 0 ? 'yes' : 'no'"
    'unit_measures'       = "DB::table('unit_measures')->count() > 0 ? 'yes' : 'no'"
    'nutrients'           = "DB::table('nutrients')->count() > 0 ? 'yes' : 'no'"
    'payment_methods'     = "DB::table('payment_methods')->count() > 0 ? 'yes' : 'no'"
    'cities'              = "DB::table('cities')->count() > 0 ? 'yes' : 'no'"
}

foreach ($label in $dataChecks.Keys) {
    $expr   = $dataChecks[$label]
    $result = & $php $artisan tinker --execute="echo $expr;" 2>&1
    if ($result -match 'yes') {
        Pass "Data: $label"
    } else {
        Fail "Missing data: $label"
    }
}

# ── 8. JS files referenced by layouts ────────────────────────────────────────
Section "JS files (admin layout)"

$adminJsFiles = @(
    'admin-rbac.js', 'admin-audit.js', 'admin-objectives.js', 'admin-health-preferences.js',
    'admin-ingredients.js', 'admin-ingredient-categories.js', 'admin-nutrients.js',
    'admin-units.js', 'admin-ingredient-equivalences.js', 'admin-food-tags.js',
    'admin-brands.js', 'admin-product-categories.js', 'admin-products.js',
    'admin-barcodes.js', 'admin-product-reports.js', 'admin-cities.js',
    'admin-supermarkets.js', 'admin-branches.js', 'admin-recipe-categories.js',
    'admin-recipe-tags.js', 'admin-meal-types.js', 'recipe-ingredients.js',
    'recipe-steps.js', 'recipe-nutrition.js', 'recipe-cost.js',
    'admin-official-recipes.js', 'admin-recipe-import.js', 'admin-recipe-import-text.js',
    'admin-recipe-import-candidates.js', 'admin-supermarket-products.js',
    'admin-promotions.js', 'admin-payment-methods.js', 'admin-supermarket-scraping.js',
    'admin-recipe-scraping.js', 'admin-scraped-products.js', 'admin-scraping-alerts.js',
    'admin-price-refresh-requests.js', 'admin-reports.js', 'admin-thesis-docs.js',
    'admin-demo-scenarios.js', 'admin-settings.js', 'admin-feature-flags.js',
    'admin-ai-foundation.js'
)

foreach ($jsFile in $adminJsFiles) {
    $path = Join-Path $root "public\js\$jsFile"
    if (Test-Path $path) {
        Pass "admin-web: $jsFile"
    } else {
        Fail "MISSING admin-web JS: $jsFile"
    }
}

Section "JS files (user layout)"

$userJsFiles = @(
    'family-groups.js', 'user-profile.js', 'professional-links.js', 'user-catalog.js',
    'user-barcode.js', 'user-supermarkets.js', 'user-branches.js', 'user-payment-methods.js',
    'user-stock-locations.js', 'recipe-ingredients.js', 'recipe-steps.js', 'recipe-nutrition.js',
    'recipe-cost.js', 'recipe-availability.js', 'recipe-substitutions.js',
    'recipe-favorites-actions.js', 'recipe-sharing-branch.js', 'user-recipes.js',
    'user-recipe-favorites.js', 'user-recipe-search.js', 'user-recipe-suggestions.js',
    'user-meal-plans.js', 'user-notifications.js', 'user-reports.js', 'user-supplements.js',
    'user-budget.js', 'user-purchases.js', 'user-shopping-session.js',
    'shopping-alternatives.js', 'shopping-compare.js', 'user-shopping-lists.js'
)

foreach ($jsFile in $userJsFiles) {
    $path = Join-Path $root "public\js\$jsFile"
    if (Test-Path $path) {
        Pass "web-user: $jsFile"
    } else {
        Fail "MISSING web-user JS: $jsFile"
    }
}

Section "JS files (teacher layout)"

$teacherJsFiles = @('teacher-docs.js', 'teacher-demo-scenarios.js')
foreach ($jsFile in $teacherJsFiles) {
    $path = Join-Path $root "public\js\$jsFile"
    if (Test-Path $path) {
        Pass "teacher-web: $jsFile"
    } else {
        Fail "MISSING teacher-web JS: $jsFile"
    }
}

# ── 9. Duplicate script detection ─────────────────────────────────────────────
Section "Duplicate script detection in layouts"

$layoutFiles = @(
    (Join-Path $root 'resources\views\layouts\admin-web.blade.php'),
    (Join-Path $root 'resources\views\layouts\web-user.blade.php'),
    (Join-Path $root 'resources\views\layouts\teacher-web.blade.php')
)

foreach ($layoutFile in $layoutFiles) {
    if (-not (Test-Path $layoutFile)) { continue }
    $content   = Get-Content $layoutFile -Raw
    $regexMatches = [regex]::Matches($content, "asset\('js/([^']+\.js)'\)")
    $jsNames      = $regexMatches | ForEach-Object { $_.Groups[1].Value }
    $duplicates = $jsNames | Group-Object | Where-Object { $_.Count -gt 1 }
    $layoutName = Split-Path $layoutFile -Leaf
    if ($duplicates) {
        foreach ($dup in $duplicates) {
            Fail "$layoutName loads $($dup.Name) $($dup.Count) times"
        }
    } else {
        Pass "$layoutName - no duplicate scripts"
    }
}

# ── 10. node --check on JS files ─────────────────────────────────────────────
Section "node --check JS syntax"

$nodeCmd = (Get-Command node -ErrorAction SilentlyContinue)
if ($null -ne $nodeCmd) {
    $allJsFiles = Get-ChildItem (Join-Path $root 'public\js') -Filter '*.js' |
        Where-Object { $_.Name -ne 'app.js' }

    $jsFailed = 0
    foreach ($f in $allJsFiles) {
        $out = & node --check $f.FullName 2>&1
        if ($LASTEXITCODE -ne 0) {
            $fname = $f.Name
            Fail "node --check failed: $fname"
            $jsFailed++
        }
    }
    if ($jsFailed -eq 0) {
        $cnt = $allJsFiles.Count
        $msg = "All JS files passed node --check - $cnt files"
        Pass $msg
    }
} else {
    Write-Host '  [SKIP] node not found - skipping JS syntax check' -ForegroundColor Yellow
}

# ── 11. PHP lint on key PHP/Blade files ──────────────────────────────────────
Section "PHP lint"

$phpFiles = @(
    'database\seeds\DemoDataSeeder.php',
    'database\seeds\DatabaseSeeder.php',
    'database\migrations\2026_06_24_000033_add_shopping_session_user_permission.php',
    'database\migrations\2026_06_24_000034_add_purchases_user_permission.php',
    'database\migrations\2026_06_24_000035_add_supplements_user_permission.php',
    'database\migrations\2026_06_24_000036_add_notifications_user_permission.php',
    'database\migrations\2026_06_24_000037_add_feature_flags_admin_permission.php',
    'database\migrations\2026_06_24_000038_add_ai_foundation_admin_permission.php'
)

foreach ($rel in $phpFiles) {
    $fullPath = Join-Path $root $rel
    if (-not (Test-Path $fullPath)) {
        Fail "File not found: $rel"
        continue
    }
    $out = & $php -l $fullPath 2>&1
    if ($LASTEXITCODE -eq 0) {
        Pass "PHP lint OK: $(Split-Path $rel -Leaf)"
    } else {
        Fail "PHP lint FAILED: $rel - $out"
    }
}

# ── 12. Critical API routes ───────────────────────────────────────────────────
Section "API routes (spot check)"

$routeList = & $php $artisan route:list --path=api/v1 2>&1
$criticalRoutes = @(
    'api/v1/ingredients',
    'api/v1/products',
    'api/v1/recipes',
    'api/v1/family-groups/{id}/shopping-lists',
    'api/v1/family-groups/{id}/stock-locations',
    'api/v1/family-groups/{id}/budgets',
    'api/v1/admin/brands',
    'api/v1/admin/cities',
    'api/v1/admin/feature-flags',
    'api/v1/products/barcode'
)

foreach ($route in $criticalRoutes) {
    if ($routeList -match [regex]::Escape($route)) {
        Pass "Route exists: $route"
    } else {
        Fail "Route MISSING: $route"
    }
}

# ── 13. Web routes ────────────────────────────────────────────────────────────
Section "Web routes (spot check)"

$webRouteList = & $php $artisan route:list --path=web 2>&1
$criticalWebRoutes = @('web', 'admin-web', 'teacher-web')
foreach ($wr in $criticalWebRoutes) {
    if ($webRouteList -match $wr) {
        Pass "Web route group: /$wr"
    } else {
        Fail "Web route group MISSING: /$wr"
    }
}

# ── 14. Critical PHPUnit partitions ──────────────────────────────────────────
Section "PHPUnit (Unit tests)"

$phpunit = Join-Path $root 'vendor\phpunit\phpunit\phpunit'
$out = & $php -d memory_limit=256M $phpunit --no-coverage (Join-Path $root 'tests\Unit') 2>&1
if ($LASTEXITCODE -eq 0) {
    $summary = ($out | Select-Object -Last 3) -join ' '
    Pass "Unit tests: $summary"
} else {
    $summary = ($out | Select-Object -Last 5) -join "`n"
    Fail "Unit tests FAILED:`n$summary"
}

# ── Summary ───────────────────────────────────────────────────────────────────
Write-Host ""
Write-Host ('═' * 56) -ForegroundColor White
Write-Host "  Validate-demo complete" -ForegroundColor White
Write-Host "  Passed : $passed" -ForegroundColor Green

if ($errors.Count -eq 0) {
    Write-Host "  Failed : 0" -ForegroundColor Green
    Write-Host ""
    Write-Host "  All checks passed." -ForegroundColor Green
    exit 0
} else {
    Write-Host "  Failed : $($errors.Count)" -ForegroundColor Red
    Write-Host ""
    Write-Host "  Failures:" -ForegroundColor Red
    foreach ($e in $errors) {
        Write-Host "    - $e" -ForegroundColor Red
    }
    exit 1
}

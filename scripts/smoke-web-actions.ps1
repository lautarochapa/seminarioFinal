#Requires -Version 5.1
<#
.SYNOPSIS
    Smoke test de acciones web via HTTP real.
    Simula el flujo completo: login -> grupo -> stock -> productos admin.
    Exit code 0 = todo OK. Exit code 1 = algun fallo.
#>

$ErrorActionPreference = 'Continue'

$baseUrl       = 'http://127.0.0.1:8000'
$userEmail     = 'usuario@cccontrol.test'
$userPassword  = '12345678'
$adminEmail    = 'superadmin@cccontrol.test'
$adminPassword = '12345678'

$pass   = 0
$fail   = 0
$errors = [System.Collections.Generic.List[string]]::new()

function Pass([string]$msg) {
    Write-Host "  [PASS] $msg" -ForegroundColor Green
    $script:pass++
}

function Fail([string]$msg) {
    Write-Host "  [FAIL] $msg" -ForegroundColor Red
    $script:errors.Add($msg)
    $script:fail++
}

function Section([string]$title) {
    Write-Host ""
    Write-Host "-- $title --" -ForegroundColor Cyan
}

function Api([string]$method, [string]$url, [hashtable]$body = $null, [string]$token = '') {
    $headers = @{ 'Accept' = 'application/json'; 'Content-Type' = 'application/json' }
    if ($token -ne '') { $headers['Authorization'] = "Bearer $token" }

    $params = @{
        Method          = $method
        Uri             = "$baseUrl$url"
        Headers         = $headers
        UseBasicParsing = $true
    }
    if ($body -ne $null) {
        $params['Body'] = ($body | ConvertTo-Json -Depth 5)
    }

    try {
        $resp = Invoke-WebRequest @params -ErrorAction Stop
        return @{
            status = [int]$resp.StatusCode
            body   = ($resp.Content | ConvertFrom-Json)
            ok     = $true
        }
    } catch {
        $statusCode = 0
        $bodyObj    = $null
        if ($_.Exception.Response) {
            $statusCode = [int]$_.Exception.Response.StatusCode
            try {
                $stream  = $_.Exception.Response.GetResponseStream()
                $reader  = [System.IO.StreamReader]::new($stream)
                $bodyObj = ($reader.ReadToEnd() | ConvertFrom-Json)
            } catch {}
        }
        return @{
            status = $statusCode
            body   = $bodyObj
            ok     = $false
            error  = $_.Exception.Message
        }
    }
}

function Login([string]$email, [string]$password) {
    $r = Api 'POST' '/api/v1/auth/login' @{ email = $email; password = $password }
    if ($r.ok -and $r.status -eq 200) {
        $token = $r.body.token.access_token
        if ($token) { return [string]$token }
    }
    return ''
}

# -- 1. Login usuario -------------------------------------------------------
Section "1. Login usuario"

$userToken = Login $userEmail $userPassword
if ($userToken -ne '') {
    Pass "Login usuario exitoso"
} else {
    Fail "Login usuario FALLIDO para $userEmail"
}

# -- 2. Family groups -------------------------------------------------------
Section "2. Family groups"

$groupId = 0
if ($userToken -ne '') {
    $r = Api 'GET' '/api/v1/family-groups' -token $userToken
    if ($r.ok -and $r.status -eq 200) {
        $groups = $r.body.data
        if ($groups -and $groups.Count -gt 0) {
            $groupId = [int]$groups[0].id
            Pass "Family groups: $($groups.Count) grupo(s), id=$groupId"
        } else {
            Fail "Family groups: lista vacia"
        }
    } else {
        Fail "GET /api/v1/family-groups -> status $($r.status)"
    }
}

# -- 3. Stock locations -----------------------------------------------------
Section "3. Stock locations"

$locationId = 0
if ($userToken -ne '' -and $groupId -gt 0) {
    $locUrl = '/api/v1/family-groups/' + $groupId + '/stock-locations?per_page=100&status=active'
    $r = Api 'GET' $locUrl -token $userToken
    if ($r.ok -and $r.status -eq 200) {
        $locs = $r.body.data
        if ($locs -and $locs.Count -gt 0) {
            $locationId = [int]$locs[0].id
            Pass "Stock locations: $($locs.Count) ubicacion(es), primera='$($locs[0].name)'"
        } else {
            Fail "Stock locations: lista vacia para grupo $groupId"
        }
    } else {
        Fail "GET stock-locations -> status $($r.status)"
    }
}

# -- 4. Listar stock --------------------------------------------------------
Section "4. Listar stock del grupo"

if ($userToken -ne '' -and $groupId -gt 0) {
    $stockUrl = '/api/v1/family-groups/' + $groupId + '/stock?per_page=20'
    $r = Api 'GET' $stockUrl -token $userToken
    if ($r.ok -and $r.status -eq 200) {
        $total = $r.body.meta.total
        Pass "Listar stock: $total item(s)"
    } else {
        Fail "GET family-groups/{id}/stock -> status $($r.status)"
    }
}

# -- 5. Buscar producto limon -----------------------------------------------
Section "5. Buscar producto limon"

$productId = 0
if ($userToken -ne '') {
    $r = Api 'GET' '/api/v1/products?search=limon&per_page=5' -token $userToken
    if ($r.ok -and $r.status -eq 200) {
        $prods = $r.body.data
        if ($prods -and $prods.Count -gt 0) {
            $productId = [int]$prods[0].id
            Pass "Buscar 'limon': $($prods.Count) resultado(s), id=$productId"
        } else {
            Fail "Buscar 'limon': sin resultados (verificar DemoDataSeeder)"
        }
    } else {
        Fail "GET products?search=limon -> status $($r.status)"
    }
}

# -- 6. Crear stock item ----------------------------------------------------
Section "6. Crear stock item"

$stockItemId = 0
if ($userToken -ne '' -and $groupId -gt 0 -and $productId -gt 0 -and $locationId -gt 0) {
    $stockUrl = '/api/v1/family-groups/' + $groupId + '/stock'
    $body = @{
        product_id        = $productId
        stock_location_id = $locationId
        quantity          = 3
        unit_id           = 1
        purchase_price    = 150
    }
    $r = Api 'POST' $stockUrl $body $userToken
    if ($r.ok -and ($r.status -eq 201 -or $r.status -eq 200)) {
        $stockItemId = [int]$r.body.data.id
        Pass "Crear stock item: status $($r.status), id=$stockItemId"
    } else {
        $bodyJson = if ($r.body) { $r.body | ConvertTo-Json -Compress } else { 'null' }
        Fail "POST $stockUrl -> status $($r.status) body=$bodyJson"
    }
} else {
    Fail "Crear stock item: prerequisito faltante (groupId=$groupId productId=$productId locationId=$locationId)"
}

# -- 7. Eliminar stock item -------------------------------------------------
Section "7. Eliminar stock item"

if ($userToken -ne '' -and $stockItemId -gt 0 -and $groupId -gt 0) {
    $delUrl = '/api/v1/family-groups/' + $groupId + '/stock/' + $stockItemId
    $r = Api 'DELETE' $delUrl -token $userToken
    if ($r.ok -and ($r.status -eq 200 -or $r.status -eq 204)) {
        Pass "Eliminar stock item ${stockItemId}: status $($r.status)"
    } else {
        Fail "DELETE $delUrl -> status $($r.status)"
    }
} else {
    Fail "Eliminar stock item: no hay stockItemId del paso anterior"
}

# -- 8. Login admin ---------------------------------------------------------
Section "8. Login admin"

$adminToken = Login $adminEmail $adminPassword
if ($adminToken -ne '') {
    Pass "Login admin exitoso"
} else {
    Fail "Login admin FALLIDO para $adminEmail"
}

# -- 9. Listar productos admin ----------------------------------------------
Section "9. Listar productos admin"

if ($adminToken -ne '') {
    $r = Api 'GET' '/api/v1/admin/products?per_page=5' -token $adminToken
    if ($r.ok -and $r.status -eq 200) {
        $total = $r.body.meta.total
        Pass "GET /api/v1/admin/products: $total producto(s)"
    } else {
        Fail "GET /api/v1/admin/products -> status $($r.status)"
    }
}

# -- 10. Crear producto admin -----------------------------------------------
Section "10. Crear producto admin"

$newProductId = 0
$smokeBrandId = 0
if ($adminToken -ne '') {
    $rb = Api 'GET' '/api/v1/admin/brands?per_page=1&status=active' -token $adminToken
    if ($rb.ok -and $rb.body.data -and $rb.body.data.Count -gt 0) {
        $smokeBrandId = [int]$rb.body.data[0].id
    }
}
if ($adminToken -ne '' -and $smokeBrandId -gt 0) {
    $body = @{
        name     = 'Producto Smoke Test'
        brand_id = $smokeBrandId
        status   = 'active'
    }
    $r = Api 'POST' '/api/v1/admin/products' $body $adminToken
    if ($r.ok -and $r.status -eq 201) {
        $newProductId = [int]$r.body.data.id
        Pass "POST /api/v1/admin/products: 201 created, id=$newProductId"
    } else {
        $bodyJson = if ($r.body) { $r.body | ConvertTo-Json -Compress } else { 'null' }
        Fail "POST /api/v1/admin/products -> status $($r.status) body=$bodyJson"
    }
}

# -- 11. Editar producto admin ----------------------------------------------
Section "11. Editar producto admin"

if ($adminToken -ne '' -and $newProductId -gt 0) {
    $patchUrl = '/api/v1/admin/products/' + $newProductId
    $body = @{ name = 'Producto Smoke Test (editado)' }
    $r = Api 'PATCH' $patchUrl $body $adminToken
    if ($r.ok -and $r.status -eq 200) {
        Pass "PATCH ${patchUrl}: 200 OK"
    } else {
        Fail "PATCH $patchUrl -> status $($r.status)"
    }
} else {
    Fail "Editar producto: no hay newProductId del paso anterior"
}

# -- 12. Detalle producto admin ---------------------------------------------
Section "12. Detalle producto admin"

if ($adminToken -ne '' -and $newProductId -gt 0) {
    $getUrl = '/api/v1/admin/products/' + $newProductId
    $r = Api 'GET' $getUrl -token $adminToken
    if ($r.ok -and $r.status -eq 200) {
        Pass "GET ${getUrl}: 200 OK"
    } else {
        Fail "GET $getUrl -> status $($r.status)"
    }
} else {
    Fail "Detalle producto: no hay newProductId del paso anterior"
}

# -- 13. Eliminar producto admin --------------------------------------------
Section "13. Eliminar producto admin"

if ($adminToken -ne '' -and $newProductId -gt 0) {
    $delAdminUrl = '/api/v1/admin/products/' + $newProductId
    $r = Api 'DELETE' $delAdminUrl -token $adminToken
    if ($r.ok -and ($r.status -eq 200 -or $r.status -eq 204)) {
        Pass "DELETE ${delAdminUrl}: status $($r.status)"
    } else {
        Fail "DELETE $delAdminUrl -> status $($r.status)"
    }
} else {
    Fail "Eliminar producto: no hay newProductId del paso anterior"
}

# -- RESULTADO FINAL --------------------------------------------------------
Write-Host ""
Write-Host "================================" -ForegroundColor White
$color = if ($fail -eq 0) { 'Green' } else { 'Red' }
Write-Host "  RESULTADO: $pass PASS / $fail FAIL" -ForegroundColor $color
Write-Host "================================" -ForegroundColor White

if ($errors.Count -gt 0) {
    Write-Host ""
    Write-Host "Fallos:" -ForegroundColor Red
    foreach ($e in $errors) {
        Write-Host "  - $e" -ForegroundColor Red
    }
}

if ($fail -gt 0) { exit 1 } else { exit 0 }

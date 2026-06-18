<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| All /api/v1/* routes are defined in domain modules under routes/api/.
| Public auth routes and RBAC admin routes live in routes/api_contract.php.
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

// Public auth routes + admin RBAC + family-groups + users/me basics
Route::prefix('v1')->middleware(['web'])->group(base_path('routes/api_contract.php'));

// Domain modules — all protected by web + trace_id + api_token + auth
$v1 = ['web', 'trace_id', 'api_token', 'auth'];

Route::prefix('v1')->middleware($v1)->group(base_path('routes/api/notifications.php'));
Route::prefix('v1')->middleware($v1)->group(base_path('routes/api/catalog.php'));
Route::prefix('v1')->middleware($v1)->group(base_path('routes/api/ingredients.php'));
Route::prefix('v1')->middleware($v1)->group(base_path('routes/api/recipes.php'));
Route::prefix('v1')->middleware($v1)->group(base_path('routes/api/stock.php'));
Route::prefix('v1')->middleware($v1)->group(base_path('routes/api/health.php'));
Route::prefix('v1')->middleware($v1)->group(base_path('routes/api/professional.php'));
Route::prefix('v1')->middleware($v1)->group(base_path('routes/api/scraping-admin.php'));
Route::prefix('v1')->middleware($v1)->group(base_path('routes/api/admin-misc.php'));
Route::prefix('v1')->middleware($v1)->group(base_path('routes/api/meal-plans.php'));
Route::prefix('v1')->middleware($v1)->group(base_path('routes/api/shopping.php'));
Route::prefix('v1')->middleware($v1)->group(base_path('routes/api/purchases.php'));
Route::prefix('v1')->middleware($v1)->group(base_path('routes/api/budgets.php'));
Route::prefix('v1')->middleware($v1)->group(base_path('routes/api/reports.php'));

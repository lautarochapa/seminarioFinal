<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

Route::prefix('v1')->group(base_path('routes/api_contract.php'));

Route::prefix('v1')->middleware(['web', 'trace_id', 'auth'])->group(function () {
    Route::get('auth/consents', [\App\Http\Controllers\Api\V1\Consents\UserConsentController::class, 'show']);
    Route::patch('auth/consents', [\App\Http\Controllers\Api\V1\Consents\UserConsentController::class, 'update']);
    Route::get('users/me/consents', [\App\Http\Controllers\Api\V1\Consents\UserConsentController::class, 'show']);
    Route::patch('users/me/consents', [\App\Http\Controllers\Api\V1\Consents\UserConsentController::class, 'update']);
});

Route::prefix('v1')->middleware(['web', 'trace_id', 'auth'])->group(function () {
    Route::pattern('healthPreferenceType', 'dietary-restrictions|health-conditions|allergies');

    Route::get('admin/{healthPreferenceType}', [\App\Http\Controllers\Api\V1\HealthPreferences\AdminHealthPreferenceController::class, 'index'])
        ->middleware('permission:catalog.manage');
    Route::post('admin/{healthPreferenceType}', [\App\Http\Controllers\Api\V1\HealthPreferences\AdminHealthPreferenceController::class, 'store'])
        ->middleware('permission:catalog.manage');
    Route::patch('admin/{healthPreferenceType}/{id}/restore', [\App\Http\Controllers\Api\V1\HealthPreferences\AdminHealthPreferenceController::class, 'restore'])
        ->middleware('permission:catalog.manage');
    Route::get('admin/{healthPreferenceType}/{id}', [\App\Http\Controllers\Api\V1\HealthPreferences\AdminHealthPreferenceController::class, 'show'])
        ->middleware('permission:catalog.manage');
    Route::patch('admin/{healthPreferenceType}/{id}', [\App\Http\Controllers\Api\V1\HealthPreferences\AdminHealthPreferenceController::class, 'update'])
        ->middleware('permission:catalog.manage');
    Route::delete('admin/{healthPreferenceType}/{id}', [\App\Http\Controllers\Api\V1\HealthPreferences\AdminHealthPreferenceController::class, 'destroy'])
        ->middleware('permission:catalog.manage');

    Route::get('catalog/{healthPreferenceType}', [\App\Http\Controllers\Api\V1\HealthPreferences\CatalogHealthPreferenceController::class, 'index']);

    Route::get('users/me/{healthPreferenceType}', [\App\Http\Controllers\Api\V1\HealthPreferences\UserHealthPreferenceController::class, 'index']);
    Route::post('users/me/{healthPreferenceType}', [\App\Http\Controllers\Api\V1\HealthPreferences\UserHealthPreferenceController::class, 'store']);
    Route::delete('users/me/{healthPreferenceType}/{id}', [\App\Http\Controllers\Api\V1\HealthPreferences\UserHealthPreferenceController::class, 'destroy']);
});

Route::prefix('v1')->middleware(['web', 'trace_id', 'auth'])->group(function () {
    // Professional links (patient perspective)
    Route::prefix('professional-links')->group(function () {
        Route::get('',        [\App\Http\Controllers\Api\V1\Professional\ProfessionalLinkController::class, 'index']);
        Route::post('',       [\App\Http\Controllers\Api\V1\Professional\ProfessionalLinkController::class, 'store']);
        Route::patch('{id}',  [\App\Http\Controllers\Api\V1\Professional\ProfessionalLinkController::class, 'update']);
        Route::delete('{id}', [\App\Http\Controllers\Api\V1\Professional\ProfessionalLinkController::class, 'destroy']);
    });

    // Professional panel (dietologist perspective)
    Route::prefix('professional')->middleware('permission:professional.users.read')->group(function () {
        Route::get('linked-users',                         [\App\Http\Controllers\Api\V1\Professional\ProfessionalPanelController::class, 'linkedUsers']);
        Route::get('users/{userId}/profile',               [\App\Http\Controllers\Api\V1\Professional\ProfessionalPanelController::class, 'userProfile']);
        Route::get('users/{userId}/meal-plans',            [\App\Http\Controllers\Api\V1\Professional\ProfessionalPanelController::class, 'mealPlans']);
        Route::patch('users/{userId}/meal-plans/{planId}', [\App\Http\Controllers\Api\V1\Professional\ProfessionalPanelController::class, 'updateMealPlan'])
            ->middleware('permission:professional.meal_plans.write');
    });
});




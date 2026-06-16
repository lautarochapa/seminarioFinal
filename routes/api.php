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


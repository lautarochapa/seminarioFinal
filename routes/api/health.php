<?php

use Illuminate\Support\Facades\Route;

// Constraint applied per-route (not globally) to avoid polluting other route groups.
// Accepted values for {healthPreferenceType}: dietary-restrictions | health-conditions | allergies
$hpt = 'dietary-restrictions|health-conditions|allergies';

// Health preferences — admin
Route::get('admin/{healthPreferenceType}', [\App\Http\Controllers\Api\V1\HealthPreferences\AdminHealthPreferenceController::class, 'index'])
    ->middleware('permission:catalog.manage')
    ->where('healthPreferenceType', $hpt);
Route::post('admin/{healthPreferenceType}', [\App\Http\Controllers\Api\V1\HealthPreferences\AdminHealthPreferenceController::class, 'store'])
    ->middleware('permission:catalog.manage')
    ->where('healthPreferenceType', $hpt);
Route::patch('admin/{healthPreferenceType}/{id}/restore', [\App\Http\Controllers\Api\V1\HealthPreferences\AdminHealthPreferenceController::class, 'restore'])
    ->middleware('permission:catalog.manage')
    ->where('healthPreferenceType', $hpt);
Route::get('admin/{healthPreferenceType}/{id}', [\App\Http\Controllers\Api\V1\HealthPreferences\AdminHealthPreferenceController::class, 'show'])
    ->middleware('permission:catalog.manage')
    ->where('healthPreferenceType', $hpt);
Route::patch('admin/{healthPreferenceType}/{id}', [\App\Http\Controllers\Api\V1\HealthPreferences\AdminHealthPreferenceController::class, 'update'])
    ->middleware('permission:catalog.manage')
    ->where('healthPreferenceType', $hpt);
Route::delete('admin/{healthPreferenceType}/{id}', [\App\Http\Controllers\Api\V1\HealthPreferences\AdminHealthPreferenceController::class, 'destroy'])
    ->middleware('permission:catalog.manage')
    ->where('healthPreferenceType', $hpt);

// Health preferences — catalog
Route::get('catalog/{healthPreferenceType}', [\App\Http\Controllers\Api\V1\HealthPreferences\CatalogHealthPreferenceController::class, 'index'])
    ->where('healthPreferenceType', $hpt);

// Supplements — specific routes before wildcard {healthPreferenceType}
Route::get('users/me/supplements',                                   [\App\Http\Controllers\Api\V1\UserSupplements\UserSupplementController::class, 'index']);
Route::post('users/me/supplements',                                  [\App\Http\Controllers\Api\V1\UserSupplements\UserSupplementController::class, 'store']);
Route::patch('users/me/supplements/{id}',                            [\App\Http\Controllers\Api\V1\UserSupplements\UserSupplementController::class, 'update']);
Route::delete('users/me/supplements/{id}',                           [\App\Http\Controllers\Api\V1\UserSupplements\UserSupplementController::class, 'destroy']);
Route::get('users/me/supplements/{id}/schedule',                     [\App\Http\Controllers\Api\V1\UserSupplements\SupplementScheduleController::class, 'index']);
Route::post('users/me/supplements/{id}/schedule',                    [\App\Http\Controllers\Api\V1\UserSupplements\SupplementScheduleController::class, 'store']);
Route::patch('users/me/supplements/{id}/schedule/{scheduleId}',      [\App\Http\Controllers\Api\V1\UserSupplements\SupplementScheduleController::class, 'update']);
Route::delete('users/me/supplements/{id}/schedule/{scheduleId}',     [\App\Http\Controllers\Api\V1\UserSupplements\SupplementScheduleController::class, 'destroy']);
Route::post('users/me/supplements/{id}/log',                         [\App\Http\Controllers\Api\V1\UserSupplements\SupplementScheduleController::class, 'storeLog']);

// Personal reports — specific routes before wildcard {healthPreferenceType}
Route::get('users/me/reports/body-progress',       [\App\Http\Controllers\Api\V1\PersonalReports\PersonalReportController::class, 'bodyProgress']);
Route::get('users/me/reports/objectives-progress', [\App\Http\Controllers\Api\V1\PersonalReports\PersonalReportController::class, 'objectivesProgress']);

// Health preferences — user (wildcard last, constrained to avoid shadowing specific user routes)
Route::get('users/me/{healthPreferenceType}', [\App\Http\Controllers\Api\V1\HealthPreferences\UserHealthPreferenceController::class, 'index'])
    ->where('healthPreferenceType', $hpt);
Route::post('users/me/{healthPreferenceType}', [\App\Http\Controllers\Api\V1\HealthPreferences\UserHealthPreferenceController::class, 'store'])
    ->where('healthPreferenceType', $hpt);
Route::delete('users/me/{healthPreferenceType}/{id}', [\App\Http\Controllers\Api\V1\HealthPreferences\UserHealthPreferenceController::class, 'destroy'])
    ->where('healthPreferenceType', $hpt);

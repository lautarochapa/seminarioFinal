<?php

use Illuminate\Support\Facades\Route;

// Budget sub-resources — specific before wildcard {budgetId}
Route::get('family-groups/{id}/budgets/{budgetId}/alerts',                          [\App\Http\Controllers\Api\V1\Budgets\BudgetAlertController::class, 'index']);
Route::patch('family-groups/{id}/budgets/{budgetId}/alerts/{alertId}/read',         [\App\Http\Controllers\Api\V1\Budgets\BudgetAlertController::class, 'markAsRead']);
Route::get('family-groups/{id}/budgets/{budgetId}/movements',                       [\App\Http\Controllers\Api\V1\Budgets\BudgetMovementController::class, 'index']);
Route::post('family-groups/{id}/budgets/{budgetId}/adjustments',                    [\App\Http\Controllers\Api\V1\Budgets\BudgetMovementController::class, 'storeAdjustment']);
Route::get('family-groups/{id}/budgets/{budgetId}/summary',                         [\App\Http\Controllers\Api\V1\Budgets\BudgetSummaryController::class, 'summary']);
Route::get('family-groups/{id}/budgets/{budgetId}/projection',                      [\App\Http\Controllers\Api\V1\Budgets\BudgetSummaryController::class, 'projection']);
Route::get('family-groups/{id}/budgets/{budgetId}/categories',                      [\App\Http\Controllers\Api\V1\Budgets\BudgetCategoryController::class, 'index']);
Route::post('family-groups/{id}/budgets/{budgetId}/categories',                     [\App\Http\Controllers\Api\V1\Budgets\BudgetCategoryController::class, 'store']);
Route::patch('family-groups/{id}/budgets/{budgetId}/categories/{categoryId}',       [\App\Http\Controllers\Api\V1\Budgets\BudgetCategoryController::class, 'update']);
Route::delete('family-groups/{id}/budgets/{budgetId}/categories/{categoryId}',      [\App\Http\Controllers\Api\V1\Budgets\BudgetCategoryController::class, 'destroy']);

// Budgets — current before wildcard {budgetId}
Route::get('family-groups/{id}/budgets/current',          [\App\Http\Controllers\Api\V1\Budgets\BudgetController::class, 'current']);
Route::get('family-groups/{id}/budgets',                  [\App\Http\Controllers\Api\V1\Budgets\BudgetController::class, 'index']);
Route::post('family-groups/{id}/budgets',                 [\App\Http\Controllers\Api\V1\Budgets\BudgetController::class, 'store']);
Route::patch('family-groups/{id}/budgets/{budgetId}',     [\App\Http\Controllers\Api\V1\Budgets\BudgetController::class, 'update']);
Route::delete('family-groups/{id}/budgets/{budgetId}',    [\App\Http\Controllers\Api\V1\Budgets\BudgetController::class, 'destroy']);

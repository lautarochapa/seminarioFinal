<?php

use Illuminate\Support\Facades\Route;

// Stock locations
Route::get('family-groups/{id}/stock-locations',                          [\App\Http\Controllers\Api\V1\StockLocations\StockLocationController::class, 'index']);
Route::post('family-groups/{id}/stock-locations',                         [\App\Http\Controllers\Api\V1\StockLocations\StockLocationController::class, 'store']);
Route::patch('family-groups/{id}/stock-locations/{locationId}',           [\App\Http\Controllers\Api\V1\StockLocations\StockLocationController::class, 'update']);
Route::delete('family-groups/{id}/stock-locations/{locationId}',          [\App\Http\Controllers\Api\V1\StockLocations\StockLocationController::class, 'destroy']);

// Household stock — summary and value before index to avoid shadowing
Route::get('family-groups/{id}/stock/summary',    [\App\Http\Controllers\Api\V1\HouseholdStock\HouseholdStockController::class, 'summary']);
Route::get('family-groups/{id}/stock/value',      [\App\Http\Controllers\Api\V1\HouseholdStock\HouseholdStockController::class, 'value']);
Route::get('family-groups/{id}/reports/waste',    [\App\Http\Controllers\Api\V1\WasteReports\WasteReportController::class, 'show']);
Route::get('family-groups/{id}/stock/expiring',   [\App\Http\Controllers\Api\V1\StockAlerts\StockAlertController::class, 'expiring']);
Route::get('family-groups/{id}/stock/low-stock',  [\App\Http\Controllers\Api\V1\StockAlerts\StockAlertController::class, 'lowStock']);
Route::post('family-groups/{id}/stock/scan',      [\App\Http\Controllers\Api\V1\StockScan\StockScanController::class, 'store']);

// Stock alerts and minimum rules
Route::get('family-groups/{id}/stock-alerts',                             [\App\Http\Controllers\Api\V1\StockAlerts\StockAlertController::class, 'index']);
Route::patch('family-groups/{id}/stock-alerts/{alertId}/read',            [\App\Http\Controllers\Api\V1\StockAlerts\StockAlertController::class, 'read']);
Route::get('family-groups/{id}/stock-minimum-rules',                      [\App\Http\Controllers\Api\V1\StockAlerts\StockMinimumRuleController::class, 'index']);
Route::post('family-groups/{id}/stock-minimum-rules',                     [\App\Http\Controllers\Api\V1\StockAlerts\StockMinimumRuleController::class, 'store']);
Route::get('family-groups/{id}/stock-minimum-rules/{ruleId}',             [\App\Http\Controllers\Api\V1\StockAlerts\StockMinimumRuleController::class, 'show']);
Route::patch('family-groups/{id}/stock-minimum-rules/{ruleId}',           [\App\Http\Controllers\Api\V1\StockAlerts\StockMinimumRuleController::class, 'update']);
Route::delete('family-groups/{id}/stock-minimum-rules/{ruleId}',          [\App\Http\Controllers\Api\V1\StockAlerts\StockMinimumRuleController::class, 'destroy']);

// Stock movements — adjust/consume/discard before index to keep specific before wildcard
Route::get('family-groups/{id}/stock-movements',                          [\App\Http\Controllers\Api\V1\StockMovements\StockMovementController::class, 'index']);
Route::post('family-groups/{id}/stock/{stockItemId}/adjust',              [\App\Http\Controllers\Api\V1\StockMovements\StockMovementController::class, 'adjust']);
Route::post('family-groups/{id}/stock/{stockItemId}/consume',             [\App\Http\Controllers\Api\V1\StockMovements\StockMovementController::class, 'consume']);
Route::post('family-groups/{id}/stock/{stockItemId}/discard',             [\App\Http\Controllers\Api\V1\StockMovements\StockMovementController::class, 'discard']);

// Stock CRUD
Route::get('family-groups/{id}/stock',                    [\App\Http\Controllers\Api\V1\HouseholdStock\HouseholdStockController::class, 'index']);
Route::post('family-groups/{id}/stock',                   [\App\Http\Controllers\Api\V1\HouseholdStock\HouseholdStockController::class, 'store']);
Route::patch('family-groups/{id}/stock/{stockItemId}',    [\App\Http\Controllers\Api\V1\HouseholdStock\HouseholdStockController::class, 'update']);
Route::delete('family-groups/{id}/stock/{stockItemId}',   [\App\Http\Controllers\Api\V1\HouseholdStock\HouseholdStockController::class, 'destroy']);

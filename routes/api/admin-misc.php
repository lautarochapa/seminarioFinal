<?php

use Illuminate\Support\Facades\Route;

// Admin reports
Route::get('admin/reports/users-active', [\App\Http\Controllers\Api\V1\AdminReports\AdminReportController::class, 'usersActive'])
    ->middleware('permission:audit.read');
Route::get('admin/reports/products-pending-review', [\App\Http\Controllers\Api\V1\AdminReports\AdminReportController::class, 'productsPendingReview'])
    ->middleware('permission:scraped_products.review');
Route::get('admin/reports/recipes-pending-review',  [\App\Http\Controllers\Api\V1\AdminReports\AdminReportController::class, 'recipesPendingReview'])
    ->middleware('permission:catalog.manage');
Route::get('admin/reports/price-variations',        [\App\Http\Controllers\Api\V1\AdminReports\AdminReportController::class, 'priceVariations'])
    ->middleware('permission:catalog.manage');
Route::get('admin/reports/most-used-recipes',       [\App\Http\Controllers\Api\V1\AdminReports\AdminReportController::class, 'mostUsedRecipes'])
    ->middleware('permission:catalog.manage');
Route::get('admin/reports/supermarket-price-status',[\App\Http\Controllers\Api\V1\AdminReports\AdminReportController::class, 'supermarketPriceStatus'])
    ->middleware('permission:catalog.manage');

// System settings and feature flags
Route::get('admin/settings', [\App\Http\Controllers\Api\V1\SystemSettings\SystemSettingController::class, 'index'])
    ->middleware('permission:settings.manage');
Route::patch('admin/settings/{key}', [\App\Http\Controllers\Api\V1\SystemSettings\SystemSettingController::class, 'update'])
    ->middleware('permission:settings.manage');

// AI feature flag before generic feature-flags to avoid shadowing
Route::get('admin/feature-flags/ai_enabled', [\App\Http\Controllers\Api\V1\AiFoundation\AiFoundationController::class, 'flag'])
    ->middleware('permission:feature_flags.manage');
Route::get('admin/feature-flags', [\App\Http\Controllers\Api\V1\FeatureFlags\FeatureFlagController::class, 'index'])
    ->middleware('permission:feature_flags.manage');
Route::patch('admin/feature-flags/{key}', [\App\Http\Controllers\Api\V1\FeatureFlags\FeatureFlagController::class, 'update'])
    ->middleware('permission:feature_flags.manage');
Route::post('admin/ai/test-suggestion', [\App\Http\Controllers\Api\V1\AiFoundation\AiFoundationController::class, 'testSuggestion'])
    ->middleware('permission:feature_flags.manage');

<?php

use Illuminate\Support\Facades\Route;

// Demo scenarios — admin and public
Route::get('admin/demo-scenarios',         [\App\Http\Controllers\Api\V1\DemoScenarios\DemoScenarioController::class, 'adminIndex'])
    ->middleware('permission:demo_scenarios.manage');
Route::post('admin/demo-scenarios',        [\App\Http\Controllers\Api\V1\DemoScenarios\DemoScenarioController::class, 'store'])
    ->middleware('permission:demo_scenarios.manage');
Route::get('admin/demo-scenarios/{id}',    [\App\Http\Controllers\Api\V1\DemoScenarios\DemoScenarioController::class, 'adminShow'])
    ->middleware('permission:demo_scenarios.manage');
Route::patch('admin/demo-scenarios/{id}',  [\App\Http\Controllers\Api\V1\DemoScenarios\DemoScenarioController::class, 'update'])
    ->middleware('permission:demo_scenarios.manage');
Route::delete('admin/demo-scenarios/{id}', [\App\Http\Controllers\Api\V1\DemoScenarios\DemoScenarioController::class, 'destroy'])
    ->middleware('permission:demo_scenarios.manage');

Route::get('demo-scenarios',       [\App\Http\Controllers\Api\V1\DemoScenarios\DemoScenarioController::class, 'publicIndex'])
    ->middleware('permission:demo_scenarios.read');
Route::get('demo-scenarios/{id}',  [\App\Http\Controllers\Api\V1\DemoScenarios\DemoScenarioController::class, 'publicShow'])
    ->middleware('permission:demo_scenarios.read');

// Thesis documents — admin
Route::get('admin/thesis-documents', [\App\Http\Controllers\Api\V1\AdminThesisDocuments\AdminThesisDocumentController::class, 'index'])
    ->middleware('permission:thesis_documents.manage');
Route::post('admin/thesis-documents', [\App\Http\Controllers\Api\V1\AdminThesisDocuments\AdminThesisDocumentController::class, 'store'])
    ->middleware('permission:thesis_documents.manage');
Route::get('admin/thesis-documents/{id}/versions', [\App\Http\Controllers\Api\V1\AdminThesisDocuments\AdminThesisDocumentController::class, 'indexVersions'])
    ->middleware('permission:thesis_documents.manage');
Route::post('admin/thesis-documents/{id}/versions', [\App\Http\Controllers\Api\V1\AdminThesisDocuments\AdminThesisDocumentController::class, 'storeVersion'])
    ->middleware('permission:thesis_documents.manage');
Route::post('admin/thesis-documents/{id}/sections', [\App\Http\Controllers\Api\V1\AdminThesisDocuments\AdminThesisDocumentController::class, 'storeSection'])
    ->middleware('permission:thesis_documents.manage');
Route::patch('admin/thesis-documents/{id}/sections/{sectionId}', [\App\Http\Controllers\Api\V1\AdminThesisDocuments\AdminThesisDocumentController::class, 'updateSection'])
    ->middleware('permission:thesis_documents.manage');
Route::delete('admin/thesis-documents/{id}/sections/{sectionId}', [\App\Http\Controllers\Api\V1\AdminThesisDocuments\AdminThesisDocumentController::class, 'destroySection'])
    ->middleware('permission:thesis_documents.manage');
Route::get('admin/thesis-documents/{id}', [\App\Http\Controllers\Api\V1\AdminThesisDocuments\AdminThesisDocumentController::class, 'show'])
    ->middleware('permission:thesis_documents.manage');
Route::patch('admin/thesis-documents/{id}', [\App\Http\Controllers\Api\V1\AdminThesisDocuments\AdminThesisDocumentController::class, 'update'])
    ->middleware('permission:thesis_documents.manage');
Route::delete('admin/thesis-documents/{id}', [\App\Http\Controllers\Api\V1\AdminThesisDocuments\AdminThesisDocumentController::class, 'destroy'])
    ->middleware('permission:thesis_documents.manage');

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

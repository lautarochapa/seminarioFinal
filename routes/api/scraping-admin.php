<?php

use Illuminate\Support\Facades\Route;

// Scraping — product candidates (specific actions before generic {id})
Route::post('admin/scraping/product-candidates/{id}/approve', [\App\Http\Controllers\Api\V1\ScrapingCandidates\ScrapingCandidateController::class, 'approve'])
    ->middleware('permission:catalog.manage');
Route::post('admin/scraping/product-candidates/{id}/reject', [\App\Http\Controllers\Api\V1\ScrapingCandidates\ScrapingCandidateController::class, 'reject'])
    ->middleware('permission:catalog.manage');
Route::post('admin/scraping/product-candidates/{id}/match-product', [\App\Http\Controllers\Api\V1\ScrapingCandidates\ScrapingCandidateController::class, 'matchProduct'])
    ->middleware('permission:catalog.manage');
Route::post('admin/scraping/product-candidates/{id}/create-product', [\App\Http\Controllers\Api\V1\ScrapingCandidates\ScrapingCandidateController::class, 'createProduct'])
    ->middleware('permission:catalog.manage');
Route::post('admin/scraping/product-candidates/{id}/assign-ingredient', [\App\Http\Controllers\Api\V1\ScrapingCandidates\ScrapingCandidateController::class, 'assignIngredient'])
    ->middleware('permission:catalog.manage');
Route::get('admin/scraping/product-candidates/{id}', [\App\Http\Controllers\Api\V1\ScrapingCandidates\ScrapingCandidateController::class, 'show'])
    ->middleware('permission:catalog.manage');
Route::get('admin/scraping/product-candidates', [\App\Http\Controllers\Api\V1\ScrapingCandidates\ScrapingCandidateController::class, 'index'])
    ->middleware('permission:catalog.manage');

// Scraping — alerts
Route::get('admin/scraping/alerts', [\App\Http\Controllers\Api\V1\ScrapingAlerts\ScrapingAlertController::class, 'index'])
    ->middleware('permission:scraping.manage');
Route::patch('admin/scraping/alerts/{id}/resolve', [\App\Http\Controllers\Api\V1\ScrapingAlerts\ScrapingAlertController::class, 'resolve'])
    ->middleware('permission:scraping.manage');
Route::get('admin/reports/scraping-errors', [\App\Http\Controllers\Api\V1\ScrapingAlerts\ScrapingAlertController::class, 'report'])
    ->middleware('permission:scraping.manage');

// Scraping — sources and jobs (retry/cancel/logs before {id} to avoid collision)
Route::get('admin/scraping/sources', [\App\Http\Controllers\Api\V1\Scraping\ScrapingSourceController::class, 'index'])
    ->middleware('permission:catalog.manage');
Route::post('admin/scraping/sources', [\App\Http\Controllers\Api\V1\Scraping\ScrapingSourceController::class, 'store'])
    ->middleware('permission:catalog.manage');

Route::post('admin/scraping/jobs/{id}/retry', [\App\Http\Controllers\Api\V1\Scraping\ScrapingJobController::class, 'retry'])
    ->middleware('permission:catalog.manage');
Route::post('admin/scraping/jobs/{id}/cancel', [\App\Http\Controllers\Api\V1\Scraping\ScrapingJobController::class, 'cancel'])
    ->middleware('permission:catalog.manage');
Route::get('admin/scraping/jobs/{id}/logs', [\App\Http\Controllers\Api\V1\Scraping\ScrapingJobController::class, 'logs'])
    ->middleware('permission:catalog.manage');
Route::get('admin/scraping/jobs/{id}', [\App\Http\Controllers\Api\V1\Scraping\ScrapingJobController::class, 'show'])
    ->middleware('permission:catalog.manage');
Route::get('admin/scraping/jobs', [\App\Http\Controllers\Api\V1\Scraping\ScrapingJobController::class, 'index'])
    ->middleware('permission:catalog.manage');
Route::post('admin/scraping/jobs', [\App\Http\Controllers\Api\V1\Scraping\ScrapingJobController::class, 'store'])
    ->middleware('permission:catalog.manage');

// Price refresh requests — admin
Route::get('admin/price-refresh-requests', [\App\Http\Controllers\Api\V1\PriceRefreshRequests\PriceRefreshRequestController::class, 'adminIndex'])
    ->middleware('permission:scraping.manage');
Route::post('admin/price-refresh-requests/{id}/process', [\App\Http\Controllers\Api\V1\PriceRefreshRequests\PriceRefreshRequestController::class, 'process'])
    ->middleware('permission:scraping.manage');

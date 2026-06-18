<?php

use Illuminate\Support\Facades\Route;

// Group reports
Route::get('family-groups/{id}/reports/stock',              [\App\Http\Controllers\Api\V1\GroupReports\GroupReportController::class, 'stock']);
Route::get('family-groups/{id}/reports/stock-value',        [\App\Http\Controllers\Api\V1\GroupReports\GroupReportController::class, 'stockValue']);
Route::get('family-groups/{id}/reports/expiring-products',  [\App\Http\Controllers\Api\V1\GroupReports\GroupReportController::class, 'expiringProducts']);
Route::get('family-groups/{id}/reports/purchases',          [\App\Http\Controllers\Api\V1\GroupReports\GroupReportController::class, 'purchases']);
Route::get('family-groups/{id}/reports/budget',             [\App\Http\Controllers\Api\V1\GroupReports\GroupReportController::class, 'budget']);
Route::get('family-groups/{id}/reports/budget-vs-actual',   [\App\Http\Controllers\Api\V1\GroupReports\GroupReportController::class, 'budgetVsActual']);
Route::get('family-groups/{id}/reports/recipes-cooked',     [\App\Http\Controllers\Api\V1\GroupReports\GroupReportController::class, 'recipesCooked']);
Route::get('family-groups/{id}/reports/nutrition-estimate', [\App\Http\Controllers\Api\V1\GroupReports\GroupReportController::class, 'nutritionEstimate']);
Route::post('family-groups/{id}/reports/export',            [\App\Http\Controllers\Api\V1\ReportExports\ReportExportController::class, 'store']);

// Report exports
Route::get('report-exports/{id}', [\App\Http\Controllers\Api\V1\ReportExports\ReportExportController::class, 'show']);

// Thesis documents — public
Route::get('thesis-documents',               [\App\Http\Controllers\Api\V1\ThesisDocuments\ThesisDocumentController::class, 'index'])
    ->middleware('permission:thesis_documents.read');
Route::get('thesis-documents/{id}/sections', [\App\Http\Controllers\Api\V1\ThesisDocuments\ThesisDocumentController::class, 'sections'])
    ->middleware('permission:thesis_documents.read');
Route::get('thesis-documents/{id}',          [\App\Http\Controllers\Api\V1\ThesisDocuments\ThesisDocumentController::class, 'show'])
    ->middleware('permission:thesis_documents.read');
Route::get('thesis-documents/{id}/comments',  [\App\Http\Controllers\Api\V1\ThesisDocuments\ThesisCommentController::class, 'index'])
    ->middleware('permission:thesis_documents.read');
Route::post('thesis-documents/{id}/comments', [\App\Http\Controllers\Api\V1\ThesisDocuments\ThesisCommentController::class, 'store'])
    ->middleware('permission:thesis_comments.write');

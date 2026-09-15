<?php

use Illuminate\Support\Facades\Route;

// Meal plan incompatibilities
Route::get('family-groups/{id}/meal-plans/{planId}/incompatibilities',        [\App\Http\Controllers\Api\V1\MealPlanIncompatibilities\MealPlanIncompatibilityController::class, 'index']);
Route::post('family-groups/{id}/meal-plans/{planId}/check-incompatibilities', [\App\Http\Controllers\Api\V1\MealPlanIncompatibilities\MealPlanIncompatibilityController::class, 'check']);

// Meal plan portions
Route::get('family-groups/{id}/meal-plans/{planId}/items/{itemId}/portions',                 [\App\Http\Controllers\Api\V1\MealPlanPortions\MealPlanPortionController::class, 'index']);
Route::post('family-groups/{id}/meal-plans/{planId}/items/{itemId}/portions',                [\App\Http\Controllers\Api\V1\MealPlanPortions\MealPlanPortionController::class, 'store']);
Route::patch('family-groups/{id}/meal-plans/{planId}/items/{itemId}/portions/{portionId}',   [\App\Http\Controllers\Api\V1\MealPlanPortions\MealPlanPortionController::class, 'update']);

// Meal plan items — specific actions before wildcard {itemId}
Route::get('family-groups/{id}/meal-plans/{planId}/items',                              [\App\Http\Controllers\Api\V1\MealPlanItems\MealPlanItemController::class, 'index']);
Route::post('family-groups/{id}/meal-plans/{planId}/items',                             [\App\Http\Controllers\Api\V1\MealPlanItems\MealPlanItemController::class, 'store']);
Route::post('family-groups/{id}/meal-plans/{planId}/items/{itemId}/mark-cooked',        [\App\Http\Controllers\Api\V1\MealPlanItemStatus\MealPlanItemStatusController::class, 'markCooked']);
Route::post('family-groups/{id}/meal-plans/{planId}/items/{itemId}/skip',               [\App\Http\Controllers\Api\V1\MealPlanItemStatus\MealPlanItemStatusController::class, 'skip']);
Route::patch('family-groups/{id}/meal-plans/{planId}/items/{itemId}',                   [\App\Http\Controllers\Api\V1\MealPlanItems\MealPlanItemController::class, 'update']);
Route::delete('family-groups/{id}/meal-plans/{planId}/items/{itemId}',                  [\App\Http\Controllers\Api\V1\MealPlanItems\MealPlanItemController::class, 'destroy']);

// Meal plan preferences (group-level knobs used by the generator: respect_budget, etc.)
Route::get('family-groups/{id}/meal-plan-preferences',   [\App\Http\Controllers\Api\V1\MealPlanPreferences\MealPlanPreferenceController::class, 'show']);
Route::patch('family-groups/{id}/meal-plan-preferences', [\App\Http\Controllers\Api\V1\MealPlanPreferences\MealPlanPreferenceController::class, 'update']);

// Meal plan generation and shopping list preview — before CRUD {planId} to avoid shadowing
Route::post('family-groups/{id}/meal-plans/generate',             [\App\Http\Controllers\Api\V1\MealPlanGeneration\MealPlanGenerationController::class, 'generate']);
Route::post('family-groups/{id}/meal-plans/{planId}/approve',     [\App\Http\Controllers\Api\V1\MealPlanGeneration\MealPlanGenerationController::class, 'approve']);
Route::post('family-groups/{id}/meal-plans/{planId}/regenerate',  [\App\Http\Controllers\Api\V1\MealPlanGeneration\MealPlanGenerationController::class, 'regenerate']);
Route::get('family-groups/{id}/meal-plans/{planId}/shopping-list-preview',    [\App\Http\Controllers\Api\V1\ShoppingListPreview\ShoppingListPreviewController::class, 'preview']);
Route::post('family-groups/{id}/meal-plans/{planId}/generate-shopping-list',  [\App\Http\Controllers\Api\V1\ShoppingListPreview\ShoppingListPreviewController::class, 'generate']);

// Meal plans CRUD
Route::get('family-groups/{id}/meal-plans',             [\App\Http\Controllers\Api\V1\MealPlans\MealPlanController::class, 'index']);
Route::post('family-groups/{id}/meal-plans',            [\App\Http\Controllers\Api\V1\MealPlans\MealPlanController::class, 'store']);
Route::get('family-groups/{id}/meal-plans/{planId}',    [\App\Http\Controllers\Api\V1\MealPlans\MealPlanController::class, 'show']);
Route::patch('family-groups/{id}/meal-plans/{planId}',  [\App\Http\Controllers\Api\V1\MealPlans\MealPlanController::class, 'update']);
Route::delete('family-groups/{id}/meal-plans/{planId}', [\App\Http\Controllers\Api\V1\MealPlans\MealPlanController::class, 'destroy']);

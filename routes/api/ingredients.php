<?php

use Illuminate\Support\Facades\Route;

// Ingredient equivalences — admin
Route::get('admin/ingredient-equivalences', [\App\Http\Controllers\Api\V1\IngredientEquivalences\AdminIngredientEquivalenceController::class, 'index'])
    ->middleware('permission:catalog.manage');
Route::post('admin/ingredient-equivalences', [\App\Http\Controllers\Api\V1\IngredientEquivalences\AdminIngredientEquivalenceController::class, 'store'])
    ->middleware('permission:catalog.manage');
Route::get('admin/ingredient-equivalences/{id}/audit', [\App\Http\Controllers\Api\V1\IngredientEquivalences\AdminIngredientEquivalenceController::class, 'audit'])
    ->middleware('permission:audit.read');
Route::patch('admin/ingredient-equivalences/{id}/restore', [\App\Http\Controllers\Api\V1\IngredientEquivalences\AdminIngredientEquivalenceController::class, 'restore'])
    ->middleware('permission:catalog.manage');
Route::get('admin/ingredient-equivalences/{id}', [\App\Http\Controllers\Api\V1\IngredientEquivalences\AdminIngredientEquivalenceController::class, 'show'])
    ->middleware('permission:catalog.manage');
Route::patch('admin/ingredient-equivalences/{id}', [\App\Http\Controllers\Api\V1\IngredientEquivalences\AdminIngredientEquivalenceController::class, 'update'])
    ->middleware('permission:catalog.manage');
Route::delete('admin/ingredient-equivalences/{id}', [\App\Http\Controllers\Api\V1\IngredientEquivalences\AdminIngredientEquivalenceController::class, 'destroy'])
    ->middleware('permission:catalog.manage');

// Ingredients — admin and catalog
Route::get('admin/ingredients', [\App\Http\Controllers\Api\V1\Ingredients\AdminIngredientController::class, 'index'])
    ->middleware('permission:catalog.manage');
Route::post('admin/ingredients', [\App\Http\Controllers\Api\V1\Ingredients\AdminIngredientController::class, 'store'])
    ->middleware('permission:catalog.manage');
Route::get('admin/ingredients/{id}/audit', [\App\Http\Controllers\Api\V1\Ingredients\AdminIngredientController::class, 'audit'])
    ->middleware('permission:audit.read');
Route::patch('admin/ingredients/{id}/restore', [\App\Http\Controllers\Api\V1\Ingredients\AdminIngredientController::class, 'restore'])
    ->middleware('permission:catalog.manage');
Route::get('admin/ingredients/{id}', [\App\Http\Controllers\Api\V1\Ingredients\AdminIngredientController::class, 'show'])
    ->middleware('permission:catalog.manage');
Route::patch('admin/ingredients/{id}', [\App\Http\Controllers\Api\V1\Ingredients\AdminIngredientController::class, 'update'])
    ->middleware('permission:catalog.manage');
Route::delete('admin/ingredients/{id}', [\App\Http\Controllers\Api\V1\Ingredients\AdminIngredientController::class, 'destroy'])
    ->middleware('permission:catalog.manage');

// Specific sub-resources before wildcard {id}
Route::get('ingredients/{id}/nutrition',    [\App\Http\Controllers\Api\V1\Ingredients\IngredientCatalogController::class, 'nutrition']);
Route::get('ingredients/{id}/equivalences', [\App\Http\Controllers\Api\V1\Ingredients\IngredientCatalogController::class, 'equivalences']);
Route::get('ingredients/{id}',              [\App\Http\Controllers\Api\V1\Ingredients\IngredientCatalogController::class, 'show']);
Route::get('ingredients',                   [\App\Http\Controllers\Api\V1\Ingredients\IngredientCatalogController::class, 'index']);

// Ingredient categories — admin and catalog
Route::get('admin/ingredient-categories', [\App\Http\Controllers\Api\V1\IngredientCategories\IngredientCategoryAdminController::class, 'index'])
    ->middleware('permission:catalog.manage');
Route::post('admin/ingredient-categories', [\App\Http\Controllers\Api\V1\IngredientCategories\IngredientCategoryAdminController::class, 'store'])
    ->middleware('permission:catalog.manage');
Route::patch('admin/ingredient-categories/{id}/restore', [\App\Http\Controllers\Api\V1\IngredientCategories\IngredientCategoryAdminController::class, 'restore'])
    ->middleware('permission:catalog.manage');
Route::get('admin/ingredient-categories/{id}', [\App\Http\Controllers\Api\V1\IngredientCategories\IngredientCategoryAdminController::class, 'show'])
    ->middleware('permission:catalog.manage');
Route::patch('admin/ingredient-categories/{id}', [\App\Http\Controllers\Api\V1\IngredientCategories\IngredientCategoryAdminController::class, 'update'])
    ->middleware('permission:catalog.manage');
Route::delete('admin/ingredient-categories/{id}', [\App\Http\Controllers\Api\V1\IngredientCategories\IngredientCategoryAdminController::class, 'destroy'])
    ->middleware('permission:catalog.manage');

Route::get('ingredient-categories', [\App\Http\Controllers\Api\V1\IngredientCategories\IngredientCategoryCatalogController::class, 'index']);

// Nutrients — admin, ingredient nutrients, and product nutrients
Route::get('admin/nutrients', [\App\Http\Controllers\Api\V1\Nutrients\AdminNutrientController::class, 'index'])
    ->middleware('permission:catalog.manage');
Route::post('admin/nutrients', [\App\Http\Controllers\Api\V1\Nutrients\AdminNutrientController::class, 'store'])
    ->middleware('permission:catalog.manage');
Route::get('admin/nutrients/{id}/audit', [\App\Http\Controllers\Api\V1\Nutrients\AdminNutrientController::class, 'audit'])
    ->middleware('permission:audit.read');
Route::patch('admin/nutrients/{id}/restore', [\App\Http\Controllers\Api\V1\Nutrients\AdminNutrientController::class, 'restore'])
    ->middleware('permission:catalog.manage');
Route::get('admin/nutrients/{id}', [\App\Http\Controllers\Api\V1\Nutrients\AdminNutrientController::class, 'show'])
    ->middleware('permission:catalog.manage');
Route::patch('admin/nutrients/{id}', [\App\Http\Controllers\Api\V1\Nutrients\AdminNutrientController::class, 'update'])
    ->middleware('permission:catalog.manage');
Route::delete('admin/nutrients/{id}', [\App\Http\Controllers\Api\V1\Nutrients\AdminNutrientController::class, 'destroy'])
    ->middleware('permission:catalog.manage');

Route::get('admin/ingredients/{id}/nutrients', [\App\Http\Controllers\Api\V1\Nutrients\IngredientNutrientController::class, 'index'])
    ->middleware('permission:catalog.manage');
Route::post('admin/ingredients/{id}/nutrients', [\App\Http\Controllers\Api\V1\Nutrients\IngredientNutrientController::class, 'store'])
    ->middleware('permission:catalog.manage');
Route::patch('admin/ingredients/{id}/nutrients/{nutrientId}', [\App\Http\Controllers\Api\V1\Nutrients\IngredientNutrientController::class, 'update'])
    ->middleware('permission:catalog.manage');

Route::get('admin/products/{id}/nutrients', [\App\Http\Controllers\Api\V1\Nutrients\ProductNutrientController::class, 'index'])
    ->middleware('permission:catalog.manage');
Route::post('admin/products/{id}/nutrients', [\App\Http\Controllers\Api\V1\Nutrients\ProductNutrientController::class, 'store'])
    ->middleware('permission:catalog.manage');

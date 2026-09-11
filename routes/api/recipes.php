<?php

use Illuminate\Support\Facades\Route;

// Recipe categories — admin and catalog
Route::get('admin/recipe-categories', [\App\Http\Controllers\Api\V1\RecipeCategories\RecipeCategoryAdminController::class, 'index'])
    ->middleware('permission:catalog.manage');
Route::post('admin/recipe-categories', [\App\Http\Controllers\Api\V1\RecipeCategories\RecipeCategoryAdminController::class, 'store'])
    ->middleware('permission:catalog.manage');
Route::patch('admin/recipe-categories/{id}/restore', [\App\Http\Controllers\Api\V1\RecipeCategories\RecipeCategoryAdminController::class, 'restore'])
    ->middleware('permission:catalog.manage');
Route::get('admin/recipe-categories/{id}', [\App\Http\Controllers\Api\V1\RecipeCategories\RecipeCategoryAdminController::class, 'show'])
    ->middleware('permission:catalog.manage');
Route::patch('admin/recipe-categories/{id}', [\App\Http\Controllers\Api\V1\RecipeCategories\RecipeCategoryAdminController::class, 'update'])
    ->middleware('permission:catalog.manage');
Route::delete('admin/recipe-categories/{id}', [\App\Http\Controllers\Api\V1\RecipeCategories\RecipeCategoryAdminController::class, 'destroy'])
    ->middleware('permission:catalog.manage');

Route::get('recipe-categories', [\App\Http\Controllers\Api\V1\RecipeCategories\RecipeCategoryCatalogController::class, 'index']);

// Meal types — catalog and admin
Route::get('meal-types', [\App\Http\Controllers\Api\V1\MealTypes\MealTypeCatalogController::class, 'index']);

Route::get('admin/meal-types', [\App\Http\Controllers\Api\V1\MealTypes\MealTypeAdminController::class, 'index'])
    ->middleware('permission:catalog.manage');
Route::post('admin/meal-types', [\App\Http\Controllers\Api\V1\MealTypes\MealTypeAdminController::class, 'store'])
    ->middleware('permission:catalog.manage');
Route::patch('admin/meal-types/{id}/restore', [\App\Http\Controllers\Api\V1\MealTypes\MealTypeAdminController::class, 'restore'])
    ->middleware('permission:catalog.manage');
Route::get('admin/meal-types/{id}', [\App\Http\Controllers\Api\V1\MealTypes\MealTypeAdminController::class, 'show'])
    ->middleware('permission:catalog.manage');
Route::patch('admin/meal-types/{id}', [\App\Http\Controllers\Api\V1\MealTypes\MealTypeAdminController::class, 'update'])
    ->middleware('permission:catalog.manage');
Route::delete('admin/meal-types/{id}', [\App\Http\Controllers\Api\V1\MealTypes\MealTypeAdminController::class, 'destroy'])
    ->middleware('permission:catalog.manage');

// Recipe tags — admin and catalog
Route::get('admin/recipe-tags', [\App\Http\Controllers\Api\V1\RecipeTags\RecipeTagAdminController::class, 'index'])
    ->middleware('permission:catalog.manage');
Route::post('admin/recipe-tags', [\App\Http\Controllers\Api\V1\RecipeTags\RecipeTagAdminController::class, 'store'])
    ->middleware('permission:catalog.manage');
Route::patch('admin/recipe-tags/{id}/restore', [\App\Http\Controllers\Api\V1\RecipeTags\RecipeTagAdminController::class, 'restore'])
    ->middleware('permission:catalog.manage');
Route::get('admin/recipe-tags/{id}', [\App\Http\Controllers\Api\V1\RecipeTags\RecipeTagAdminController::class, 'show'])
    ->middleware('permission:catalog.manage');
Route::patch('admin/recipe-tags/{id}', [\App\Http\Controllers\Api\V1\RecipeTags\RecipeTagAdminController::class, 'update'])
    ->middleware('permission:catalog.manage');
Route::delete('admin/recipe-tags/{id}', [\App\Http\Controllers\Api\V1\RecipeTags\RecipeTagAdminController::class, 'destroy'])
    ->middleware('permission:catalog.manage');

Route::get('recipe-tags', [\App\Http\Controllers\Api\V1\RecipeTags\RecipeTagCatalogController::class, 'index']);

// Admin recipes — specific sub-routes before wildcard {id}
Route::get('admin/recipes', [\App\Http\Controllers\Api\V1\Recipes\AdminRecipeController::class, 'index'])
    ->middleware('permission:catalog.manage');
Route::post('admin/recipes', [\App\Http\Controllers\Api\V1\Recipes\AdminRecipeController::class, 'store'])
    ->middleware('permission:catalog.manage');

// Import candidates before {id} to prevent shadowing
Route::get('admin/recipes/import-candidates', [\App\Http\Controllers\Api\V1\RecipeImportCandidates\RecipeImportCandidatesController::class, 'index'])
    ->middleware('permission:recipes.manage');
Route::patch('admin/recipes/import-candidates/{id}', [\App\Http\Controllers\Api\V1\RecipeImportCandidates\RecipeImportCandidatesController::class, 'update'])
    ->middleware('permission:recipes.manage');
Route::post('admin/recipes/import-candidates/{id}/approve', [\App\Http\Controllers\Api\V1\RecipeImportCandidates\RecipeImportCandidatesController::class, 'approve'])
    ->middleware('permission:recipes.manage');
Route::post('admin/recipes/import-candidates/{id}/reject', [\App\Http\Controllers\Api\V1\RecipeImportCandidates\RecipeImportCandidatesController::class, 'reject'])
    ->middleware('permission:recipes.manage');
Route::post('admin/recipes/import-candidates/{id}/map-ingredient', [\App\Http\Controllers\Api\V1\RecipeImportCandidates\RecipeImportCandidatesController::class, 'mapIngredient'])
    ->middleware('permission:recipes.manage');
Route::post('admin/recipes/import-candidates/{id}/create-recipe', [\App\Http\Controllers\Api\V1\RecipeImportCandidates\RecipeImportCandidatesController::class, 'createRecipe'])
    ->middleware('permission:recipes.manage');
Route::post('admin/recipes/import-candidates/{id}/recalculate-suggestions', [\App\Http\Controllers\Api\V1\RecipeImportCandidates\RecipeImportCandidatesController::class, 'recalculateSuggestions'])
    ->middleware('permission:recipes.manage');
Route::post('admin/recipes/import-candidates/recalculate-suggestions-bulk', [\App\Http\Controllers\Api\V1\RecipeImportCandidates\RecipeImportCandidatesController::class, 'recalculateSuggestionsBulk'])
    ->middleware('permission:recipes.manage');
Route::post('admin/recipes/import-candidates/{id}/apply-suggestions', [\App\Http\Controllers\Api\V1\RecipeImportCandidates\RecipeImportCandidatesController::class, 'applySuggestions'])
    ->middleware('permission:recipes.manage');
Route::post('admin/recipes/import-candidates/apply-suggestions-bulk', [\App\Http\Controllers\Api\V1\RecipeImportCandidates\RecipeImportCandidatesController::class, 'applySuggestionsBulk'])
    ->middleware('permission:recipes.manage');
Route::post('admin/recipes/import-candidates/approve-bulk', [\App\Http\Controllers\Api\V1\RecipeImportCandidates\RecipeImportCandidatesController::class, 'approveBulk'])
    ->middleware('permission:recipes.manage');
Route::get('admin/recipes/import-candidates/{id}', [\App\Http\Controllers\Api\V1\RecipeImportCandidates\RecipeImportCandidatesController::class, 'show'])
    ->middleware('permission:recipes.manage');

// Scraping jobs before {id}
Route::post('admin/recipes/scraping/jobs', [\App\Http\Controllers\Api\V1\RecipeScraping\RecipeScrapingJobController::class, 'store'])
    ->middleware('permission:recipes.manage');
Route::get('admin/recipes/scraping/jobs', [\App\Http\Controllers\Api\V1\RecipeScraping\RecipeScrapingJobController::class, 'index'])
    ->middleware('permission:recipes.manage');
Route::get('admin/recipes/scraping/jobs/{id}', [\App\Http\Controllers\Api\V1\RecipeScraping\RecipeScrapingJobController::class, 'show'])
    ->middleware('permission:recipes.manage');
Route::post('admin/recipes/scraping/jobs/{id}/retry', [\App\Http\Controllers\Api\V1\RecipeScraping\RecipeScrapingJobController::class, 'retry'])
    ->middleware('permission:recipes.manage');

// Recipe import
Route::post('admin/recipes/import/text', [\App\Http\Controllers\Api\V1\RecipeImportText\RecipeImportTextController::class, '__invoke'])
    ->middleware('permission:recipes.manage');
Route::post('admin/recipes/import/url', [\App\Http\Controllers\Api\V1\RecipeImportUrl\RecipeImportUrlController::class, '__invoke'])
    ->middleware('permission:recipes.manage');

// Admin recipe operations requiring {id} — after all specific sub-prefixes
Route::get('admin/recipes/{id}', [\App\Http\Controllers\Api\V1\Recipes\AdminRecipeController::class, 'show'])
    ->middleware('permission:catalog.manage');
Route::patch('admin/recipes/{id}', [\App\Http\Controllers\Api\V1\Recipes\AdminRecipeController::class, 'update'])
    ->middleware('permission:catalog.manage');
Route::delete('admin/recipes/{id}', [\App\Http\Controllers\Api\V1\Recipes\AdminRecipeController::class, 'destroy'])
    ->middleware('permission:catalog.manage');

Route::post('admin/recipes/{id}/recalculate-cost', [\App\Http\Controllers\Api\V1\RecipeCost\RecipeCostController::class, 'recalculate'])
    ->middleware('permission:recipes.manage');
Route::post('admin/recipes/{id}/recalculate-nutrition', [\App\Http\Controllers\Api\V1\RecipeNutrition\RecipeNutritionController::class, 'recalculate'])
    ->middleware('permission:recipes.manage');

// Recipe catalog — specific sub-routes before wildcard {id}
Route::get('recipes/{id}/availability',         [\App\Http\Controllers\Api\V1\RecipeAvailability\RecipeAvailabilityController::class, 'availability']);
Route::get('recipes/{id}/missing-ingredients',  [\App\Http\Controllers\Api\V1\RecipeAvailability\RecipeAvailabilityController::class, 'missingIngredients']);
Route::get('recipes/{id}/cost',                 [\App\Http\Controllers\Api\V1\RecipeCost\RecipeCostController::class, 'show']);
Route::get('recipes/{id}/nutrition',            [\App\Http\Controllers\Api\V1\RecipeNutrition\RecipeNutritionController::class, 'show']);
Route::post('recipes/{id}/steps',               [\App\Http\Controllers\Api\V1\RecipeSteps\RecipeStepController::class, 'store']);
Route::patch('recipes/{id}/steps/{stepId}',     [\App\Http\Controllers\Api\V1\RecipeSteps\RecipeStepController::class, 'update']);
Route::delete('recipes/{id}/steps/{stepId}',    [\App\Http\Controllers\Api\V1\RecipeSteps\RecipeStepController::class, 'destroy']);
Route::post('recipes/{id}/ingredients',                              [\App\Http\Controllers\Api\V1\RecipeIngredients\RecipeIngredientController::class, 'store']);
Route::patch('recipes/{id}/ingredients/{ingredientId}',             [\App\Http\Controllers\Api\V1\RecipeIngredients\RecipeIngredientController::class, 'update']);
Route::delete('recipes/{id}/ingredients/{ingredientId}',            [\App\Http\Controllers\Api\V1\RecipeIngredients\RecipeIngredientController::class, 'destroy']);
Route::get('recipes/{id}/substitutions',        [\App\Http\Controllers\Api\V1\RecipeSubstitutions\RecipeSubstitutionsController::class, '__invoke']);
Route::post('recipes/{id}/share',               [\App\Http\Controllers\Api\V1\RecipeSharingBranch\RecipeSharingBranchController::class, 'share']);
Route::post('recipes/{id}/unshare',             [\App\Http\Controllers\Api\V1\RecipeSharingBranch\RecipeSharingBranchController::class, 'unshare']);
Route::post('recipes/{id}/branch',              [\App\Http\Controllers\Api\V1\RecipeSharingBranch\RecipeSharingBranchController::class, 'branch']);
Route::post('recipes/{id}/favorite',            [\App\Http\Controllers\Api\V1\RecipeFavoritesCooked\RecipeFavoritesCookedController::class, 'addFavorite']);
Route::delete('recipes/{id}/favorite',          [\App\Http\Controllers\Api\V1\RecipeFavoritesCooked\RecipeFavoritesCookedController::class, 'removeFavorite']);
Route::post('recipes/{id}/cook',                [\App\Http\Controllers\Api\V1\RecipeFavoritesCooked\RecipeFavoritesCookedController::class, 'cook']);

// User recipe lists
Route::get('users/me/favorite-recipes', [\App\Http\Controllers\Api\V1\RecipeFavoritesCooked\RecipeFavoritesCookedController::class, 'listFavorites']);
Route::get('users/me/cooked-recipes',   [\App\Http\Controllers\Api\V1\RecipeFavoritesCooked\RecipeFavoritesCookedController::class, 'listCooked']);

// Family group recipe suggestions
Route::get('family-groups/{id}/recipes/available',         [\App\Http\Controllers\Api\V1\RecipeSuggestions\RecipeSuggestionsController::class, 'available']);
Route::get('family-groups/{id}/recipes/almost-available',  [\App\Http\Controllers\Api\V1\RecipeSuggestions\RecipeSuggestionsController::class, 'almostAvailable']);
Route::get('family-groups/{id}/recipes/by-expiring-stock', [\App\Http\Controllers\Api\V1\RecipeSuggestions\RecipeSuggestionsController::class, 'byExpiringStock']);
Route::get('family-groups/{id}/recipes/by-budget',         [\App\Http\Controllers\Api\V1\RecipeSuggestions\RecipeSuggestionsController::class, 'byBudget']);
Route::get('family-groups/{id}/recipes/by-objectives',     [\App\Http\Controllers\Api\V1\RecipeSuggestions\RecipeSuggestionsController::class, 'byObjectives']);

// Recipe search and suggestions — before wildcard {id}
Route::get('recipes/search',      [\App\Http\Controllers\Api\V1\RecipeSearch\RecipeSearchController::class, '__invoke']);
Route::get('recipes/suggestions', [\App\Http\Controllers\Api\V1\RecipeSuggestions\RecipeSuggestionsController::class, 'suggestions']);

// Recipes CRUD
Route::get('recipes',        [\App\Http\Controllers\Api\V1\Recipes\RecipeController::class, 'index']);
Route::post('recipes',       [\App\Http\Controllers\Api\V1\Recipes\RecipeController::class, 'store']);
Route::get('recipes/{id}',   [\App\Http\Controllers\Api\V1\Recipes\RecipeController::class, 'show']);
Route::patch('recipes/{id}', [\App\Http\Controllers\Api\V1\Recipes\RecipeController::class, 'update']);
Route::delete('recipes/{id}',[\App\Http\Controllers\Api\V1\Recipes\RecipeController::class, 'destroy']);

<?php

use Illuminate\Support\Facades\Route;

// Shopping list generation — specific routes before wildcard {listId}
Route::get('family-groups/{id}/shopping-lists',                                        [\App\Http\Controllers\Api\V1\ShoppingLists\ShoppingListController::class, 'index']);
Route::post('family-groups/{id}/shopping-lists',                                       [\App\Http\Controllers\Api\V1\ShoppingLists\ShoppingListController::class, 'store']);
Route::post('family-groups/{id}/shopping-lists/generate-from-meal-plan',               [\App\Http\Controllers\Api\V1\ShoppingListGeneration\ShoppingListGenerationController::class, 'fromMealPlan']);
Route::post('family-groups/{id}/shopping-lists/generate-from-history',                 [\App\Http\Controllers\Api\V1\ShoppingListGeneration\ShoppingListGenerationController::class, 'fromHistory']);
Route::post('family-groups/{id}/recipes/{recipeId}/shopping-list',                     [\App\Http\Controllers\Api\V1\RecipeShoppingList\RecipeShoppingListController::class, 'store']);

// Shopping list {listId} sub-resources — specific before wildcard
Route::post('family-groups/{id}/shopping-lists/{listId}/start-session',                [\App\Http\Controllers\Api\V1\ShoppingSessions\ShoppingSessionController::class, 'start']);
Route::get('family-groups/{id}/shopping-lists/{listId}/compare-supermarkets',          [\App\Http\Controllers\Api\V1\SupermarketComparison\SupermarketComparisonController::class, 'compare']);
Route::get('family-groups/{id}/shopping-lists/{listId}/optimize',                      [\App\Http\Controllers\Api\V1\SupermarketComparison\SupermarketComparisonController::class, 'optimize']);
Route::get('family-groups/{id}/shopping-lists/{listId}/alternatives',                  [\App\Http\Controllers\Api\V1\ShoppingAlternatives\ShoppingAlternativeController::class, 'index']);
Route::get('family-groups/{id}/shopping-lists/{listId}/items',                         [\App\Http\Controllers\Api\V1\ShoppingListItems\ShoppingListItemController::class, 'index']);
Route::post('family-groups/{id}/shopping-lists/{listId}/items',                        [\App\Http\Controllers\Api\V1\ShoppingListItems\ShoppingListItemController::class, 'store']);
Route::post('family-groups/{id}/shopping-lists/{listId}/items/{itemId}/select-alternative', [\App\Http\Controllers\Api\V1\ShoppingAlternatives\ShoppingAlternativeController::class, 'select']);
Route::patch('family-groups/{id}/shopping-lists/{listId}/items/{itemId}',              [\App\Http\Controllers\Api\V1\ShoppingListItems\ShoppingListItemController::class, 'update']);
Route::delete('family-groups/{id}/shopping-lists/{listId}/items/{itemId}',             [\App\Http\Controllers\Api\V1\ShoppingListItems\ShoppingListItemController::class, 'destroy']);

// Shopping list CRUD
Route::get('family-groups/{id}/shopping-lists/{listId}',    [\App\Http\Controllers\Api\V1\ShoppingLists\ShoppingListController::class, 'show']);
Route::patch('family-groups/{id}/shopping-lists/{listId}',  [\App\Http\Controllers\Api\V1\ShoppingLists\ShoppingListController::class, 'update']);
Route::delete('family-groups/{id}/shopping-lists/{listId}', [\App\Http\Controllers\Api\V1\ShoppingLists\ShoppingListController::class, 'destroy']);

// Shopping sessions
Route::patch('family-groups/{id}/shopping-sessions/{sessionId}',        [\App\Http\Controllers\Api\V1\ShoppingSessions\ShoppingSessionController::class, 'update']);
Route::post('family-groups/{id}/shopping-sessions/{sessionId}/scan',    [\App\Http\Controllers\Api\V1\ShoppingSessions\ShoppingSessionController::class, 'scan']);
Route::post('family-groups/{id}/shopping-sessions/{sessionId}/finish',  [\App\Http\Controllers\Api\V1\ShoppingSessions\ShoppingSessionController::class, 'finish']);

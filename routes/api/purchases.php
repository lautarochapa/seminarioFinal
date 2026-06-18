<?php

use Illuminate\Support\Facades\Route;

// Purchases — specific sub-resources before wildcard {purchaseId}
Route::get('family-groups/{id}/purchases',                                        [\App\Http\Controllers\Api\V1\Purchases\PurchaseController::class, 'index']);
Route::post('family-groups/{id}/purchases',                                       [\App\Http\Controllers\Api\V1\Purchases\PurchaseController::class, 'store']);
Route::post('family-groups/{id}/purchases/{purchaseId}/confirm',                  [\App\Http\Controllers\Api\V1\Purchases\PurchaseConfirmationController::class, 'confirm']);
Route::post('family-groups/{id}/purchases/{purchaseId}/add-to-stock',             [\App\Http\Controllers\Api\V1\Purchases\PurchaseConfirmationController::class, 'addToStock']);
Route::get('family-groups/{id}/purchases/{purchaseId}/items',                     [\App\Http\Controllers\Api\V1\Purchases\PurchaseItemController::class, 'index']);
Route::post('family-groups/{id}/purchases/{purchaseId}/items',                    [\App\Http\Controllers\Api\V1\Purchases\PurchaseItemController::class, 'store']);
Route::patch('family-groups/{id}/purchases/{purchaseId}/items/{itemId}',          [\App\Http\Controllers\Api\V1\Purchases\PurchaseItemController::class, 'update']);
Route::delete('family-groups/{id}/purchases/{purchaseId}/items/{itemId}',         [\App\Http\Controllers\Api\V1\Purchases\PurchaseItemController::class, 'destroy']);
Route::get('family-groups/{id}/purchases/{purchaseId}',                           [\App\Http\Controllers\Api\V1\Purchases\PurchaseController::class, 'show']);
Route::patch('family-groups/{id}/purchases/{purchaseId}',                         [\App\Http\Controllers\Api\V1\Purchases\PurchaseController::class, 'update']);
Route::delete('family-groups/{id}/purchases/{purchaseId}',                        [\App\Http\Controllers\Api\V1\Purchases\PurchaseController::class, 'destroy']);

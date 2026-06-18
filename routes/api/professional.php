<?php

use Illuminate\Support\Facades\Route;

// Professional links (patient perspective)
Route::prefix('professional-links')->group(function () {
    Route::get('',        [\App\Http\Controllers\Api\V1\Professional\ProfessionalLinkController::class, 'index']);
    Route::post('',       [\App\Http\Controllers\Api\V1\Professional\ProfessionalLinkController::class, 'store']);
    Route::patch('{id}',  [\App\Http\Controllers\Api\V1\Professional\ProfessionalLinkController::class, 'update']);
    Route::delete('{id}', [\App\Http\Controllers\Api\V1\Professional\ProfessionalLinkController::class, 'destroy']);
});

// Professional panel (dietologist perspective)
Route::prefix('professional')->middleware('permission:professional.users.read')->group(function () {
    Route::get('linked-users',                         [\App\Http\Controllers\Api\V1\Professional\ProfessionalPanelController::class, 'linkedUsers']);
    Route::get('users/{userId}/profile',               [\App\Http\Controllers\Api\V1\Professional\ProfessionalPanelController::class, 'userProfile']);
    Route::get('users/{userId}/meal-plans',            [\App\Http\Controllers\Api\V1\Professional\ProfessionalPanelController::class, 'mealPlans']);
    Route::patch('users/{userId}/meal-plans/{planId}', [\App\Http\Controllers\Api\V1\Professional\ProfessionalPanelController::class, 'updateMealPlan'])
        ->middleware('permission:professional.meal_plans.write');
});

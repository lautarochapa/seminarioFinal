<?php

use Illuminate\Support\Facades\Route;

// Notifications
Route::get('notifications/unread-count',      [\App\Http\Controllers\Api\V1\Notifications\NotificationController::class, 'unreadCount']);
Route::patch('notifications/read-all',        [\App\Http\Controllers\Api\V1\Notifications\NotificationController::class, 'readAll']);
Route::get('notifications',                   [\App\Http\Controllers\Api\V1\Notifications\NotificationController::class, 'index']);
Route::patch('notifications/{id}/read',       [\App\Http\Controllers\Api\V1\Notifications\NotificationController::class, 'markAsRead']);
Route::get('users/me/notification-preferences',   [\App\Http\Controllers\Api\V1\Notifications\NotificationController::class, 'getPreferences']);
Route::patch('users/me/notification-preferences', [\App\Http\Controllers\Api\V1\Notifications\NotificationController::class, 'updatePreferences']);

// Consents
Route::get('auth/consents',  [\App\Http\Controllers\Api\V1\Consents\UserConsentController::class, 'show']);
Route::patch('auth/consents', [\App\Http\Controllers\Api\V1\Consents\UserConsentController::class, 'update']);
Route::get('users/me/consents',   [\App\Http\Controllers\Api\V1\Consents\UserConsentController::class, 'show']);
Route::patch('users/me/consents', [\App\Http\Controllers\Api\V1\Consents\UserConsentController::class, 'update']);

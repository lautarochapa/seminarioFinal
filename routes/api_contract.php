<?php

use Illuminate\Support\Facades\Route;

Route::prefix('auth')->middleware(['trace_id'])->group(function () {
    Route::post('register', [\App\Http\Controllers\Api\V1\Auth\AuthController::class, 'register'])
        ->middleware('throttle:60,1');
    Route::post('login', [\App\Http\Controllers\Api\V1\Auth\AuthController::class, 'login'])
        ->middleware('throttle:10,1');
    Route::post('google', [\App\Http\Controllers\Api\V1\Auth\GoogleAuthController::class, 'authenticate'])
        ->middleware('throttle:10,1');
    Route::post('forgot-password', [\App\Http\Controllers\Api\V1\Auth\PasswordController::class, 'forgotPassword'])
        ->middleware('throttle:5,1');
    Route::post('reset-password', [\App\Http\Controllers\Api\V1\Auth\PasswordController::class, 'resetPassword'])
        ->middleware('throttle:60,1');

    Route::middleware(['api_token', 'auth'])->group(function () {
        Route::post('logout', [\App\Http\Controllers\Api\V1\Auth\AuthController::class, 'logout']);
        Route::get('me', [\App\Http\Controllers\Api\V1\Auth\AuthController::class, 'me']);
        Route::patch('me', [\App\Http\Controllers\Api\V1\Auth\AuthController::class, 'updateProfile']);
    });
});

Route::prefix('admin')->middleware(['trace_id', 'api_token', 'auth'])->group(function () {
    Route::get('users', [\App\Http\Controllers\Api\V1\Admin\UserAdminController::class, 'index'])
        ->middleware('permission:security.users.read');
    Route::get('users/{id}/audit', [\App\Http\Controllers\Api\V1\Admin\UserAdminController::class, 'audit'])
        ->middleware('permission:audit.read');
    Route::get('users/{id}', [\App\Http\Controllers\Api\V1\Admin\UserAdminController::class, 'show'])
        ->middleware('permission:security.users.read');
    Route::post('users', [\App\Http\Controllers\Api\V1\Admin\UserAdminController::class, 'store'])
        ->middleware('permission:security.users.write');
    Route::patch('users/{id}/restore', [\App\Http\Controllers\Api\V1\Admin\UserAdminController::class, 'restore'])
        ->middleware('permission:security.users.write');
    Route::patch('users/{id}', [\App\Http\Controllers\Api\V1\Admin\UserAdminController::class, 'update'])
        ->middleware('permission:security.users.write');
    Route::delete('users/{id}', [\App\Http\Controllers\Api\V1\Admin\UserAdminController::class, 'destroy'])
        ->middleware('permission:security.users.write');

    Route::post('users/{userId}/roles', [\App\Http\Controllers\Api\V1\Admin\UserRoleController::class, 'store'])
        ->middleware('permission:security.users.write');
    Route::delete('users/{userId}/roles/{roleId}', [\App\Http\Controllers\Api\V1\Admin\UserRoleController::class, 'destroy'])
        ->middleware('permission:security.users.write');

    Route::get('roles', [\App\Http\Controllers\Api\V1\Admin\RoleAdminController::class, 'index'])
        ->middleware('permission:security.roles.read');
    Route::get('roles/{id}/audit', [\App\Http\Controllers\Api\V1\Admin\RoleAdminController::class, 'audit'])
        ->middleware('permission:audit.read');
    Route::get('roles/{id}', [\App\Http\Controllers\Api\V1\Admin\RoleAdminController::class, 'show'])
        ->middleware('permission:security.roles.read');
    Route::post('roles', [\App\Http\Controllers\Api\V1\Admin\RoleAdminController::class, 'store'])
        ->middleware('permission:security.roles.write');
    Route::patch('roles/{id}/restore', [\App\Http\Controllers\Api\V1\Admin\RoleAdminController::class, 'restore'])
        ->middleware('permission:security.roles.write');
    Route::patch('roles/{id}', [\App\Http\Controllers\Api\V1\Admin\RoleAdminController::class, 'update'])
        ->middleware('permission:security.roles.write');
    Route::delete('roles/{id}', [\App\Http\Controllers\Api\V1\Admin\RoleAdminController::class, 'destroy'])
        ->middleware('permission:security.roles.write');

    Route::post('roles/{roleId}/permissions', [\App\Http\Controllers\Api\V1\Admin\RolePermissionController::class, 'store'])
        ->middleware('permission:security.roles.write');
    Route::delete('roles/{roleId}/permissions/{permissionId}', [\App\Http\Controllers\Api\V1\Admin\RolePermissionController::class, 'destroy'])
        ->middleware('permission:security.roles.write');

    Route::get('permissions', [\App\Http\Controllers\Api\V1\Admin\PermissionAdminController::class, 'index'])
        ->middleware('permission:security.permissions.read');

    Route::get('audit-logs', [\App\Http\Controllers\Api\V1\Admin\AuditLogController::class, 'index'])
        ->middleware('permission:audit.read');
    Route::get('login-logs', [\App\Http\Controllers\Api\V1\Admin\LoginLogController::class, 'index'])
        ->middleware('permission:audit.read');
    Route::get('{resource}/{id}/audit', [\App\Http\Controllers\Api\V1\Admin\AuditLogController::class, 'forResource'])
        ->middleware('permission:audit.read');
});

Route::prefix('family-groups')->middleware(['trace_id', 'api_token', 'auth'])->group(function () {
    Route::post('invitations/{invitationId}/accept', [\App\Http\Controllers\Api\V1\FamilyGroup\FamilyGroupInvitationController::class, 'accept']);

    Route::get('', [\App\Http\Controllers\Api\V1\FamilyGroup\FamilyGroupController::class, 'index']);
    Route::post('', [\App\Http\Controllers\Api\V1\FamilyGroup\FamilyGroupController::class, 'store']);
    Route::get('{id}', [\App\Http\Controllers\Api\V1\FamilyGroup\FamilyGroupController::class, 'show']);
    Route::patch('{id}', [\App\Http\Controllers\Api\V1\FamilyGroup\FamilyGroupController::class, 'update']);
    Route::delete('{id}', [\App\Http\Controllers\Api\V1\FamilyGroup\FamilyGroupController::class, 'destroy']);

    Route::get('{id}/members', [\App\Http\Controllers\Api\V1\FamilyGroup\FamilyGroupMemberController::class, 'index']);
    Route::post('{id}/members', [\App\Http\Controllers\Api\V1\FamilyGroup\FamilyGroupMemberController::class, 'store']);
    Route::patch('{id}/members/{memberId}', [\App\Http\Controllers\Api\V1\FamilyGroup\FamilyGroupMemberController::class, 'update']);
    Route::delete('{id}/members/{memberId}', [\App\Http\Controllers\Api\V1\FamilyGroup\FamilyGroupMemberController::class, 'destroy']);

    Route::post('{id}/invitations', [\App\Http\Controllers\Api\V1\FamilyGroup\FamilyGroupInvitationController::class, 'store']);

    Route::get('{id}/preferences', [\App\Http\Controllers\Api\V1\FamilyGroup\FamilyGroupPreferenceController::class, 'show']);
    Route::patch('{id}/preferences', [\App\Http\Controllers\Api\V1\FamilyGroup\FamilyGroupPreferenceController::class, 'update']);
});

Route::prefix('users/me')->middleware(['trace_id', 'api_token', 'auth'])->group(function () {
    Route::get('profile', [\App\Http\Controllers\Api\V1\UserProfile\UserProfileController::class, 'show']);
    Route::patch('profile', [\App\Http\Controllers\Api\V1\UserProfile\UserProfileController::class, 'update']);
    Route::get('priority-settings', [\App\Http\Controllers\Api\V1\UserProfile\UserPrioritySettingController::class, 'show']);
    Route::patch('priority-settings', [\App\Http\Controllers\Api\V1\UserProfile\UserPrioritySettingController::class, 'update']);

    Route::get('body-measurements', [\App\Http\Controllers\Api\V1\BodyMeasurement\BodyMeasurementController::class, 'index']);
    Route::post('body-measurements', [\App\Http\Controllers\Api\V1\BodyMeasurement\BodyMeasurementController::class, 'store']);
    Route::patch('body-measurements/{id}', [\App\Http\Controllers\Api\V1\BodyMeasurement\BodyMeasurementController::class, 'update']);
    Route::delete('body-measurements/{id}', [\App\Http\Controllers\Api\V1\BodyMeasurement\BodyMeasurementController::class, 'destroy']);
});

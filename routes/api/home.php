<?php

use Illuminate\Support\Facades\Route;

Route::get('users/me/home-summary', [\App\Http\Controllers\Api\V1\UserHome\UserHomeController::class, 'show']);

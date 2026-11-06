<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\RegistrationController;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    Route::post('/auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:5,1');

    Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword'])
        ->middleware('throttle:3,1');

    Route::post('/auth/reset-password', [AuthController::class, 'resetPassword'])
        ->middleware('throttle:5,1');

    Route::middleware([
        'auth:sanctum',
        EnsureUserIsActive::class,
    ])->group(function () {

        Route::post('/auth/register', [RegistrationController::class, 'store']);

        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::patch(
            '/auth/me',
            [AuthController::class, 'updateProfile']
        );

        Route::post(
            '/auth/change-password',
            [AuthController::class, 'changePassword']
        );

        Route::post('/auth/logout', [AuthController::class, 'logout']);

        Route::get(
            '/auth/sessions',
            [AuthController::class, 'sessions']
        );

        Route::delete(
            '/auth/sessions/{id}',
            [AuthController::class, 'revokeSession']
        )->whereNumber('id');

        Route::get('/roles', [
            \App\Http\Controllers\Api\RoleController::class,
            'index',
        ]);

        Route::get('/users', [UserController::class, 'index']);
        Route::get('/users/{user}', [UserController::class, 'show']);
        Route::patch('/users/{user}', [UserController::class, 'update']);
        Route::patch('/users/{user}/status', [UserController::class, 'changeStatus']);
        Route::put('/users/{user}/roles', [UserController::class, 'assignRoles']);

    });

});

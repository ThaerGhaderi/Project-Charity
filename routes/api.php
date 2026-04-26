<?php

use App\Http\Controllers\CompleteProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;

Route::prefix('auth')->group(function () {

    // PUBLIC ROUTES
    
    Route::post('/register', [UserController::class, 'register']);
    Route::post('/verify-otp', [UserController::class, 'verifyOtp']);
    Route::post('/login', [UserController::class, 'login']);
Route::post('/forgot-password', [UserController::class, 'forgotPassword']);
 Route::post('/reset-password', [UserController::class, 'resetPassword']);
    

    // PROTECTED ROUTES
    Route::middleware('auth:sanctum')->group(function () {
Route::post('/change-password', [UserController::class, 'changePassword']);
    Route::post('/complete-profile', [CompleteProfileController::class, 'completeProfile']);
        Route::post('/logout', [UserController::class, 'logout']);
    });

});
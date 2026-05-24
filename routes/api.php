<?php

use App\Http\Controllers\BeneficiaryProfileController;
use App\Http\Controllers\Api\DonorProfileController;
use App\Http\Controllers\Api\VolunteerProfileController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CityController;
use App\Http\Controllers\DayController;
use App\Http\Controllers\DomainController;
use App\Http\Controllers\DonorProfileController as ControllersDonorProfileController;
use App\Http\Controllers\SkillController;
use App\Http\Controllers\TypeController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VolunterProfileController as ControllersVolunterProfileController;

Route::prefix('auth')->group(function () {

    // PUBLIC ROUTES
    Route::post('/register', [UserController::class, 'register']);

    Route::post('/login', [UserController::class, 'login']);
    Route::post('/forgot-password', [UserController::class, 'forgotPassword']);
    Route::post('/reset-password', [UserController::class, 'resetPassword']);
    Route::post('/resend-otp', [UserController::class, 'resendOtp']);

    // PROTECTED ROUTES
    Route::middleware('auth:sanctum')->group(function () {
         Route::post('/verify-otp', [UserController::class, 'verifyOtp']);
        Route::post('/select-role', [UserController::class, 'selectRole']);
        Route::put('/change-password', [UserController::class, 'changePassword']);
        Route::post('/logout', [UserController::class, 'logout']);

    });

});
  Route::middleware('auth:sanctum')->group(function () {
 Route::prefix('beneficiary')->group(function () {
        Route::post('/complete-profile', [BeneficiaryProfileController::class, 'completeProfile']);
        Route::get('/get-profile', [BeneficiaryProfileController::class, 'getProfile']);
        Route::get('/cities', [CityController::class, 'index']);
         Route::get('/TypeNeeds', [TypeController::class, 'index']);
 });
 Route::prefix('donor')->group(function () {
        Route::post('/complete-profile', [ControllersDonorProfileController::class, 'completeProfile']);
        Route::get('/get-profile', [ControllersDonorProfileController::class, 'getProfile']);
        Route::get('/cities', [CityController::class, 'index']);
 });
 Route::prefix('volunteer')->group(function () {
        Route::post('/complete-profile', [ControllersVolunterProfileController::class, 'completeProfile']);
        Route::get('/get-profile', [ControllersVolunterProfileController::class, 'getProfile']);
        Route::get('/categories', [CategoryController::class, 'index']);
        Route::get('/cities', [CityController::class, 'index']);
        Route::get('/domains', [DomainController::class, 'index']);
        Route::get('/days',[DayController::class,'index']);
        Route::get('/skills', [SkillController::class, 'index']);
 });
  });

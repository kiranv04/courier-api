<?php

use App\Http\Controllers\Api\BranchController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\StateController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;


Route::post('/login', [AuthController::class, 'login']);
Route::middleware('auth:sanctum')->group(function () {
   Route::get('/me', [AuthController::class, 'me']);
//    Route::post('/logout', [AuthController::class, 'logout']);
   Route::post('/change-password', [AuthController::class, 'changePassword']);
   Route::apiResource('locations', LocationController::class);
   Route::post('/locations/{id}/activate', [LocationController::class, 'actrivate']);
   Route::get('/states', [StateController::class, 'index']);
});

Route::middleware('auth:sanctum', 'role:super-admin|admin')->group(function () {
   Route::apiResource('branches', BranchController::class);
   Route::post('/branches/{id}/activate', [BranchController::class, 'activate']);
   Route::apiResource('users', UserController::class);
   Route::post('/users/{id}/activate', [UserController::class, 'activate']);
   Route::post('/users/{user}/reset-password', [UserController::class, 'resetPassword']);
});
<?php

use App\Http\Controllers\Api\BranchController;
use App\Http\Controllers\Api\CftController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\ShipmentController;
use App\Http\Controllers\Api\StateController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\WarehouseController;
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;


Route::post('/login', [AuthController::class, 'login']);
Route::middleware('auth:sanctum')->group(function () {
   Route::get('/me', [AuthController::class, 'me']);
   Route::post('/logout', [AuthController::class, 'logout']);
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
   Route::apiResource('warehouses', WarehouseController::class);
   Route::post('/warehouses/{id}/activate', [WarehouseController::class, 'activate']);
   Route::apiResource('cfts', CftController::class);
   Route::post('/cfts/{id}/activate', [CftController::class, 'activate']);
});

Route::middleware('auth:sanctum', 'role:super-admin|admin|branch-admin|branch-employee')->group(function () {
   Route::apiResource('customers', CustomerController::class);
   Route::post('/customers/{id}/activate', [CustomerController::class, 'activate']);
   Route::apiResource('users', UserController::class);
   Route::get('/users/branch/{branchId}', [UserController::class, 'branchUsers']);
   Route::apiResource('shipments', ShipmentController::class)->only(['index', 'store', 'show']);
   Route::patch('/shipments/{shipment}/status', [ShipmentController::class, 'updateStatus']);
   Route::apiResource('cfts', CftController::class);
});

Route::middleware('auth:sanctum', 'role:warehouse-admin')->group(function () {
   Route::apiResource('warehouses', WarehouseController::class);
   Route::get('/users/warehouse/{warehouseId}', [UserController::class, 'warehouseUsers']);
   Route::apiResource('users', UserController::class);
});

// Public tracking endpoint - no authentication required
Route::get('/track/{awb}', function (string $awb) {
    $shipment = \App\Models\Shipment::where('awb_number', $awb)
        ->with(['events.entity'])
        ->firstOrFail();
    return (new \App\Http\Controllers\Api\ShipmentController)->tracking($shipment);
});
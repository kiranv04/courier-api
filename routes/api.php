<?php

use App\Http\Controllers\Api\BranchController;
use App\Http\Controllers\Api\CftController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\PrintConfigController;
use App\Http\Controllers\Api\ShipmentController;
use App\Http\Controllers\Api\ShipmentPdfController;
use App\Http\Controllers\Api\StateController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\WarehouseController;
use App\Http\Controllers\AuthController;
use App\Models\Shipment;
use Illuminate\Support\Facades\Route;


Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {

   // ────────────────────────────────────────────────
   // Super-admin + admin only – full power
   // ────────────────────────────────────────────────
   Route::middleware('role:super-admin|admin')->group(function () {

      Route::apiResource('users', UserController::class);
      Route::post('/users/{id}/activate', [UserController::class, 'activate']);
      Route::post('/users/{user}/reset-password', [UserController::class, 'resetPassword']);
      
      Route::apiResource('branches', BranchController::class);
      Route::post('/branches/{id}/activate', [BranchController::class, 'activate']);
      
      Route::apiResource('warehouses', WarehouseController::class);
      Route::post('/warehouses/{id}/activate', [WarehouseController::class, 'activate']);
      
      Route::apiResource('cfts', CftController::class);
      Route::post('/cfts/{id}/activate', [CftController::class, 'activate']);
   });


   // ────────────────────────────────────────────────
   // Branch scoped operations (list / create / etc.)
   // ────────────────────────────────────────────────
   Route::middleware('role:super-admin|admin|branch-admin|branch-employee')->group(function () {

      Route::get('/users/branch/{branchId}', [UserController::class, 'branchUsers']);

      Route::get('/cfts', [CftController::class, 'index']);

      Route::get('/branches/{branch}', [BranchController::class, 'show']);
      
      Route::apiResource('customers', CustomerController::class);
      Route::post('/customers/{id}/activate', [CustomerController::class, 'activate']);
      Route::get('/customers/{customer}/print-config', [PrintConfigController::class, 'getForCustomer']);
      Route::post('/customers/{customer}/print-config', [PrintConfigController::class, 'saveForCustomer']);
      Route::delete('/customers/{customer}/print-config', [PrintConfigController::class, 'resetForCustomer']);

      Route::apiResource('shipments', ShipmentController::class)->only(['index', 'store', 'show']);
      Route::patch('/shipments/{shipment}/status', [ShipmentController::class, 'updateStatus']);
      Route::post('/shipments/{shipment}/print-override', [PrintConfigController::class, 'saveOverride']); // Shipment print override
      Route::get('/shipments/{shipment}/print-config', [PrintConfigController::class, 'getEffectiveForShipment']); // Effective config for a shipment (used by print modal)
      Route::get('/shipments/{shipment}/pdf', [ShipmentPdfController::class, 'generate']); // PDF generation
   });


   // ────────────────────────────────────────────────
   // Warehouse scoped (very limited)
   // ────────────────────────────────────────────────
   Route::middleware('role:warehouse-admin')->group(function () {

      Route::get('/users/warehouse/{warehouseId}', [UserController::class, 'warehouseUsers']);
   });

   //Other shared routes
   Route::get('/me', [AuthController::class, 'me']);
   Route::post('/logout', [AuthController::class, 'logout']);
   Route::post('/change-password', [AuthController::class, 'changePassword']);

   Route::apiResource('locations', LocationController::class);
   Route::post('/locations/{id}/activate', [LocationController::class, 'actrivate']);
   
   Route::get('/states', [StateController::class, 'index']);
});

// Public tracking endpoint - no authentication required
Route::get('/track/{awb}', function (string $awb) {
    $shipment = Shipment::where('awb_number', $awb)
        ->with(['events.entity'])
        ->firstOrFail();
    return (new ShipmentController)->tracking($shipment);
});

// Route::get('/debug-my-roles', function () {
//     $user = auth('sanctum')->user();

//     if (!$user) {
//         return response()->json(['error' => 'Not authenticated'], 401);
//     }

//     return response()->json([
//         'user_id'     => $user->id,
//         'email'       => $user->email,
//         'roles'       => $user->getRoleNames(),
//         'permissions' => $user->getAllPermissions()->pluck('name'),
//         'has_super'   => $user->hasRole('super-admin'),
//     ]);
// });
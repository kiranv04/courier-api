<?php

use App\Http\Controllers\Api\BranchController;
use App\Http\Controllers\Api\CftController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DeliveryController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\InvoicePdfController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\ManifestController;
use App\Http\Controllers\Api\PincodeController;
use App\Http\Controllers\Api\PrintConfigController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\ShipmentController;
use App\Http\Controllers\Api\ShipmentPaymentController;
use App\Http\Controllers\Api\ShipmentPdfController;
use App\Http\Controllers\Api\ShipmentStatusController;
use App\Http\Controllers\Api\StateController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\WarehouseController;
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;


Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
Route::post('/driver/login', [AuthController::class, 'driverLogin'])->middleware('throttle:10,1');

Route::middleware(['auth:sanctum', 'password.changed'])->group(function () {

   // ────────────────────────────────────────────────
   // Super-admin + admin only – full power
   // ────────────────────────────────────────────────
   Route::middleware('role:super-admin|admin')->group(function () {

      Route::apiResource('branches', BranchController::class);
      Route::post('/branches/{id}/activate', [BranchController::class, 'activate']);
      
      Route::apiResource('warehouses', WarehouseController::class);
      Route::post('/warehouses/{id}/activate', [WarehouseController::class, 'activate']);
      
      Route::apiResource('cfts', CftController::class)->except('show');
      Route::post('/cfts/{id}/activate', [CftController::class, 'activate']);

      Route::apiResource('locations', LocationController::class);
      Route::post('/locations/{id}/activate', [LocationController::class, 'activate']);

      Route::get('/dashboard/superadmin', [DashboardController::class, 'superadmin']);
   });

   // ────────────────────────────────────────────────
   // Super-admin only – company & branch settings
   // ────────────────────────────────────────────────
   Route::middleware('role:super-admin')->group(function () {
 
      Route::get('/settings/company', [SettingsController::class, 'showCompany']);
      Route::put('/settings/company', [SettingsController::class, 'updateCompany']);
      Route::post('/settings/company/logo', [SettingsController::class, 'uploadLogo']);
      Route::post('/settings/company/bank-qr', [SettingsController::class, 'uploadBankQr']);
      Route::delete('/settings/company/bank-qr', [SettingsController::class, 'deleteBankQr']);

      Route::get('/settings/branches/{branch}', [SettingsController::class, 'showBranch']);
      Route::put('/settings/branches/{branch}', [SettingsController::class, 'updateBranch']);
   });

   // ────────────────────────────────────────────────
   // Branch scoped operations (list / create / etc.)
   // ────────────────────────────────────────────────
   Route::middleware('role:super-admin|admin|branch-admin|branch-employee|warehouse-admin|warehouse-employee')->group(function () {

      Route::get('/cfts', [CftController::class, 'index']);

      Route::get('/branches/{branch}', [BranchController::class, 'show']);
      
      Route::get('/shipments/destinations', [ShipmentStatusController::class, 'destinations']);
      Route::get('/shipments/delivery-agents', [ShipmentStatusController::class, 'deliveryAgents']);
      Route::get('/shipments/{shipment}/transitions', [ShipmentStatusController::class, 'nextTransitions']);
      Route::patch('/shipments/{shipment}/statusUpdate', [ShipmentStatusController::class, 'update']);

      Route::apiResource('shipments', ShipmentController::class)->only(['index', 'store', 'update']);
      Route::get('/shipments/{shipment}', [ShipmentController::class, 'show']);
      Route::post('/shipments/{shipment}/print-override', [PrintConfigController::class, 'saveOverride']); // Shipment print override
      Route::get('/shipments/{shipment}/print-config', [PrintConfigController::class, 'getEffectiveForShipment']); // Effective config for a shipment (used by print modal)
      Route::get('/shipments/{shipment}/pdf', [ShipmentPdfController::class, 'generate']); // PDF generation

      Route::get('/shipments/{shipment}/payment', [ShipmentPaymentController::class, 'show']);
      Route::post('/shipments/{shipment}/payment', [ShipmentPaymentController::class, 'store']);
      
      Route::get('/delivery/cod-summary', [DeliveryController::class, 'codSummary']);

      Route::get('/manifests/eligible-shipments', [ManifestController::class, 'eligibleShipments']);
      Route::post('/manifests/{manifest}/close', [ManifestController::class, 'close']);
      Route::apiResource('manifests', ManifestController::class)->only(['index', 'show', 'store']);
   });


   // ────────────────────────────────────────────────
   // Warehouse scoped (very limited)
   // ────────────────────────────────────────────────
   // Customers, invoices and reports are head-office / branch data. Warehouse roles have no
   // screens for them and their owner_id is a warehouse id, so they must not reach these.
   Route::middleware('role:super-admin|admin|branch-admin|branch-employee')->group(function () {

      Route::apiResource('customers', CustomerController::class);
      Route::post('/customers/{id}/activate', [CustomerController::class, 'activate']);
      Route::get('/customers/{customer}/documents/{type}', [CustomerController::class, 'document']);
      Route::get('/customers/{customer}/print-config', [PrintConfigController::class, 'getForCustomer']);
      Route::post('/customers/{customer}/print-config', [PrintConfigController::class, 'saveForCustomer']);
      Route::delete('/customers/{customer}/print-config', [PrintConfigController::class, 'resetForCustomer']);

      Route::get('/invoices', [InvoiceController::class, 'index']);
      Route::get('/invoices/preview-corporate', [InvoiceController::class, 'previewCorporate']);
      Route::get('/invoices/{invoice}', [InvoiceController::class, 'show']);
      Route::post('/invoices/corporate', [InvoiceController::class, 'createCorporate']);
      Route::get('/invoices/{invoice}/pdf', [InvoicePdfController::class, 'generate']);

      Route::get('/reports/shipments', [ReportController::class, 'shipments']);
      Route::get('/reports/shipments/pdf', [ReportController::class, 'shipmentsPdf']);
      Route::get('/reports/invoices', [ReportController::class, 'invoices']);
      Route::get('/reports/invoices/pdf', [ReportController::class, 'invoicesPdf']);
      Route::get('/reports/shipments/detail', [ReportController::class, 'shipmentsDetail']);
      Route::get('/reports/invoices/detail', [ReportController::class, 'invoicesDetail']);
   });

   // User management: head office manages everyone; branch/warehouse admins are
   // restricted to their own branch/warehouse staff inside UserController.
   Route::middleware('role:super-admin|admin|branch-admin|warehouse-admin')->group(function () {
      Route::apiResource('users', UserController::class);
      Route::post('/users/{id}/activate', [UserController::class, 'activate']);
      Route::post('/users/{user}/reset-password', [UserController::class, 'resetPassword']);
      Route::get('/users/branch/{branchId}', [UserController::class, 'branchUsers']);
      Route::get('/users/warehouse/{warehouseId}', [UserController::class, 'warehouseUsers']);
   });

   Route::middleware('role:warehouse-admin|warehouse-employee')->group(function () {
      Route::get('/dashboard/warehouse', [DashboardController::class, 'warehouse']);
   });

   Route::middleware('role:branch-admin|branch-employee')->group(function () {
      Route::get('/dashboard/branch', [DashboardController::class, 'branch']);
   });

   Route::middleware('role:branch-delivery')->group(function () {
      Route::get('/delivery/shipments', [DeliveryController::class, 'shipments']);
      Route::post('/delivery/shipments/{shipment}/deliver', [DeliveryController::class, 'deliver']);
      Route::post('/delivery/shipments/{shipment}/fail', [DeliveryController::class, 'fail']);
   });

   //Other shared routes
   Route::get('/me', [AuthController::class, 'me']);
   Route::post('/logout', [AuthController::class, 'logout']);
   Route::post('/change-password', [AuthController::class, 'changePassword'])->middleware('throttle:10,1');

   Route::get('/states', [StateController::class, 'index']);
});

// Public tracking endpoint - no authentication required
Route::get('/track/{awb}', [ShipmentController::class, 'track'])->middleware('throttle:30,1');

Route::get('/pincodes/check', [PincodeController::class, 'check'])->middleware('throttle:60,1');
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerPrintConfig;
use App\Models\ShipmentPrintOverride;
use App\Models\Shipment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PrintConfigController extends Controller
{
    // The default config — single source of truth
    public static function defaults(): array
    {
        return [
            'show_shipper_details'      => true,
            'show_consignee_details'    => true,
            'show_shipper_gst'          => true,
            'show_consignee_gst'        => true,
            'show_parcel_dimensions'    => true,
            'show_invoice_details'      => true,
            'show_eway_bill'            => true,
            'show_charges_breakdown'    => true,
            'show_grand_total_only'     => false,
            'show_special_instructions' => true,
        ];
    }

    // Get config for a customer — returns existing or defaults
    public function getForCustomer(Customer $customer): JsonResponse
    {
        $config = $customer->printConfig ?? self::defaults();

        return response()->json([
            'data'       => $config,
            'is_default' => !$customer->printConfig,
        ]);
    }

    // Create or update a customer's print config
    public function saveForCustomer(Request $request, Customer $customer): JsonResponse
    {
        $data = $this->validateConfig($request);

        $config = CustomerPrintConfig::updateOrCreate(
            ['customer_id' => $customer->id],
            array_merge($data, [
                'created_by' => $customer->printConfig ? $customer->printConfig->created_by : auth()->id(),
                'updated_by' => auth()->id(),
            ])
        );

        return response()->json([
            'message' => 'Print config saved!',
            'data'    => $config,
        ]);
    }

    // Reset a customer's config back to defaults
    public function resetForCustomer(Customer $customer): JsonResponse
    {
        $customer->printConfig?->delete();

        return response()->json([
            'message' => 'Print config reset to defaults',
            'data'    => self::defaults(),
        ]);
    }

    // Save a per-shipment override
    public function saveOverride(Request $request, Shipment $shipment): JsonResponse
    {
        $data = $this->validateConfig($request);

        $override = ShipmentPrintOverride::updateOrCreate(
            ['shipment_id' => $shipment->id],
            array_merge($data, ['created_by' => auth()->id()])
        );

        return response()->json([
            'message' => 'Print override saved!',
            'data'    => $override,
        ]);
    }

    // Resolve the effective config for a shipment — used by PDF endpoint later
    public static function resolveForShipment(Shipment $shipment): array
    {
        // Priority 1: shipment override
        if ($shipment->printOverride) {
            return $shipment->printOverride->toArray();
        }

        // Priority 2: customer config (corporate only)
        if ($shipment->customer_type === 'corporate' && $shipment->customer?->printConfig) {
            return $shipment->customer->printConfig->toArray();
        }

        // Priority 3: defaults
        return self::defaults();
    }

    private function validateConfig(Request $request): array
    {
        return $request->validate([
            'show_shipper_details'      => 'required|boolean',
            'show_consignee_details'    => 'required|boolean',
            'show_shipper_gst'          => 'required|boolean',
            'show_consignee_gst'        => 'required|boolean',
            'show_parcel_dimensions'    => 'required|boolean',
            'show_invoice_details'      => 'required|boolean',
            'show_eway_bill'            => 'required|boolean',
            'show_charges_breakdown'    => 'required|boolean',
            'show_grand_total_only'     => 'required|boolean',
            'show_special_instructions' => 'required|boolean',
        ]);
    }

    public function getEffectiveForShipment(Shipment $shipment): JsonResponse
    {
        $config = self::resolveForShipment($shipment);

        // Determine source for the frontend badge
        if ($shipment->printOverride) {
            $source = 'override';
        } elseif ($shipment->customer_type === 'corporate' && $shipment->customer?->printConfig) {
            $source = 'customer';
        } else {
            $source = 'default';
        }

        return response()->json([
            'data'   => $config,
            'source' => $source,
        ]);
    }
}
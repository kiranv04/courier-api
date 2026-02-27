<?php

namespace App\Http\Controllers\Api;

use App\Models\Shipment;
use App\Models\ShipmentEvent;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ShipmentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();
        $isBranchAdmin = $user->hasRole('branch-admin');

        $shipments = Shipment::with(['charges', 'latestEvent', 'branch', 'customer'])
            // Branch admins are always scoped to their branch
            ->when($isBranchAdmin, fn($q) => $q->where('branch_id', $user->owner_id))
            // Admin optional branch filter
            ->when(!$isBranchAdmin && $request->branch_id, fn($q) => $q->where('branch_id', $request->branch_id))
            // Date range — only apply if both dates are provided
            ->when($request->date_from && $request->date_to, fn($q) => $q
                ->whereDate('created_at', '>=', $request->date_from)
                ->whereDate('created_at', '<=', $request->date_to)
            )
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json(['data' => $shipments]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'status'                  => 'required|in:draft,booked',
            'branchId' => [
                auth()->user()->hasRole('super-admin | admin' ) ? 'required' : 'nullable',
                'exists:branches,id'
            ],
            'customer.customerId'     => 'nullable|exists:customers,id',
            'customer.customerType'   => 'required|in:cash,corporate',
            'service.serviceType'     => 'nullable|string',
            'service.service'         => 'required|in:Parcel,Document',
            'service.paymentMode'     => 'nullable|in:Regular,FOD,DOD,COD',
            'service.customerRef'     => 'nullable|string',
            'service.parcelContent'   => 'nullable|string',
            'service.trackingNumber'  => 'nullable|string',
            'specialInstruction'      => 'nullable|string',

            // Shipper
            'shipper.shipperName'           => 'required|string',
            'shipper.shipperCompany'        => 'nullable|string',
            'shipper.shipperPhone'          => 'nullable|string',
            'shipper.shipperEmail'          => 'nullable|email',
            'shipper.shipperGst'            => 'nullable|string',
            'shipper.shipperAddLine1'       => 'nullable|string',
            'shipper.shipperAddLine2'       => 'nullable|string',
            'shipper.shipperAddCity'        => 'nullable|string',
            'shipper.shipperState'          => 'nullable|string',
            'shipper.shipperPincode'        => 'nullable|string',

            // Consignee
            'consignee.consigneeName'       => 'required|string',
            'consignee.consigneePhone'      => 'nullable|string',
            'consignee.consigneeGst'        => 'nullable|string',
            'consignee.consigneeAddLine1'   => 'nullable|string',
            'consignee.consigneeAddLine2'   => 'nullable|string',
            'consignee.consigneeAddCity'    => 'nullable|string',
            'consignee.consigneePincode'    => 'nullable|string',

            // COD/DOD
            'inFavour'            => 'nullable|string',
            'payableAt'              => 'nullable|string',
            'collectableAmount'      => 'nullable|numeric',

            // Parcels
            'parcels'               => 'required_if:service.service,Parcel|nullable|array',
            'parcels.*.length'      => 'required_if:service.service,Parcel|numeric',
            'parcels.*.width'       => 'required_if:service.service,Parcel|numeric',
            'parcels.*.height'      => 'required_if:service.service,Parcel|numeric',
            'parcels.*.weight'      => 'required_if:service.service,Parcel|numeric',
            'parcels.*.numBoxes'    => 'required_if:service.service,Parcel|integer|min:1',
            // 'parcels.*.volWeight'   => 'required_if:service.service,Parcel|numeric',

            // Invoices - only required for Parcel service
            'invoices'                      => 'required_if:service.service,Parcel|nullable|array',
            'invoices.*.invoiceNumber'      => 'required_if:service.service,Parcel|string',
            'invoices.*.invoiceAmount'      => 'required_if:service.service,Parcel|numeric',
            'invoices.*.ewayBill'           => 'nullable|string',

            // Doc dimensions - only required for Document service
            'docDimensions.length'  => 'required_if:service.service,Document|numeric',
            'docDimensions.width'   => 'required_if:service.service,Document|numeric',
            'docDimensions.height'  => 'required_if:service.service,Document|numeric',
            'docDimensions.weight'  => 'required_if:service.service,Document|numeric',

            // Rates
            'rates'                         => 'nullable|array',
            'rates.cft'                     => 'nullable|integer',
            'rates.chargeableWeight'        => 'nullable|numeric',
            'rates.packageYield'            => 'nullable|numeric',
            'rates.freight'                 => 'nullable|numeric',
            'rates.fuel'                    => 'nullable|numeric',
            'rates.awbFee'                  => 'nullable|numeric',
            'rates.fov'                     => 'nullable|numeric',
            'rates.insurance'               => 'nullable|in:owner,carrier',
            'rates.carrierInsurance'        => 'nullable|numeric',
            'rates.fod'                     => 'nullable|numeric',
            'rates.dod'                     => 'nullable|numeric',
            'rates.oda'                     => 'nullable|numeric',
            'rates.handling'                => 'nullable|numeric',
            'rates.dcc'                     => 'nullable|numeric',
            'rates.pickupcharges'           => 'nullable|numeric',
            'rates.deliverycharges'         => 'nullable|numeric',
            'rates.total'                   => 'nullable|numeric',
            'rates.gst'                     => 'nullable|numeric',
            'rates.grandTotal'              => 'nullable|numeric',
        ]);

        try {
            DB::beginTransaction();

            $s = $data['service'];
            $sh = $data['shipper'];
            $con = $data['consignee'];
            $cust = $data['customer'];
            $dod = $data['dodCodDetails'] ?? [];

            $shipment = Shipment::create([
                'branch_id' => auth()->user()->hasRole('super-admin | admin')
                    ? $data['branchId']
                    : auth()->user()->owner_id,
                'customer_id'           => $cust['customerId'] ?? null,
                'awb_number'            => $data['trackingNumber'] ?? null,
                // 'customer_type'         => $cust['customerType'],
                'status'                => $data['status'],
                'service_type'          => $s['serviceType'] ?? null,
                'service'               => $s['service'],
                'payment_mode'          => $s['paymentMode'] ?? null,
                'customer_reference'    => $s['customerRef'] ?? null,
                'parcel_content'        => $s['parcelContent'] ?? null,
                // 'tracking_number'       => $s['trackingNumber'] ?? null,

                'shipper_name'          => $sh['shipperName'],
                'shipper_company_name'  => $sh['shipperCompany'] ?? null,
                'shipper_phone'         => $sh['shipperPhone'] ?? null,
                'shipper_email'         => $sh['shipperEmail'] ?? null,
                'shipper_gst'           => $sh['shipperGst'] ?? null,
                'shipper_address_line1' => $sh['shipperAddLine1'] ?? null,
                'shipper_address_line2' => $sh['shipperAddLine2'] ?? null,
                'shipper_city'          => $sh['shipperAddCity'] ?? null,
                'shipper_state_id'      => $sh['shipperState'] ?? null,
                'shipper_pincode'       => $sh['shipperPincode'] ?? null,

                'consignee_name'        => $con['consigneeName'],
                'consignee_phone'       => $con['consigneePhone'] ?? null,
                'consignee_gst'         => $con['consigneeGst'] ?? null,
                'consignee_address_line1'         => $con['consigneeAddLine1'] ?? null,
                'consignee_address_line2'         => $con['consigneeAddLine2'] ?? null,
                'consignee_city'        => $con['consigneeAddCity'] ?? null,
                'consignee_pincode'     => $con['consigneePincode'] ?? null,

                'special_instructions'  => $data['specialInstruction'] ?? null,
                'in_favor_of'          => $dod['inFavour'] ?? null,
                'payable_at'            => $dod['payableAt'] ?? null,
                'collectable_amount'    => $dod['collectableAmount'] ?? null,
                'created_by'            => auth()->id(),
                'booked_at'             => $data['status'] === 'booked' ? now() : null,
            ]);

            // Parcels
            if ($s['service'] === 'Parcel' && !empty($data['parcels'])) {
                $shipment->parcels()->createMany(
                    collect($data['parcels'])->map(fn($p) => [
                        'length'     => $p['length'],
                        'width'      => $p['width'],
                        'height'     => $p['height'],
                        'weight'     => $p['weight'],
                        'num_boxes'  => $p['numBoxes'],
                        // 'volumetric_weight' => $p['volWeight'],
                    ])->toArray()
                );
            } elseif ($s['service'] === 'Document' && !empty($data['docDimensions'])) {
                $doc = $data['docDimensions'];
                $shipment->parcels()->create([
                    'length'     => $doc['length'],
                    'width'      => $doc['width'],
                    'height'     => $doc['height'],
                    'weight'     => $doc['weight'],
                    'num_boxes'  => 1,
                    'volumetric_weight' => round(($doc['length'] * $doc['width'] * $doc['height']) / 27000, 2),
                ]);
            }

            // Invoices
            if (!empty($data['invoices'])) {
                $shipment->invoices()->createMany(
                    collect($data['invoices'])->map(fn($i) => [
                        'invoice_number' => $i['invoiceNumber'],
                        'invoice_amount' => $i['invoiceAmount'],
                        'eway_bill'      => $i['ewayBill'] ?? null,
                    ])->toArray()
                );
            }

            // Charges
            if (!empty($data['rates'])) {
                $r = $data['rates'];
                $shipment->charges()->create([
                    'cft'               => $data['rates']['cft'] ?? null,
                    'chargeable_weight' => $data['rates']['chargeableWeight'] ?? null,
                    'package_yield'     => $data['rates']['packageYield'] ?? null,
                    'freight'           => $data['rates']['freight'] ?? 0,
                    'fuel'              => $data['rates']['fuel'] ?? 0,
                    'awb_fee'           => $data['rates']['awbFee'] ?? 0,
                    'fov'               => $data['rates']['fov'] ?? 0,
                    'insurance_type'    => $data['rates']['insurance'] ?? 'owner',
                    'carrier_insurance' => $data['rates']['carrierInsurance'] ?? 0,
                    'fod'               => $data['rates']['fod'] ?? 0,
                    'dod'               => $data['rates']['dod'] ?? 0,
                    'oda'               => $data['rates']['oda'] ?? 0,
                    'handling'          => $data['rates']['handling'] ?? 0,
                    'dcc'               => $data['rates']['dcc'] ?? 0,
                    'pickup_charges'    => $data['rates']['pickupcharges'] ?? 0,
                    'delivery_charges'  => $data['rates']['deliverycharges'] ?? 0,
                    'total'             => $data['rates']['total'] ?? 0,
                    'gst'               => $data['rates']['gst'] ?? 0,
                    'grand_total'       => $data['rates']['grandTotal'] ?? 0,
                ]);
            }

            // Log the creation event
            ShipmentEvent::create([
                'shipment_id' => $shipment->id,
                'event_type'  => $data['status'] === 'booked' ? 'booked' : 'created',
                'entity_id'   => auth()->user()->rel_id,
                'entity_type' => auth()->user()->rel_type,
                'notes'       => 'Shipment ' . $data['status'],
                'created_by'  => auth()->id(),
            ]);

            DB::commit();

            return response()->json([
                'message'  => 'Shipment ' . $data['status'] . ' successfully!',
                'data'     => $shipment->load(['parcels', 'invoices', 'charges', 'events']),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to save shipment', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Shipment $shipment)
    {
        return response()->json([
            'data' => $shipment->load(['parcels', 'invoices', 'charges', 'events.entity', 'events.createdBy', 'assignments.assignedTo'])
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Shipment $shipment): JsonResponse
    {
        if ($shipment->status === 'booked') {
            return response()->json(['message' => 'Booked shipments cannot be edited'], 403);
        }

        // Same validation as store()
        $data = $request->validate([
            'status'                  => 'required|in:draft,booked',
            'customer.customerId'     => 'nullable|exists:customers,id',
            'customer.customerType'   => 'required|in:cash,corporate',
            'service.serviceType'     => 'nullable|string',
            'service.service'         => 'required|in:Parcel,Document',
            'service.paymentMode'     => 'nullable|in:Regular,FOD,DOD,COD',
            'service.customerRef'     => 'nullable|string',
            'service.parcelContent'   => 'nullable|string',
            'service.trackingNumber'  => 'nullable|string',
            'specialInstruction'      => 'nullable|string',

            // Shipper
            'shipper.shipperName'           => 'required|string',
            'shipper.shipperCompany'        => 'nullable|string',
            'shipper.shipperPhone'          => 'nullable|string',
            'shipper.shipperEmail'          => 'nullable|email',
            'shipper.shipperGst'            => 'nullable|string',
            'shipper.shipperAddLine1'       => 'nullable|string',
            'shipper.shipperAddLine2'       => 'nullable|string',
            'shipper.shipperAddCity'        => 'nullable|string',
            'shipper.shipperState'          => 'nullable|string',
            'shipper.shipperPincode'        => 'nullable|string',

            // Consignee
            'consignee.consigneeName'       => 'required|string',
            'consignee.consigneePhone'      => 'nullable|string',
            'consignee.consigneeGst'        => 'nullable|string',
            'consignee.consigneeAddLine1'   => 'nullable|string',
            'consignee.consigneeAddLine2'   => 'nullable|string',
            'consignee.consigneeAddCity'    => 'nullable|string',
            'consignee.consigneePincode'    => 'nullable|string',

            // COD/DOD
            'inFavour'            => 'nullable|string',
            'payableAt'              => 'nullable|string',
            'collectableAmount'      => 'nullable|numeric',

            // Parcels
            'parcels'               => 'required_if:service.service,Parcel|nullable|array',
            'parcels.*.length'      => 'required_if:service.service,Parcel|numeric',
            'parcels.*.width'       => 'required_if:service.service,Parcel|numeric',
            'parcels.*.height'      => 'required_if:service.service,Parcel|numeric',
            'parcels.*.weight'      => 'required_if:service.service,Parcel|numeric',
            'parcels.*.numBoxes'    => 'required_if:service.service,Parcel|integer|min:1',
            'parcels.*.volWeight'   => 'required_if:service.service,Parcel|numeric',

            // Invoices - only required for Parcel service
            'invoices'                      => 'required_if:service.service,Parcel|nullable|array',
            'invoices.*.invoiceNumber'      => 'required_if:service.service,Parcel|string',
            'invoices.*.invoiceAmount'      => 'required_if:service.service,Parcel|numeric',
            'invoices.*.ewayBill'           => 'nullable|string',

            // Doc dimensions - only required for Document service
            'docDimensions.length'  => 'required_if:service.service,Document|numeric',
            'docDimensions.width'   => 'required_if:service.service,Document|numeric',
            'docDimensions.height'  => 'required_if:service.service,Document|numeric',
            'docDimensions.weight'  => 'required_if:service.service,Document|numeric',

            // Rates
            'rates'                         => 'nullable|array',
            'rates.cft'                     => 'nullable|integer',
            'rates.chargeableWeight'        => 'nullable|numeric',
            'rates.packageYield'            => 'nullable|numeric',
            'rates.freight'                 => 'nullable|numeric',
            'rates.fuel'                    => 'nullable|numeric',
            'rates.awbFee'                  => 'nullable|numeric',
            'rates.fov'                     => 'nullable|numeric',
            'rates.insurance'               => 'nullable|in:owner,carrier',
            'rates.carrierInsurance'        => 'nullable|numeric',
            'rates.fod'                     => 'nullable|numeric',
            'rates.dod'                     => 'nullable|numeric',
            'rates.oda'                     => 'nullable|numeric',
            'rates.handling'                => 'nullable|numeric',
            'rates.dcc'                     => 'nullable|numeric',
            'rates.pickupcharges'           => 'nullable|numeric',
            'rates.deliverycharges'         => 'nullable|numeric',
            'rates.total'                   => 'nullable|numeric',
            'rates.gst'                     => 'nullable|numeric',
            'rates.grandTotal'              => 'nullable|numeric',
        ]);

        try {
            DB::beginTransaction();

            $shipment->update([/* same mapping as store */]);

            // Delete and recreate parcels and invoices
            $shipment->parcels()->delete();
            $shipment->invoices()->delete();
            $shipment->charges()->delete();

            // Then recreate exactly as in store()
            // ... parcels, invoices, charges blocks

            if ($data['status'] === 'booked' && $shipment->getOriginal('status') === 'draft') {
                ShipmentEvent::create([
                    'shipment_id' => $shipment->id,
                    'event_type'  => 'booked',
                    'entity_id'   => auth()->user()->rel_id,
                    'entity_type' => auth()->user()->rel_type,
                    'notes'       => 'Shipment booked',
                    'created_by'  => auth()->id(),
                ]);

                $shipment->update(['booked_at' => now()]);
            }

            DB::commit();
            return response()->json([
                'message' => 'Shipment updated successfully!',
                'data'    => $shipment->load(['parcels', 'invoices', 'charges', 'events']),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to update shipment', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Shipment $shipment)
    {
        //
    }

    public function updateStatus(Request $request, Shipment $shipment): JsonResponse
    {
        $data = $request->validate([
            'status' => 'required|in:booked,picked_up,in_transit,at_hub,out_for_delivery,delivered,exception,cancelled',
            'notes'  => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            $shipment->update([
                'status'    => $data['status'],
                'booked_at' => $data['status'] === 'booked' && !$shipment->booked_at ? now() : $shipment->booked_at,
            ]);

            ShipmentEvent::create([
                'shipment_id' => $shipment->id,
                'event_type'  => $data['status'],
                'branch_id'   => auth()->user()->rel_id,
                'notes'       => $data['notes'] ?? null,
                'created_by'  => auth()->id(),
            ]);

            DB::commit();
            return response()->json([
                'message' => 'Status updated!',
                'data'    => $shipment->load('events'),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to update status'], 500);
        }
    }

    public function tracking(Shipment $shipment): JsonResponse
    {
        return response()->json([
            'awb_number' => $shipment->awb_number,
            'status'     => $shipment->status,
            'events'     => $shipment->events()->with(['branch', 'createdBy'])->get(),
        ]);
    }
}

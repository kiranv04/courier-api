<?php

namespace App\Http\Controllers\Api;

use App\Models\Shipment;
use App\Models\ShipmentEvent;
use App\Models\State;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
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
        $this->validateFilters($request, ['status' => 'nullable|in:draft,booked,picked_up,in_transit,at_hub,at_branch,out_for_delivery,delivered,exception,cancelled']);

        $user = auth()->user();

        $shipments = Shipment::with(['charges', 'latestEvent', 'branch', 'customer', 'payment'])
            // Non head-office users only see shipments that touch their location
            ->visibleTo($user)
            ->when($request->branch_id, fn($q) => $q->where('branch_id', $request->branch_id))
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
        $this->denyWarehouseRoles();

        $data = $request->validate([
            'status'                  => 'required|in:draft,booked',
            'branchId' => [
                auth()->user()->hasAnyRole(['super-admin', 'admin']) ? 'required' : 'nullable',
                'exists:branches,id'
            ],
            'customer.customerId'     => ['nullable', 'exists:customers,id', $this->ownCustomerRule()],
            'customer.customerType'   => 'required|in:cash,corporate',
            'service.serviceType'     => 'nullable|in:Surface,Apex,Domestic Priority',
            'service.service'         => 'required|in:Parcel,Document',
            'service.paymentMode'     => 'nullable|in:Regular,FOD,DOD,COD',
            'service.customerRef'     => 'nullable|string|max:255',
            'service.parcelContent'   => 'nullable|string|max:255',
            'service.trackingNumber'  => 'nullable|string|max:255|unique:shipments,awb_number',
            'specialInstruction'      => 'nullable|string|max:255',

            // Shipper
            'shipper.shipperName'           => 'required|string|max:255',
            'shipper.shipperCompany'        => 'nullable|string|max:255',
            'shipper.shipperPhone'          => 'nullable|regex:/^[6-9]\d{9}$/',
            'shipper.shipperEmail'          => 'nullable|email|max:255',
            'shipper.shipperGst'            => 'nullable|regex:/^[0-9]{2}[A-Za-z]{5}[0-9]{4}[A-Za-z][1-9A-Za-z]Z[0-9A-Za-z]$/',
            'shipper.shipperAddLine1'       => 'nullable|string|max:255',
            'shipper.shipperAddLine2'       => 'nullable|string|max:255',
            'shipper.shipperAddCity'        => 'nullable|string|max:255',
            'shipper.shipperState'          => 'nullable|exists:states,id',
            'shipper.shipperPincode'        => 'nullable|digits:6',

            // Consignee
            'consignee.consigneeName'       => 'required|string|max:255',
            'consignee.receiverName'        => 'required|string|max:255',
            'consignee.consigneePhone'      => 'nullable|regex:/^[6-9]\d{9}$/',
            'consignee.consigneeGst'        => 'nullable|regex:/^[0-9]{2}[A-Za-z]{5}[0-9]{4}[A-Za-z][1-9A-Za-z]Z[0-9A-Za-z]$/',
            'consignee.consigneeAddLine1'   => 'nullable|string|max:255',
            'consignee.consigneeAddLine2'   => 'nullable|string|max:255',
            'consignee.consigneeAddCity'    => 'nullable|string|max:255',
            'consignee.consigneePincode'    => 'nullable|digits:6',
            'consignee.consigneeState'      => 'nullable|exists:states,id',

            // COD/DOD
            'dodCodDetails.inFavour'         => 'required_if:service.paymentMode,DOD,COD|nullable|string|max:255',
            'dodCodDetails.payableAt'        => 'required_if:service.paymentMode,DOD,COD|nullable|string|max:255',
            'dodCodDetails.collectableAmount' => 'required_if:service.paymentMode,DOD,COD|nullable|numeric|min:0.01|max:99999999.99',

            // Parcels
            'parcels'               => 'required_if:service.service,Parcel|nullable|array',
            'parcels.*.length'      => 'required_if:service.service,Parcel|numeric|min:0.01|max:99999999.99',
            'parcels.*.width'       => 'required_if:service.service,Parcel|numeric|min:0.01|max:99999999.99',
            'parcels.*.height'      => 'required_if:service.service,Parcel|numeric|min:0.01|max:99999999.99',
            'parcels.*.weight'      => 'required_if:service.service,Parcel|numeric|min:0.01|max:99999999.99',
            'parcels.*.numBoxes'    => 'required_if:service.service,Parcel|integer|min:1|max:127',
            'parcels.*.volWeight'   => 'required_if:service.service,Parcel|numeric|min:0|max:99999999.99',

            // Invoices - only required for Parcel service
            'invoices'                      => 'required_if:service.service,Parcel|nullable|array',
            'invoices.*.invoiceNumber'      => 'required_if:service.service,Parcel|string|max:255',
            'invoices.*.invoiceAmount'      => 'required_if:service.service,Parcel|numeric|min:0|max:99999999.99',
            'invoices.*.ewayBill'           => 'nullable|string|max:255',

            // Doc dimensions - only required for Document service
            'docDimensions.length'  => 'required_if:service.service,Document|numeric|min:0.01|max:99999999.99',
            'docDimensions.width'   => 'required_if:service.service,Document|numeric|min:0.01|max:99999999.99',
            'docDimensions.height'  => 'required_if:service.service,Document|numeric|min:0.01|max:99999999.99',
            'docDimensions.weight'  => 'required_if:service.service,Document|numeric|min:0.01|max:99999999.99',
            'docDimensions.volWeight'  => 'nullable|numeric|min:0|max:99999999.99',

            // Rates
            'rates'                         => 'nullable|array',
            'rates.cft'                     => 'nullable|decimal:0,2|min:0|max:99999999.99',
            'rates.chargeableWeight'        => 'nullable|numeric|min:0|max:99999999.99',
            'rates.packageYield'            => 'nullable|numeric|min:0|max:99999999.99',
            'rates.freight'                 => 'nullable|numeric|min:0|max:99999999.99',
            'rates.fuel'                    => 'nullable|numeric|min:0|max:99999999.99',
            'rates.awbFee'                  => 'nullable|numeric|min:0|max:99999999.99',
            'rates.fov'                     => 'nullable|numeric|min:0|max:99999999.99',
            'rates.insurance'               => 'nullable|in:owner,carrier',
            'rates.carrierInsurance'        => 'nullable|numeric|min:0|max:99999999.99',
            'rates.fod'                     => 'nullable|numeric|min:0|max:99999999.99',
            'rates.dod'                     => 'nullable|numeric|min:0|max:99999999.99',
            'rates.oda'                     => 'nullable|numeric|min:0|max:99999999.99',
            'rates.handling'                => 'nullable|numeric|min:0|max:99999999.99',
            'rates.dcc'                     => 'nullable|numeric|min:0|max:99999999.99',
            'rates.pickupcharges'           => 'nullable|numeric|min:0|max:99999999.99',
            'rates.deliverycharges'         => 'nullable|numeric|min:0|max:99999999.99',
            'rates.otherCharges'            => 'nullable|numeric|min:0|max:99999999.99',
            'rates.premiumCharges'          => 'nullable|numeric|min:0|max:99999999.99',
            'rates.total'                   => 'nullable|numeric|min:0|max:99999999.99',
            'rates.gst'                     => 'nullable|numeric|min:0|max:99999999.99',
            'rates.grandTotal'              => 'nullable|numeric|min:0|max:99999999.99',
        ]);

        $clean = fn($val) => ($val === '' || $val === null) ? null : $val;

        try {
            DB::beginTransaction();

            $s = $data['service'];
            $sh = $data['shipper'];
            $con = $data['consignee'];
            $cust = $data['customer'];
            $dod = $request->input('dodCodDetails', []);

            $shipment = Shipment::create([
                'branch_id' => auth()->user()->hasAnyRole(['super-admin', 'admin'])
                    ? $data['branchId']
                    : auth()->user()->owner_id,
                'customer_id'           => $cust['customerId'] ?? null,
                'awb_number'            => $s['trackingNumber'] ?? null,
                // 'customer_type'         => $cust['customerType'],
                'status'                => $data['status'],
                'service_type'          => $s['serviceType'] ?? 'Surface',
                'service'               => $s['service'],
                'payment_mode'          => $s['paymentMode'] ?? 'Regular',
                'customer_reference'    => $s['customerRef'] ?? null,
                'parcel_content'        => $s['parcelContent'] ?? null,

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
                'receiver_name'         => $con['receiverName'] ?? null,
                'consignee_phone'       => $con['consigneePhone'] ?? null,
                'consignee_gst'         => $con['consigneeGst'] ?? null,
                'consignee_address_line1'         => $con['consigneeAddLine1'] ?? null,
                'consignee_address_line2'         => $con['consigneeAddLine2'] ?? null,
                'consignee_city'        => $con['consigneeAddCity'] ?? null,
                'consignee_pincode'     => $con['consigneePincode'] ?? null,
                'consignee_state_id'    => $con['consigneeState'] ?? null,

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
                        'volumetric_weight' => $p['volWeight'],
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
                    'volumetric_weight' => $doc['volWeight'] ?? null,
                ]);
            }   

            // Invoices
            if (!empty($data['invoices'])) {
                $shipment->shipmentInvoices()->createMany(
                    collect($data['invoices'])->map(fn($i) => [
                        'invoice_number' => $i['invoiceNumber'],
                        'invoice_amount' => $i['invoiceAmount'],
                        'eway_bill'      => $i['ewayBill'] ?? null,
                    ])->toArray()
                );
            }

            // Charges
            $chargeRecord = null;
            if (!empty($data['rates'])) {
                // $r = $data['rates'];
                $chargeRecord =  $shipment->charges()->create([
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
                    'other_charges'     => $data['rates']['otherCharges'] ?? 0,
                    'premium_charges'   => $data['rates']['premiumCharges'] ?? 0,
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

            // ── Cash invoice — only for booked shipments with charges ──────────
            // Corporate shipments are invoiced monthly via VkInvoiceController.
            // Cash shipments get an invoice immediately on booking.
            if ($data['status'] === 'booked'
                && $cust['customerType'] === 'cash'
                && $chargeRecord
            ) {
                $shipment->load('branch.location.state');
                $isIntraState = $this->resolveIntraState($shipment);
    
                $invoice = new Invoice([
                    'invoice_number' => Invoice::generateInvoiceNumber(),
                    'type'           => 'cash',
                    'branch_id'      => $shipment->branch_id,
                    'customer_id'    => $shipment->customer_id,
                    'status'         => 'finalized',
                    'created_by'     => auth()->id(),
                ]);
    
                $invoice->computeTotalsFromCharges(collect([$chargeRecord]), $isIntraState);
                $invoice->save();
                $invoice->shipments()->attach($shipment->id);
            }

            DB::commit();

            return response()->json([
                'message'  => 'Shipment ' . $data['status'] . ' successfully!',
                'data'     => $shipment->load(['parcels', 'shipmentInvoices', 'charges', 'events']),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to save shipment', 'error' => $this->errorDetail($e)], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Shipment $shipment)
    {
        $this->authorizeShipment($shipment);

        return response()->json([
            'data' => $shipment->load(['parcels', 'shipmentInvoices', 'charges', 'events.entity', 'events.createdBy', 'assignments.assignedTo', 'customer']),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Shipment $shipment)
    {
        $this->denyWarehouseRoles();
        $this->authorizeShipment($shipment);

        if (!in_array($shipment->status, ['draft', 'booked'])) {
            return response()->json(['message' => 'Only draft or booked shipments can be edited'], 403);
        }

        $data = $request->validate([
            'status'                  => 'required|in:draft,booked',
            'branchId'                => [
                auth()->user()->hasAnyRole(['super-admin', 'admin']) ? 'required' : 'nullable',
                'exists:branches,id'
            ],
            'customer.customerId'     => ['nullable', 'exists:customers,id', $this->ownCustomerRule()],
            'customer.customerType'   => 'required|in:cash,corporate',
            'service.serviceType'     => 'nullable|in:Surface,Apex,Domestic Priority',
            'service.service'         => 'required|in:Parcel,Document',
            'service.paymentMode'     => 'nullable|in:Regular,FOD,DOD,COD',
            'service.customerRef'     => 'nullable|string|max:255',
            'service.parcelContent'   => 'nullable|string|max:255',
            'service.trackingNumber'  => ['nullable', 'string', 'max:255', \Illuminate\Validation\Rule::unique('shipments', 'awb_number')->ignore($shipment->id)],
            'specialInstruction'      => 'nullable|string|max:255',

            'shipper.shipperName'           => 'required|string|max:255',
            'shipper.shipperCompany'        => 'nullable|string|max:255',
            'shipper.shipperPhone'          => 'nullable|regex:/^[6-9]\d{9}$/',
            'shipper.shipperEmail'          => 'nullable|email|max:255',
            'shipper.shipperGst'            => 'nullable|regex:/^[0-9]{2}[A-Za-z]{5}[0-9]{4}[A-Za-z][1-9A-Za-z]Z[0-9A-Za-z]$/',
            'shipper.shipperAddLine1'       => 'nullable|string|max:255',
            'shipper.shipperAddLine2'       => 'nullable|string|max:255',
            'shipper.shipperAddCity'        => 'nullable|string|max:255',
            'shipper.shipperState'          => 'nullable|exists:states,id',
            'shipper.shipperPincode'        => 'nullable|digits:6',

            'consignee.consigneeName'       => 'required|string|max:255',
            'consignee.receiverName'        => 'required|string|max:255',
            'consignee.consigneePhone'      => 'nullable|regex:/^[6-9]\d{9}$/',
            'consignee.consigneeGst'        => 'nullable|regex:/^[0-9]{2}[A-Za-z]{5}[0-9]{4}[A-Za-z][1-9A-Za-z]Z[0-9A-Za-z]$/',
            'consignee.consigneeAddLine1'   => 'nullable|string|max:255',
            'consignee.consigneeAddLine2'   => 'nullable|string|max:255',
            'consignee.consigneeAddCity'    => 'nullable|string|max:255',
            'consignee.consigneePincode'    => 'nullable|digits:6',
            'consignee.consigneeState'      => 'nullable|exists:states,id',

            'dodCodDetails.inFavour'         => 'required_if:service.paymentMode,DOD,COD|nullable|string|max:255',
            'dodCodDetails.payableAt'        => 'required_if:service.paymentMode,DOD,COD|nullable|string|max:255',
            'dodCodDetails.collectableAmount' => 'required_if:service.paymentMode,DOD,COD|nullable|numeric|min:0.01|max:99999999.99',

            'parcels'               => 'required_if:service.service,Parcel|nullable|array|min:1',
            'parcels.*.length'      => 'required_if:service.service,Parcel|numeric|min:0.01|max:99999999.99',
            'parcels.*.width'       => 'required_if:service.service,Parcel|numeric|min:0.01|max:99999999.99',
            'parcels.*.height'      => 'required_if:service.service,Parcel|numeric|min:0.01|max:99999999.99',
            'parcels.*.weight'      => 'required_if:service.service,Parcel|numeric|min:0.01|max:99999999.99',
            'parcels.*.numBoxes'    => 'required_if:service.service,Parcel|integer|min:1|max:127',
            'parcels.*.volWeight'   => 'nullable|numeric|min:0|max:99999999.99',

            'invoices'                      => 'required_if:service.service,Parcel|nullable|array',
            'invoices.*.invoiceNumber'      => 'required_if:service.service,Parcel|string|max:255',
            'invoices.*.invoiceAmount'      => 'required_if:service.service,Parcel|numeric|min:0|max:99999999.99',
            'invoices.*.ewayBill'           => 'nullable|string|max:255',

            'docDimensions.length'  => 'required_if:service.service,Document|numeric|min:0.01|max:99999999.99',
            'docDimensions.width'   => 'required_if:service.service,Document|numeric|min:0.01|max:99999999.99',
            'docDimensions.height'  => 'required_if:service.service,Document|numeric|min:0.01|max:99999999.99',
            'docDimensions.weight'  => 'required_if:service.service,Document|numeric|min:0.01|max:99999999.99',
            'docDimensions.volWeight' => 'nullable|numeric|min:0|max:99999999.99',

            'rates'                         => 'nullable|array',
            'rates.cft'                     => 'nullable|decimal:0,2|min:0|max:99999999.99',
            'rates.chargeableWeight'        => 'nullable|numeric|min:0|max:99999999.99',
            'rates.packageYield'            => 'nullable|numeric|min:0|max:99999999.99',
            'rates.freight'                 => 'nullable|numeric|min:0|max:99999999.99',
            'rates.fuel'                    => 'nullable|numeric|min:0|max:99999999.99',
            'rates.awbFee'                  => 'nullable|numeric|min:0|max:99999999.99',
            'rates.fov'                     => 'nullable|numeric|min:0|max:99999999.99',
            'rates.insurance'               => 'nullable|in:owner,carrier',
            'rates.carrierInsurance'        => 'nullable|numeric|min:0|max:99999999.99',
            'rates.fod'                     => 'nullable|numeric|min:0|max:99999999.99',
            'rates.dod'                     => 'nullable|numeric|min:0|max:99999999.99',
            'rates.oda'                     => 'nullable|numeric|min:0|max:99999999.99',
            'rates.handling'                => 'nullable|numeric|min:0|max:99999999.99',
            'rates.dcc'                     => 'nullable|numeric|min:0|max:99999999.99',
            'rates.pickupcharges'           => 'nullable|numeric|min:0|max:99999999.99',
            'rates.deliverycharges'         => 'nullable|numeric|min:0|max:99999999.99',
            'rates.otherCharges'            => 'nullable|numeric|min:0|max:99999999.99',
            'rates.premiumCharges'          => 'nullable|numeric|min:0|max:99999999.99',
            'rates.total'                   => 'nullable|numeric|min:0|max:99999999.99',
            'rates.gst'                     => 'nullable|numeric|min:0|max:99999999.99',
            'rates.grandTotal'              => 'nullable|numeric|min:0|max:99999999.99',
        ]);

        if ($shipment->status === 'booked' && $data['status'] === 'draft') {
            return response()->json(['message' => 'A booked shipment cannot be moved back to draft.'], 422);
        }

        try {
            DB::beginTransaction();

            $s   = $data['service'];
            $sh  = $data['shipper'];
            $con = $data['consignee'];
            $cust = $data['customer'];
            $dod = $request->input('dodCodDetails', []);

            $wasBooked = $shipment->status === 'booked';
            $nowBooked = $data['status'] === 'booked';

            $shipment->update([
                'branch_id'             => auth()->user()->hasAnyRole(['super-admin', 'admin'])
                                            ? $data['branchId']
                                            : $shipment->branch_id, // don't allow branch change for non-admins
                'customer_id'           => $cust['customerId'] ?? null,
                'status'                => $data['status'],
                'service_type'          => $s['serviceType'] ?? 'Surface',
                'service'               => $s['service'],
                'payment_mode'          => $s['paymentMode'] ?? 'Regular',
                'customer_reference'    => $s['customerRef'] ?? null,
                'parcel_content'        => $s['parcelContent'] ?? null,

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
                'receiver_name'         => $con['receiverName'] ?? null,
                'consignee_phone'       => $con['consigneePhone'] ?? null,
                'consignee_gst'         => $con['consigneeGst'] ?? null,
                'consignee_address_line1' => $con['consigneeAddLine1'] ?? null,
                'consignee_address_line2' => $con['consigneeAddLine2'] ?? null,
                'consignee_city'        => $con['consigneeAddCity'] ?? null,
                'consignee_state_id'    => $con['consigneeState'] ?? null,
                'consignee_pincode'     => $con['consigneePincode'] ?? null,

                'special_instructions'  => $data['specialInstruction'] ?? null,
                'in_favor_of'           => $dod['inFavour'] ?? null,
                'payable_at'            => $dod['payableAt'] ?? null,
                'collectable_amount'    => $dod['collectableAmount'] ?? null,
                'booked_at'             => !$wasBooked && $nowBooked ? now() : $shipment->booked_at,
            ]);

            // Parcels — delete and recreate
            $shipment->parcels()->delete();
            if ($s['service'] === 'Parcel' && !empty($data['parcels'])) {
                $shipment->parcels()->createMany(
                    collect($data['parcels'])->map(fn($p) => [
                        'length'    => $p['length'],
                        'width'     => $p['width'],
                        'height'    => $p['height'],
                        'weight'    => $p['weight'],
                        'num_boxes' => $p['numBoxes'],
                        'volumetric_weight' => $p['volWeight'] ?? null,
                    ])->toArray()
                );
            } elseif ($s['service'] === 'Document' && !empty($data['docDimensions'])) {
                $doc = $data['docDimensions'];
                $shipment->parcels()->create([
                    'length'             => $doc['length'],
                    'width'              => $doc['width'],
                    'height'             => $doc['height'],
                    'weight'             => $doc['weight'],
                    'num_boxes'          => 1,
                    'volumetric_weight'  => $doc['volWeight'] ?? round(($doc['length'] * $doc['width'] * $doc['height']) / 27000, 2),
                ]);
            }

            // Invoices — delete and recreate
            $shipment->shipmentInvoices()->delete();
            if (!empty($data['invoices'])) {
                $shipment->shipmentInvoices()->createMany(
                    collect($data['invoices'])->map(fn($i) => [
                        'invoice_number' => $i['invoiceNumber'],
                        'invoice_amount' => $i['invoiceAmount'],
                        'eway_bill'      => $i['ewayBill'] ?? null,
                    ])->toArray()
                );
            }

            // Charges — update or create, keep a reference for invoice recompute
            $chargeRecord = null;
            if (!empty($data['rates'])) {
                $chargeRecord = $shipment->charges()->updateOrCreate(
                    ['shipment_id' => $shipment->id],
                    [
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
                        'other_charges'     => $data['rates']['otherCharges'] ?? 0,
                        'premium_charges'   => $data['rates']['premiumCharges'] ?? 0,
                        'total'             => $data['rates']['total'] ?? 0,
                        'gst'               => $data['rates']['gst'] ?? 0,
                        'grand_total'       => $data['rates']['grandTotal'] ?? 0,
                    ]
                );
            }

            // Log event only if status changed to booked
            if (!$wasBooked && $nowBooked) {
                ShipmentEvent::create([
                    'shipment_id' => $shipment->id,
                    'event_type'  => 'booked',
                    'entity_id'   => auth()->user()->rel_id,
                    'entity_type' => auth()->user()->rel_type,
                    'notes'       => 'Shipment booked',
                    'created_by'  => auth()->id(),
                ]);
            }

            // ── Cash invoice handling ──────────────────────────────────────────
            // Only applies to cash customers. Corporate invoices are monthly batches.
            if ($cust['customerType'] === 'cash' && $chargeRecord) {
    
                $shipment->load('branch.location.state');
                $isIntraState = $this->resolveIntraState($shipment);
    
                // Find any existing VK invoice for this shipment
                $existingInvoice = Invoice::whereHas(
                    'shipments',
                    fn($q) => $q->where('shipments.id', $shipment->id)
                )->first();
    
                if (!$wasBooked && $nowBooked) {
                    // draft → booked: create a fresh invoice
                    $vkInvoice = new Invoice([
                        'invoice_number' => Invoice::generateInvoiceNumber(),
                        'type'           => 'cash',
                        'branch_id'      => $shipment->branch_id,
                        'customer_id'    => $shipment->customer_id,
                        'status'         => 'finalized',
                        'created_by'     => auth()->id(),
                    ]);
    
                    $vkInvoice->computeTotalsFromCharges(collect([$chargeRecord]), $isIntraState);
                    $vkInvoice->save();
                    $vkInvoice->shipments()->attach($shipment->id);
    
                } elseif ($wasBooked && $nowBooked && $existingInvoice) {
                    // booked → booked (charges edited): recompute totals on the existing invoice.
                    // The invoice number and date are preserved — only the amounts change.
                    $existingInvoice->computeTotalsFromCharges(collect([$chargeRecord]), $isIntraState);
                    $existingInvoice->save();
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Shipment updated successfully!',
                'data'    => $shipment->fresh()->load(['parcels', 'shipmentInvoices', 'charges', 'events']),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to update shipment', 'error' => $this->errorDetail($e)], 500);
        }
    }

    public function track(string $awb)
    {
        $shipment = Shipment::with(['events', 'branch'])
            ->where('awb_number', $awb)
            ->first();

        if (!$shipment) {
            return response()->json(['message' => 'Shipment not found'], 404);
        }

        return response()->json([
            'data' => [
                'awb_number'        => $shipment->awb_number,
                'status'            => $shipment->status,
                'service'           => $shipment->service,
                'service_type'      => $shipment->service_type,
                'booked_at'         => $shipment->booked_at,
                'origin_branch'     => $shipment->branch?->name,
                'shipper_city'      => $shipment->shipper_city,
                'consignee_name'    => $this->maskName($shipment->consignee_name),
                'consignee_city'    => $shipment->consignee_city,
                'events'            => $shipment->events->map(fn($e) => [
                    'status'     => $e->event_type,
                    'timestamp'  => $e->created_at,
                ]),
            ]
        ]);
    }

    /** Booking/editing is a head-office and branch activity; warehouse staff only move shipments. */
    private function denyWarehouseRoles(): void
    {
        abort_if(
            auth()->user()->hasAnyRole(['warehouse-admin', 'warehouse-employee']),
            403,
            'Warehouse users cannot book or edit shipments.'
        );
    }

    /** Non head-office users may only book for customers of their own branch. */
    private function ownCustomerRule(): \Closure
    {
        return function ($attribute, $value, $fail) {
            if (! $value || $this->isHeadOffice()) {
                return;
            }
            $branchId = \App\Models\Customer::whereKey($value)->value('branch_id');
            if ($branchId === null || (int) $branchId !== (int) auth()->user()->owner_id) {
                $fail('The selected customer does not belong to your branch.');
            }
        };
    }

    /** "Rahul Sharma" -> "R**** S*****" for the unauthenticated tracking endpoint. */
    private function maskName(?string $name): ?string
    {
        if (! $name) {
            return null;
        }
        return collect(preg_split('/\s+/', trim($name)))
            ->map(fn ($w) => mb_substr($w, 0, 1) . str_repeat('*', max(mb_strlen($w) - 1, 0)))
            ->implode(' ');
    }

    private function resolveIntraState(Shipment $shipment): bool
    {
        $branchStateName    = $shipment->branch?->location?->state?->name;
        $consigneeStateName = $shipment->consignee_state_id
            ? State::find($shipment->consignee_state_id)?->name
            : null;

        if (!$branchStateName || !$consigneeStateName) {
            return false;
        }

        return strtolower(trim($branchStateName)) === strtolower(trim($consigneeStateName));
    }
}

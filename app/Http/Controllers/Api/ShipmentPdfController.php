<?php

namespace App\Http\Controllers\Api;

use App\Helpers\AmountInWords;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Shipment;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class ShipmentPdfController extends Controller
{
    public function generate(Shipment $shipment): Response
    {
        // Load all required relations
        $shipment->load([
            'parcels',
            'shipmentInvoices',
            'charges',
            'branch.location.state',
            'customer',
            'customer.printConfig',
            'printOverride',
            'events',
        ]);

        $invoice = Invoice::whereHas('shipments', fn($q) => $q->where('shipments.id', $shipment->id))
            ->first();

            // print_r($invoice);

        // ── Branch state info ─────────────────────────────────────
        $branchState    = $shipment->branch?->location?->state;
        $branchGstCode  = $branchState?->gst_code;   // e.g. "29"
        $branchStateName = $branchState?->name;       // e.g. "Karnataka"

        // ── Consignee state info ──────────────────────────────────
        // consignee_state is stored as a state name string on the shipment
        // We look it up in states table to get its gst_code
        $consigneeState = $shipment->consignee_state_id;
        $consigneeStateName = \App\Models\State::where('id', $consigneeState)->value('name');
        $consigneeStateRecord = \App\Models\State::where('name', $consigneeStateName)->first();
        $consigneeGstCode   = $consigneeStateRecord?->gst_code; // e.g. "29"

        // ── Intra-state determination ─────────────────────────────
        // Compare state names (case-insensitive). If both are in the same
        // state → CGST + SGST. Otherwise → IGST.
        $isIntraState = $branchStateName && $consigneeStateName
            && strtolower(trim($branchStateName)) === strtolower(trim($consigneeStateName));
 
        // ── GST split ────────────────────────────────────────────
        $total      = (float) ($shipment->charges?->subtotal ?? $shipment->charges?->total ?? 0);
        $gst        = (float) ($shipment->charges?->gst ?? 0);
        $grandTotal = (float) ($shipment->charges?->grand_total ?? 0);
 
        $cgst = $isIntraState ? round($gst / 2, 2) : 0;
        $sgst = $isIntraState ? round($gst / 2, 2) : 0;
        $igst = $isIntraState ? 0 : $gst;
 
        // ── Amount in words ───────────────────────────────────────
        $amountInWords = AmountInWords::convert($grandTotal);
 
        // ── Place of supply — gst_code + state name ───────────────
        $placeOfSupply = collect([$consigneeGstCode, $consigneeStateName])
            ->filter()
            ->implode('-'); // e.g. "29-Karnataka"
 
        // ── Static VK GSTIN (replace once settings module is built) ──
        $gstin = config('vk.gstin', '29XXXXXXXXXXXXX');

        // Resolve the effective print config
        $config = PrintConfigController::resolveForShipment($shipment);

        $vkGstin = config('vk.gstin', '29XXXXXXXXXXXXX');

        $pdf = Pdf::loadView('pdf.shipment', [
            'shipment'      => $shipment,
            'invoice'       => $invoice,
            'branchGstCode' => $branchGstCode,
            'consigneeGstCode' => $consigneeGstCode,
            'placeOfSupply'  => $placeOfSupply,
            'isIntraState'   => $isIntraState,
            'cgst'           => $cgst,
            'sgst'           => $sgst,
            'igst'           => $igst,
            'grandTotal'     => $grandTotal,
            'amountInWords'  => $amountInWords,
            'vkGstin'        => $vkGstin,
            'config'   => $config,
        ])->setPaper('a4', 'portrait');

        $filename = 'VK-' . $shipment->awb_number . '.pdf';

        return $pdf->stream($filename);
    }
}
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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
            'invoices',
            'charges',
            'branch',
            'customer',
            'customer.printConfig',
            'printOverride',
            'events',
        ]);

        // Resolve the effective print config
        $config = PrintConfigController::resolveForShipment($shipment);

        $pdf = Pdf::loadView('pdf.shipment', [
            'shipment' => $shipment,
            'config'   => $config,
        ])->setPaper('a4', 'portrait');

        $filename = 'VK-' . $shipment->awb_number . '.pdf';

        return $pdf->stream($filename);
    }
}
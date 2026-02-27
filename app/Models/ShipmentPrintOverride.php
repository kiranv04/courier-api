<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShipmentPrintOverride extends Model
{
    protected $fillable = [
        'shipment_id',
        'show_shipper_details',
        'show_consignee_details',
        'show_shipper_gst',
        'show_consignee_gst',
        'show_parcel_dimensions',
        'show_invoice_details',
        'show_eway_bill',
        'show_charges_breakdown',
        'show_grand_total_only',
        'show_special_instructions',
        'created_by',
    ];

    protected $casts = [
        'show_shipper_details'      => 'boolean',
        'show_consignee_details'    => 'boolean',
        'show_shipper_gst'          => 'boolean',
        'show_consignee_gst'        => 'boolean',
        'show_parcel_dimensions'    => 'boolean',
        'show_invoice_details'      => 'boolean',
        'show_eway_bill'            => 'boolean',
        'show_charges_breakdown'    => 'boolean',
        'show_grand_total_only'     => 'boolean',
        'show_special_instructions' => 'boolean',
    ];

    public function shipment()
    {
        return $this->belongsTo(Shipment::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

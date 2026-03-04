<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShipmentCharge extends Model
{
    protected $fillable = [
        'shipment_id', 'cft', 'chargeable_weight', 'package_yield',
        'freight', 'fuel', 'awb_fee', 'fov',
        'insurance_type', 'carrier_insurance',
        'fod', 'dod', 'oda', 'handling', 'dcc',
        'pickup_charges', 'delivery_charges', 
        'other_charges', 'premium_charges',
        'total', 'gst', 'grand_total',
    ];

    protected $casts = [
        'chargeable_weight' => 'decimal:2',
        'package_yield'     => 'decimal:2',
        'freight'           => 'decimal:2',
        'fuel'              => 'decimal:2',
        'awb_fee'           => 'decimal:2',
        'fov'               => 'decimal:2',
        'carrier_insurance' => 'decimal:2',
        'fod'               => 'decimal:2',
        'dod'               => 'decimal:2',
        'oda'               => 'decimal:2',
        'handling'          => 'decimal:2',
        'dcc'               => 'decimal:2',
        'pickup_charges'    => 'decimal:2',
        'delivery_charges'  => 'decimal:2',
        'other_charges'     => 'decimal:2',
        'premium_charges'   => 'decimal:2',
        'total'             => 'decimal:2',
        'gst'               => 'decimal:2',
        'grand_total'       => 'decimal:2',
    ];

    public function shipment()
    {
        return $this->belongsTo(Shipment::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShipmentParcel extends Model
{
    protected $fillable = [
        'shipment_id', 'length', 'width', 'height',
        'weight', 'num_boxes', 'vol_weight',
    ];

    protected $casts = [
        'length' => 'decimal:2',
        'width' => 'decimal:2',
        'height' => 'decimal:2',
        'weight' => 'decimal:2',
        'vol_weight' => 'decimal:2',
    ];

    public function shipment()
    {
        return $this->belongsTo(Shipment::class);
    }
}

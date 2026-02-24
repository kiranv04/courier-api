<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShipmentInvoice extends Model
{
    protected $fillable = [
        'shipment_id', 'invoice_number', 'invoice_amount', 'eway_bill',
    ];

    protected $casts = [
        'invoice_amount' => 'decimal:2',
    ];

    public function shipment()
    {
        return $this->belongsTo(Shipment::class);
    }
}

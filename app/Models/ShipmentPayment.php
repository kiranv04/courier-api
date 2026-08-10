<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShipmentPayment extends Model
{
    protected $fillable = [
        'shipment_id',
        'payment_type',
        'transaction_id',
        'amount',
        'collected_by',
        'collected_at',
        'notes',
    ];
 
    protected $casts = [
        'amount'       => 'decimal:2',
        'collected_at' => 'datetime',
    ];
 
    public function shipment()
    {
        return $this->belongsTo(Shipment::class);
    }
 
    public function collectedBy()
    {
        return $this->belongsTo(User::class, 'collected_by');
    }
}

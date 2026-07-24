<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShipmentEvent extends Model
{
    // public $timestamps = false;

    protected $fillable = [
        'shipment_id', 'event_type', 'entity_id', 'entity_type', 'notes', 'received_by',
        'created_by', 
    ];

    const UPDATED_AT = null; // disable updated_at

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function shipment()
    {
        return $this->belongsTo(Shipment::class);
    }

    public function entity()
    {
        return $this->morphTo();
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

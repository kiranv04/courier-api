<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShipmentEvent extends Model
{
    // public $timestamps = false;

    protected $fillable = [
        'shipment_id', 'event_type', 'entity_id', 'entity_type', 'destination_type', 'destination_id', 'notes', 'received_by',
        'created_by', 
    ];

    const UPDATED_AT = null; // disable updated_at

    protected $casts = [
        'created_at' => 'datetime',
    ];

    /**
     * Restrict to each shipment's most recent event. Uses an index seek on
     * (shipment_id, id) per row instead of a table-wide MAX(id) GROUP BY.
     */
    public function scopeLatestPerShipment($query)
    {
        return $query->whereRaw(
            'shipment_events.id = (select max(le.id) from shipment_events as le where le.shipment_id = shipment_events.shipment_id)'
        );
    }

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

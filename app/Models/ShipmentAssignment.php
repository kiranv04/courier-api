<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShipmentAssignment extends Model
{
    protected $fillable = [
        'shipment_id', 'assigned_to', 'assigned_by',
        'assignment_type', 'status', 'notes', 'completed_at',
        'cod_amount_collected',
        'cod_collected_at',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
        'cod_collected_at' => 'datetime',
    ];

    public function shipment()
    {
        return $this->belongsTo(Shipment::class);
    }

    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}

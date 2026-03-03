<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceablePincode extends Model
{
    protected $fillable = [
        'pincode',
        'state_id',
        'area_code',
        'area_name',
        'region',
        'is_edl',
        'ecom_zone',
        'apex_service',
        'apex_tat',
        'apex_zone',
        'surface_service',
        'surface_tat',
        'surface_zone',
        'dp_service',
        'dp_tat',
        'dp_zone',
    ];

    protected $casts = [
        'is_edl'      => 'boolean',
        'apex_tat'    => 'integer',
        'surface_tat' => 'integer',
        'dp_tat'      => 'integer',
    ];

    public function state()
    {
        return $this->belongsTo(State::class);
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Warehouse extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'location_id',
        'address_line_1',
        'address_line_2',
        'address_line_3',
        'phone',
        'email',
        'region',
        'pincode',
        'state_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the location that owns the warehouse.
     */
    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function state()
    {
        return $this->belongsTo(State::class);
    }

    /**
     * Scope a query to only include active warehouses.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include inactive warehouses.
     */
    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }
}

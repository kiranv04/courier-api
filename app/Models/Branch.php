<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Branch extends Model
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
        'yield_ratio_door',
        'yield_ratio_warehouse',
        'region',
        'pincode',
        'state_id',
        'discount',
        'discount_type',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'yield_ratio' => 'decimal:2',
    ];

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function state()
    {
        return $this->belongsTo(State::class);
    }

    public function branchSetting()
    {
        return $this->hasOne(BranchSetting::class);
    }
}

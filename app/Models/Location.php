<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    protected $fillable = [
        'state_id',
        'name',
        'short_code',
        'pincode',
        'is_active',
    ];

    protected $casts = [
        'state_id' => 'integer',
        'is_active' => 'boolean',
    ];

    public function state()
    {
        return $this->belongsTo(State::class);
    }
}

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
        'address',
        'phone',
        'email',
        'yield_ratio',
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
}

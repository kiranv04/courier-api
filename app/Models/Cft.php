<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cft extends Model
{
    use HasFactory;

    protected $fillable = [
        'cft_value',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}

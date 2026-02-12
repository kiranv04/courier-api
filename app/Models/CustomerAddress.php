<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerAddress extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'address_type',
        'contact_person',
        'contact_phone',
        'email',
        'address_line1',
        'address_line2',
        'city',
        'state_id',
        'pincode',
        'gst_number',
        'is_default_pickup',
        'is_active',
    ];

    protected $casts = [
        'is_default_pickup' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Get the customer that owns the address.
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_name',
        'customer_code',
        'customer_type',
        'gst_number',
        'gst_image_path',
        'pan_number',
        'pan_image_path',
        'aadhar_number',
        'aadhar_image_path',
        'created_by',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the addresses for the customer.
     */

    public function addresses()
    {
        return $this->hasMany(CustomerAddress::class);
    }

    public static function generateCustomerCode(): string
    {
        // Get the highest existing number
        $last = self::orderBy('id', 'desc')
            ->first();

        $nextNumber = $last ? ((int) substr($last->customer_code, 5)) + 1 : 1;

        return sprintf('CUST-%06d', $nextNumber);
    }
}

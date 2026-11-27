<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'company_name',
        'customer_code',
        'customer_type',
        'type',
        'gst_number',
        'gst_image_path',
        'pan_number',
        'pan_image_path',
        'aadhar_number',
        'aadhar_image_path',
        'contact_person',
        'contact_phone',
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

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public static function generateCustomerCode(): string
    {
        return DB::transaction(function () {
            $lastCode = self::where('customer_code', 'like', 'CUST-%')
                ->lockForUpdate()
                ->orderByDesc('id')
                ->value('customer_code');

            $nextNumber = 1;
            if ($lastCode && preg_match('/(\d+)$/', $lastCode, $matches)) {
                $nextNumber = (int) $matches[1] + 1;
            }

            return sprintf('CUST-%06d', $nextNumber);
        });
    }

    public function printConfig()
    {
        return $this->hasOne(CustomerPrintConfig::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Shipment extends Model
{
    protected $fillable = [
        'awb_number', 'branch_id', 'customer_id', 'customer_type',
        'status', 'service_type', 'service', 'payment_mode',
        'shipper_name', 'shipper_company', 'shipper_phone', 'shipper_email',
        'shipper_gst', 'shipper_address_line1', 'shipper_address_line2',
        'shipper_city', 'shipper_state', 'shipper_pincode',
        'consignee_name', 'consignee_phone', 'consignee_gst',
        'consignee_address', 'consignee_pincode', 'consignee_city', 'consignee_state',
        'customer_ref', 'parcel_content', 'tracking_number', 'special_instructions',
        'in_favour_of', 'payable_at', 'collectable_amount',
        'created_by', 'booked_at',
    ];

    protected $casts = [
        'collectable_amount' => 'decimal:2',
        'booked_at' => 'datetime',
    ];

    // Auto-generate AWB on creation
    protected static function booted(): void
    {
        static::creating(function (Shipment $shipment) {
            if (empty($shipment->awb_number)) {
                $shipment->awb_number = self::generateAwb($shipment->branch_id);
            }
        });
    }

    private static function generateAwb(int $branchId): string
    {
        $now = now();
        $year = $now->format('y');   // 2-digit year: "25"
        $month = $now->format('m');  // 2-digit month: "02"

        $branchCode = \App\Models\Branch::where('id', $branchId)
            ->value('code'); // single column fetch, no full model load

        $count = self::where('branch_id', $branchId)
            ->whereYear('created_at', $now->year)
            ->whereMonth('created_at', $now->month)
            ->lockForUpdate()
            ->count();

        $sequence = str_pad($count + 1, 5, '0', STR_PAD_LEFT);

        return $branchCode . $year . $month . $sequence;
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function parcels()
    {
        return $this->hasMany(ShipmentParcel::class);
    }

    public function invoices()
    {
        return $this->hasMany(ShipmentInvoice::class);
    }

    public function charges()
    {
        return $this->hasOne(ShipmentCharge::class);
    }

    public function events()
    {
        return $this->hasMany(ShipmentEvent::class)->orderBy('created_at', 'asc');
    }

    public function latestEvent()
    {
        return $this->hasOne(ShipmentEvent::class)->latestOfMany();
    }

    public function assignments()
    {
        return $this->hasMany(ShipmentAssignment::class);
    }

    public function printOverride()
    {
        return $this->hasOne(ShipmentPrintOverride::class);
    }
}

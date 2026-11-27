<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Shipment extends Model
{
    protected $fillable = [
        'awb_number', 'branch_id', 'customer_id',
        'status', 'service_type', 'service', 'payment_mode',
        'shipper_name', 'shipper_company_name', 'shipper_phone', 'shipper_email',
        'shipper_gst', 'shipper_address_line1', 'shipper_address_line2',
        'shipper_city', 'shipper_state_id', 'shipper_pincode',
        'consignee_name', 'receiver_name', 'consignee_phone', 'consignee_gst',
        'consignee_address_line1', 'consignee_address_line2', 'consignee_pincode', 'consignee_city', 'consignee_state_id',
        'customer_reference', 'parcel_content', 'tracking_number', 'special_instructions',
        'in_favor_of', 'payable_at', 'collectable_amount',
        'created_by', 'booked_at', 'delivered_at',
    ];

    protected $casts = [
        'collectable_amount' => 'decimal:2',
        'booked_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    // Auto-generate AWB on creation
    /**
     * Shipments a user may see: head office sees all; a branch/warehouse user sees
     * shipments booked at, or that have passed through / been routed to, their location;
     * a delivery agent sees shipments assigned to them.
     */
    public function scopeVisibleTo($query, User $user)
    {
        if ($user->hasAnyRole(['super-admin', 'admin'])) {
            return $query;
        }

        $isBranch    = $user->hasAnyRole(['branch-admin', 'branch-employee']);
        $isWarehouse = $user->hasAnyRole(['warehouse-admin', 'warehouse-employee']);
        $isDelivery  = $user->hasRole('branch-delivery');

        if (! ($isBranch || $isWarehouse || $isDelivery) || (! $isDelivery && ! $user->owner_id)) {
            return $query->whereRaw('1 = 0');
        }

        $locType = $isWarehouse ? Warehouse::class : Branch::class;
        $locId   = $user->owner_id;

        return $query->where(function ($q) use ($isBranch, $isDelivery, $user, $locType, $locId) {
            // Always-false seed so every branch below can safely be an OR.
            $q->whereRaw('1 = 0');

            if ($isBranch) {
                $q->orWhere('shipments.branch_id', $locId);
            }
            if ($isDelivery) {
                $q->orWhereHas('assignments', fn ($a) => $a->where('assigned_to', $user->id));
            } else {
                $q->orWhereHas('events', fn ($e) => $e->where(fn ($w) => $w
                        ->where('entity_type', $locType)->where('entity_id', $locId))
                    ->orWhere(fn ($w) => $w
                        ->where('destination_type', $locType)->where('destination_id', $locId)));
            }
        });
    }

    public function isVisibleTo(User $user): bool
    {
        return static::query()->visibleTo($user)->whereKey($this->getKey())->exists();
    }

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
        $now    = now();
        $year   = $now->format('y');
        $month  = $now->format('m');

        $branchCode = \App\Models\Branch::where('id', $branchId)->value('code');

        $prefix = $branchCode . $year . $month; // e.g. "BPD2503"

        // Only count auto-generated AWBs (those that start with our prefix)
        $count = self::where('branch_id', $branchId)
            ->where('awb_number', 'like', $prefix . '%')
            ->lockForUpdate()
            ->count();

        $sequence = str_pad($count + 1, 5, '0', STR_PAD_LEFT);

        return $prefix . $sequence;
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

    public function shipmentInvoices()
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

    public function payment()
    {
        return $this->hasOne(ShipmentPayment::class);
    }

    public function printOverride()
    {
        return $this->hasOne(ShipmentPrintOverride::class);
    }

    public function invoices()
    {
        return $this->belongsToMany(Invoice::class, 'invoice_shipments');
    }

    public function manifests()
    {
        return $this->belongsToMany(Manifest::class, 'manifest_shipments')->withTimestamps();
    }
}

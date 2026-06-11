<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Invoice extends Model
{
    protected $table = 'invoices';

    protected $fillable = [
        'invoice_number',
        'type',
        'branch_id',
        'customer_id',
        'from_date',
        'to_date',
        'freight_vas',
        'fuel_surcharge',
        'fod_dod',
        'subtotal',
        'cgst',
        'sgst',
        'igst',
        'grand_total',
        'status',
        'created_by',
    ];

    protected $casts = [
        'from_date'      => 'date',
        'to_date'        => 'date',
        'freight_vas'    => 'decimal:2',
        'fuel_surcharge' => 'decimal:2',
        'fod_dod'        => 'decimal:2',
        'subtotal'       => 'decimal:2',
        'cgst'           => 'decimal:2',
        'sgst'           => 'decimal:2',
        'igst'           => 'decimal:2',
        'grand_total'    => 'decimal:2',
    ];

    // ── Relationships ────────────────────────────────────────────

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function shipments()
    {
        return $this->belongsToMany(Shipment::class, 'invoice_shipments')
                    ->withTimestamps();
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Invoice number generation ────────────────────────────────
    //
    // Format: VK-YYZZ-NNNNNN
    //   YY   = start year of financial year (2-digit)
    //   ZZ   = end year of financial year (2-digit)
    //   e.g. April 2025 – March 2026  →  VK-2526-000001
    //        April 2026 – March 2027  →  VK-2627-000001
    //
    // Sequence resets on April 1st each year.

    public static function generateInvoiceNumber(): string
    {
        $now = Carbon::now();

        // Financial year: April = month 4
        if ($now->month >= 4) {
            $fyStart = $now->year;
            $fyEnd   = $now->year + 1;
        } else {
            $fyStart = $now->year - 1;
            $fyEnd   = $now->year;
        }

        $fyLabel = substr($fyStart, 2, 2) . substr($fyEnd, 2, 2); // e.g. "2526"

        // Financial year boundaries for the count query
        $fyFrom = Carbon::create($fyStart, 4, 1)->startOfDay();
        $fyTo   = Carbon::create($fyEnd,   3, 31)->endOfDay();

        $count = self::whereBetween('created_at', [$fyFrom, $fyTo])
                     ->lockForUpdate()
                     ->count();

        $sequence = str_pad($count + 1, 6, '0', STR_PAD_LEFT);

        return "VK-{$fyLabel}-{$sequence}";
    }

    // ── Helpers ──────────────────────────────────────────────────

    /**
     * Compute and set all charge totals from a collection of ShipmentCharge models.
     * Call this before saving a new invoice.
     *
     * @param  \Illuminate\Support\Collection<ShipmentCharge>  $charges
     * @param  bool  $isIntraState
     */
    public function computeTotalsFromCharges(\Illuminate\Support\Collection $charges, bool $isIntraState): void
    {
        $freightVas = $charges->sum(fn($c) =>
            (float) $c->freight
            + (float) $c->awb_fee
            + (float) $c->fov
            + (float) $c->handling
            + (float) $c->oda
            + (float) $c->dcc
            + (float) $c->pickup_charges
            + (float) $c->delivery_charges
            + (float) $c->other_charges
            + (float) $c->premium_charges
            + (float) ($c->insurance_type === 'carrier' ? $c->carrier_insurance : 0)
        );

        $fuelSurcharge = $charges->sum(fn($c) => (float) $c->fuel);
        $fodDod        = $charges->sum(fn($c) => (float) $c->fod + (float) $c->dod);
        $subtotal      = $freightVas + $fuelSurcharge + $fodDod;
        $gst           = round($subtotal * 0.18, 2);
        $grandTotal    = $subtotal + $gst;

        $this->freight_vas    = round($freightVas, 2);
        $this->fuel_surcharge = round($fuelSurcharge, 2);
        $this->fod_dod        = round($fodDod, 2);
        $this->subtotal       = round($subtotal, 2);
        $this->cgst           = $isIntraState ? round($gst / 2, 2) : 0;
        $this->sgst           = $isIntraState ? round($gst / 2, 2) : 0;
        $this->igst           = $isIntraState ? 0 : $gst;
        $this->grand_total    = round($grandTotal, 2);
    }
}
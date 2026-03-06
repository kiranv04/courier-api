<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Manifest extends Model
{
    protected $fillable = [
        'manifest_number',
        'type',
        'origin_type',
        'origin_id',
        'destination_type',
        'destination_id',
        'delivery_agent_id',
        'status',
        'notes',
        'created_by',
        'closed_at',
    ];

    protected $casts = [
        'closed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Manifest $manifest) {
            $manifest->manifest_number = self::generateNumber($manifest->type);
        });
    }

    private static function generateNumber(string $type): string
    {
        $prefix = match($type) {
            'pickup'   => 'PKP',
            'dispatch' => 'DSP',
            'inbound'  => 'INB',
            'outbound' => 'OTB',
            'delivery' => 'DLV',
        };

        $count = self::where('type', $type)
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->count();

        return $prefix . '-' . now()->format('Ym') . '-' . str_pad($count + 1, 4, '0', STR_PAD_LEFT);
    }

    public function origin()
    {
        return $this->morphTo();
    }

    public function shipments()
    {
        return $this->belongsToMany(Shipment::class, 'manifest_shipments')->withTimestamps();
    }

    public function deliveryAgent()
    {
        return $this->belongsTo(User::class, 'delivery_agent_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
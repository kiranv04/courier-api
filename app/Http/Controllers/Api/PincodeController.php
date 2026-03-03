<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ServiceablePincode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PincodeController extends Controller
{
    /**
     * Check serviceability for a given pincode.
     *
     * GET /api/pincodes/check?pincode=560032
     */
    public function check(Request $request): JsonResponse
    {
        $request->validate([
            'pincode' => 'required|string|max:6',
        ]);

        $pincode = trim($request->query('pincode'));

        $record = ServiceablePincode::with('state')
            ->where('pincode', $pincode)
            ->first();

        if (! $record) {
            return response()->json([
                'pincode'       => $pincode,
                'serviceable'   => false,
                'message'       => 'This pincode is not serviceable.',
                'services'      => null,
            ], 404);
        }

        $services = [
            'apex'    => $this->formatService($record->apex_service, $record->apex_tat, $record->apex_zone),
            'surface' => $this->formatService($record->surface_service, $record->surface_tat, $record->surface_zone),
            'dp'      => $this->formatService($record->dp_service, $record->dp_tat, $record->dp_zone),
        ];

        // Overall serviceability — true if at least one service is available (both or inbound)
        $serviceable = collect($services)->contains(fn($s) => $s['available']);

        return response()->json([
            'pincode'      => $record->pincode,
            'state'        => $record->state?->name,
            'area'         => $record->area_name,
            'region'       => $record->region,
            'is_edl'       => $record->is_edl,
            'serviceable'  => $serviceable,
            'services'     => $services,
        ]);
    }

    private function formatService(?string $direction, ?int $tat, ?string $zone): array
    {
        // null means the pincode is not listed in that product's file at all
        if ($direction === null) {
            return [
                'available'  => false,
                'direction'  => null,
                'tat_hours'  => null,
                'zone'       => null,
            ];
        }

        return [
            'available'  => $direction !== 'none',
            'direction'  => $direction,   // 'both' | 'inbound' | 'none'
            'tat_hours'  => $tat,
            'zone'       => $zone,
        ];
    }
}
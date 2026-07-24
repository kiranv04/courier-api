<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use App\Models\ShipmentAssignment;
use App\Models\ShipmentEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DeliveryController extends Controller
{
    private array $failureReasons = [
        'customer_not_available',
        'wrong_address',
        'refused_delivery',
        'damaged',
        'other',
    ];

    public function shipments(): JsonResponse
    {
        $user = auth()->user();

        $assignments = ShipmentAssignment::where('assigned_to', $user->id)
            ->where('assignment_type', 'delivery')
            ->whereIn('status', ['pending', 'failed'])
            ->with([
                'shipment' => fn($q) => $q->with([
                    'parcels:id,shipment_id,weight,num_boxes',
                    'charges:id,shipment_id',
                ])
                ->select(
                    'id', 'awb_number', 'status', 'service', 'service_type',
                    'consignee_name', 'consignee_phone', 'consignee_address_line1', 'consignee_address_line2',
                    'consignee_city', 'consignee_pincode', 'consignee_state_id',
                    'payment_mode', 'collectable_amount', 'branch_id'
                ),
            ])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['data' => $assignments]);
    }

    public function deliver(Request $request, Shipment $shipment): JsonResponse
    {
        $user = auth()->user();

        $assignment = ShipmentAssignment::where('shipment_id', $shipment->id)
            ->where('assigned_to', $user->id)
            ->where('assignment_type', 'delivery')
            ->whereIn('status', ['pending', 'failed'])
            ->first();

        if (!$assignment) {
            return response()->json(['message' => 'Shipment not assigned to you'], 403);
        }

        $data = $request->validate([
            'cod_amount_collected' => 'nullable|numeric|min:0',
            'received_by'          => 'nullable|string|max:150',
        ]);

        try {
            DB::beginTransaction();

            $shipment->update(['status' => 'delivered']);

            ShipmentEvent::create([
                'shipment_id' => $shipment->id,
                'event_type'  => 'delivered',
                'entity_type' => 'App\\Models\\User',
                'entity_id'   => $user->id,
                'notes'       => 'Delivered by ' . $user->name,
                'received_by' => $data['received_by'] ?? null,
                'created_by'  => $user->id,
            ]);

            $assignmentData = ['status' => 'completed'];
            if ($request->filled('cod_amount_collected')) {
                $assignmentData['cod_amount_collected'] = $data['cod_amount_collected'];
                $assignmentData['cod_collected_at']     = now();
            }
            $assignment->update($assignmentData);

            DB::commit();

            return response()->json(['message' => 'Shipment marked as delivered']);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to update shipment',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function fail(Request $request, Shipment $shipment): JsonResponse
    {
        $user = auth()->user();

        $assignment = ShipmentAssignment::where('shipment_id', $shipment->id)
            ->where('assigned_to', $user->id)
            ->where('assignment_type', 'delivery')
            ->where('status', ['pending', 'failed'])
            ->first();

        if (!$assignment) {
            return response()->json(['message' => 'Shipment not assigned to you'], 403);
        }

        $data = $request->validate([
            'reason' => 'required|in:' . implode(',', $this->failureReasons),
            'notes'  => 'required_if:reason,other|nullable|string|max:500',
        ]);

        try {
            DB::beginTransaction();

            $shipment->update(['status' => 'exception']);

            ShipmentEvent::create([
                'shipment_id' => $shipment->id,
                'event_type'  => 'exception',
                'entity_type' => 'App\\Models\\User',
                'entity_id'   => $user->id,
                'notes'       => 'Failed delivery: ' . $data['reason'] .
                                 ($data['notes'] ? ' — ' . $data['notes'] : ''),
                'created_by'  => $user->id,
            ]);

            $assignment->update([
                'status' => 'failed',
                'notes'  => $data['reason'] . ($data['notes'] ? ': ' . $data['notes'] : ''),
            ]);

            DB::commit();

            return response()->json(['message' => 'Delivery attempt recorded']);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to update shipment',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function codSummary(): JsonResponse
    {
        $user  = auth()->user();
        $today = now()->toDateString();

        $summary = ShipmentAssignment::whereDate('cod_collected_at', $today)
            ->whereNotNull('cod_amount_collected')
            ->with('assignedTo:id,name,email')
            ->whereHas('assignedTo', fn($q) => $q
                ->where('owner_type', 'App\\Models\\Branch')
                ->where('owner_id', $user->owner_id)
            )
            ->get()
            ->groupBy('assigned_to')
            ->map(fn($assignments) => [
                'agent'       => $assignments->first()->assignedTo,
                'total'       => $assignments->sum('cod_amount_collected'),
                'count'       => $assignments->count(),
                'assignments' => $assignments->map(fn($a) => [
                    'shipment_id'          => $a->shipment_id,
                    'cod_amount_collected' => $a->cod_amount_collected,
                    'cod_collected_at'     => $a->cod_collected_at,
                ]),
            ])
            ->values();

        return response()->json(['data' => $summary]);
    }
}
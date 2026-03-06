<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Shipment;
use App\Models\ShipmentAssignment;
use App\Models\ShipmentEvent;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ShipmentStatusController extends Controller
{
    private array $transitions = [
        'booked'           => ['picked_up', 'in_transit', 'out_for_delivery', 'cancelled'],
        'picked_up'        => ['in_transit', 'exception', 'cancelled'],
        'in_transit'       => ['at_hub', 'at_branch', 'exception'],
        'at_hub'           => ['in_transit', 'exception'],
        'at_branch'        => ['out_for_delivery', 'exception'],
        'out_for_delivery' => ['delivered', 'exception'],
        'exception'        => ['in_transit', 'at_hub', 'at_branch', 'out_for_delivery', 'cancelled'],
    ];

    private array $roleRestrictions = [
        'picked_up'        => ['super-admin', 'admin', 'branch-admin', 'branch-employee'],
        'in_transit'       => ['super-admin', 'admin', 'branch-admin', 'branch-employee', 'warehouse-admin', 'warehouse-employee'],
        'at_hub'           => ['super-admin', 'admin', 'warehouse-admin', 'warehouse-employee'],
        'at_branch'        => ['super-admin', 'admin', 'branch-admin', 'branch-employee'],
        'out_for_delivery' => ['super-admin', 'admin', 'branch-admin', 'branch-employee'],
        'delivered'        => ['super-admin', 'admin', 'branch-admin', 'branch-employee', 'branch-delivery'],
        'exception'        => ['super-admin', 'admin', 'branch-admin', 'branch-employee', 'warehouse-admin', 'warehouse-employee', 'branch-delivery'],
        'cancelled'        => ['super-admin', 'admin', 'branch-admin'],
    ];

    public function nextTransitions(Shipment $shipment): JsonResponse
    {
        $userRoles = auth()->user()->getRoleNames()->toArray();
        $allowed   = $this->transitions[$shipment->status] ?? [];

        $filtered = array_values(array_filter(
            $allowed,
            fn($status) => !empty(array_intersect(
                $userRoles,
                $this->roleRestrictions[$status] ?? []
            ))
        ));

        return response()->json([
            'current_status' => $shipment->status,
            'transitions'    => $filtered,
        ]);
    }

    public function update(Request $request, Shipment $shipment): JsonResponse
    {
        $user      = auth()->user();
        $userRoles = $user->getRoleNames()->toArray();
        $newStatus = $request->input('status');

        // Validate the transition is allowed from current status
        $allowedTransitions = $this->transitions[$shipment->status] ?? [];
        if (!in_array($newStatus, $allowedTransitions)) {
            return response()->json([
                'message' => "Cannot transition from [{$shipment->status}] to [{$newStatus}]"
            ], 422);
        }

        // Validate user role can perform this transition
        $allowedRoles = $this->roleRestrictions[$newStatus] ?? [];
        if (empty(array_intersect($userRoles, $allowedRoles))) {
            return response()->json([
                'message' => 'You do not have permission to perform this status update'
            ], 403);
        }

        // Validate request payload based on target status
        $this->validatePayload($request, $newStatus);

        try {
            DB::beginTransaction();

            $shipment->update([
                'status'       => $newStatus,
                'delivered_at' => $newStatus === 'delivered' ? now() : $shipment->delivered_at,
            ]);

            [$entityType, $entityId] = $this->resolveEntity($user);

            // For in_transit, record where shipment is headed
            $destinationType = null;
            $destinationId   = null;
            if (in_array($newStatus, ['in_transit', 'at_branch'])) {
                $destinationType = $request->input('destination_type');
                $destinationId   = $request->input('destination_id');
            }

            ShipmentEvent::create([
                'shipment_id'      => $shipment->id,
                'event_type'       => $newStatus,
                'entity_type'      => $entityType,
                'entity_id'        => $entityId,
                'destination_type' => $destinationType,
                'destination_id'   => $destinationId,
                'notes'            => $request->input('notes'),
                'created_by'       => $user->id,
            ]);

            // Create assignment record for relevant statuses
            if ($newStatus === 'out_for_delivery') {
                ShipmentAssignment::create([
                    'shipment_id'     => $shipment->id,
                    'assigned_to'     => $request->input('delivery_agent_id'),
                    'assigned_by'     => $user->id,
                    'assignment_type' => 'delivery',
                    'status'          => 'pending',
                    'notes'           => $request->input('notes'),
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Status updated successfully',
                'data'    => $shipment->fresh()->load(['events', 'assignments']),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to update status',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function destinations(): JsonResponse
    {
        $branches = Branch::where('is_active', true)
            ->select('id', 'name', 'code')
            ->get()
            ->map(fn($b) => [
                'id'    => $b->id,
                'name'  => "{$b->name} ({$b->code})",
                'type'  => 'App\\Models\\Branch',
                'label' => 'Branch',
            ]);

        $warehouses = Warehouse::where('is_active', true)
            ->select('id', 'name', 'code')
            ->get()
            ->map(fn($w) => [
                'id'    => $w->id,
                'name'  => "{$w->name} ({$w->code})",
                'type'  => 'App\\Models\\Warehouse',
                'label' => 'Hub',
            ]);

        return response()->json([
            'data' => $branches->concat($warehouses)->values(),
        ]);
    }

    public function deliveryAgents(Request $request): JsonResponse
    {
        $branchId = $request->query('branch_id');

        if (!$branchId) {
            return response()->json(['message' => 'branch_id is required'], 422);
        }

        $agents = User::role('branch-delivery')
            ->where('owner_id', $branchId)
            ->where('owner_type', 'App\\Models\\Branch')
            ->where('is_active', true)
            ->select('id', 'name', 'email')
            ->get();

        return response()->json(['data' => $agents]);
    }

    private function validatePayload(Request $request, string $status): void
    {
        $rules = match($status) {
            'in_transit', 'at_branch' => [
                'destination_type' => 'required|in:App\\Models\\Branch,App\\Models\\Warehouse',
                'destination_id'   => 'required|integer',
                'notes'            => 'nullable|string|max:500',
            ],
            'out_for_delivery' => [
                'delivery_agent_id' => 'required|exists:users,id',
                'notes'             => 'nullable|string|max:500',
            ],
            'exception', 'cancelled' => [
                'notes' => 'required|string|max:500',
            ],
            default => [
                'notes' => 'nullable|string|max:500',
            ],
        };

        $request->validate($rules);
    }

    private function resolveEntity(User $user): array
    {
        if ($user->hasAnyRole(['warehouse-admin', 'warehouse-employee'])) {
            return ['App\\Models\\Warehouse', $user->owner_id];
        }
        return ['App\\Models\\Branch', $user->owner_id];
    }
}
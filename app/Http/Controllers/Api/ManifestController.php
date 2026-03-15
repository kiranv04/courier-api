<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Manifest;
use App\Models\Shipment;
use App\Models\ShipmentAssignment;
use App\Models\ShipmentEvent;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ManifestController extends Controller
{
    // Maps manifest type to required current status and resulting status
    private array $typeConfig = [
        'pickup'   => ['from' => ['booked'],              'to' => 'picked_up'],
        'dispatch' => ['from' => ['picked_up'],            'to' => 'in_transit'],
        'inbound' => ['from' => ['in_transit'],             'to' => null],
        'outbound' => ['from' => ['at_hub'],               'to' => 'in_transit'],
        'delivery' => ['from' => ['at_branch'],            'to' => 'out_for_delivery'],
    ];

    public function index(Request $request): JsonResponse
    {
        $user   = auth()->user();
        $query  = Manifest::with(['createdBy', 'deliveryAgent'])
            ->withCount('shipments')
            ->orderBy('created_at', 'desc');

        // Filter by origin (branch/warehouse)
        if (!$user->hasAnyRole(['super-admin', 'admin'])) {
            $query->where('origin_type', $user->owner_type)
                  ->where('origin_id', $user->owner_id);
        }

        if ($request->has('type')) {
            $query->where('type', $request->query('type'));
        }

        if ($request->has('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->query('date_from'));
        }
        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->query('date_to'));
        }

        return response()->json([
            'data' => $query->paginate(20),
        ]);
    }

    public function show(Manifest $manifest): JsonResponse
    {
        $manifest->load([
            'createdBy',
            'deliveryAgent',
            'shipments' => fn($q) => $q->with(['charges', 'parcels'])
                ->select('shipments.id', 'awb_number', 'status', 'consignee_name', 
                         'consignee_city', 'consignee_pincode', 'service', 
                         'service_type', 'branch_id'),
        ]);

        return response()->json(['data' => $manifest]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type'             => 'required|in:pickup,dispatch,inbound,outbound,delivery',
            'shipment_ids'     => 'required|array|min:1',
            'shipment_ids.*'   => 'required|integer|exists:shipments,id',
            'destination_type' => 'required_if:type,dispatch,outbound|nullable|string',
            'destination_id'   => 'required_if:type,dispatch,outbound|nullable|integer',
            'delivery_agent_id'=> 'required_if:type,delivery|nullable|exists:users,id',
            'notes'            => 'nullable|string|max:500',
        ]);

        $user      = auth()->user();
        $config    = $this->typeConfig[$data['type']];
        $fromStatuses = $config['from'];
        $toStatus = $data['type'] === 'inbound'
            ? ($user->hasAnyRole(['warehouse-admin', 'warehouse-employee']) ? 'at_hub' : 'at_branch')
            : $config['to'];

        // Verify all selected shipments are in the correct status
        $shipments = Shipment::whereIn('id', $data['shipment_ids'])
            ->whereIn('status', $fromStatuses)
            ->get();

        if ($shipments->count() !== count($data['shipment_ids'])) {
            return response()->json([
                'message' => 'One or more shipments are not in the correct status for this manifest type.',
            ], 422);
        }

        try {
            DB::beginTransaction();

            $originType = $user->hasAnyRole(['warehouse-admin', 'warehouse-employee'])
                ? 'App\\Models\\Warehouse'
                : 'App\\Models\\Branch';

            $manifest = Manifest::create([
                'type'              => $data['type'],
                'origin_type'       => $originType,
                'origin_id'         => $user->owner_id,
                'destination_type'  => $data['destination_type'] ?? null,
                'destination_id'    => $data['destination_id'] ?? null,
                'delivery_agent_id' => $data['delivery_agent_id'] ?? null,
                'notes'             => $data['notes'] ?? null,
                'created_by'        => $user->id,
                'status'            => 'open',
            ]);

            // Attach shipments to manifest
            $manifest->shipments()->attach($data['shipment_ids']);

            // Bulk update shipment statuses
            $updateData = ['status' => $toStatus];
            if ($toStatus === 'delivered') {
                $updateData['delivered_at'] = now();
            }

            Shipment::whereIn('id', $data['shipment_ids'])->update($updateData);

            // Bulk create shipment events
            $events = $shipments->map(fn($s) => [
                'shipment_id'      => $s->id,
                'event_type'       => $toStatus,
                'entity_type'      => $originType,
                'entity_id'        => $user->owner_id,
                'destination_type' => in_array($toStatus, ['in_transit']) ? ($data['destination_type'] ?? null) : null,
                'destination_id'   => in_array($toStatus, ['in_transit'])
                                        ? ($data['destination_id'] ?? null)
                                        : null,
                'notes'            => $data['notes'] ?? 'Manifest: ' . $manifest->manifest_number,
                'created_by'       => $user->id,
                'created_at'       => now(),
                'updated_at'       => now(),
            ])->toArray();

            ShipmentEvent::insert($events);

            // Create delivery assignments if delivery manifest
            if ($data['type'] === 'delivery') {
                $assignments = $shipments->map(fn($s) => [
                    'shipment_id'     => $s->id,
                    'assigned_to'     => $data['delivery_agent_id'],
                    'assigned_by'     => $user->id,
                    'assignment_type' => 'delivery',
                    'status'          => 'pending',
                    'notes'           => $data['notes'] ?? null,
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ])->toArray();

                ShipmentAssignment::insert($assignments);
            }

            DB::commit();

            return response()->json([
                'message' => 'Manifest created successfully!',
                'data'    => $manifest->load('shipments'),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to create manifest',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function close(Manifest $manifest): JsonResponse
    {
        if ($manifest->status === 'closed') {
            return response()->json(['message' => 'Manifest is already closed'], 422);
        }

        $manifest->update([
            'status'    => 'closed',
            'closed_at' => now(),
        ]);

        return response()->json(['message' => 'Manifest closed successfully']);
    }

    public function eligibleShipments(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'required|in:pickup,dispatch,inbound,outbound,delivery',
        ]);

        $user         = auth()->user();
        $type         = $request->query('type');
        $config       = $this->typeConfig[$type];
        $fromStatuses = $config['from'];

        $isWarehouse = $user->hasAnyRole(['warehouse-admin', 'warehouse-employee']);
        $isAdmin     = $user->hasAnyRole(['super-admin', 'admin']);

        $query = Shipment::whereIn('status', $fromStatuses)
            ->select(
                'id', 'awb_number', 'status', 'consignee_name',
                'consignee_city', 'service', 'service_type', 'branch_id'
            )
            ->with('parcels:id,shipment_id,weight,num_boxes')
            ->orderBy('created_at', 'desc');

        if (!$isAdmin) {
            if ($type === 'inbound') {
                // Shipments headed TO this location
                $entityType = $isWarehouse
                    ? 'App\\Models\\Warehouse'
                    : 'App\\Models\\Branch';

                $latestEventIds = ShipmentEvent::select(DB::raw('MAX(id) as id'))
                    ->groupBy('shipment_id')
                    ->pluck('id');

                $eligibleIds = ShipmentEvent::whereIn('id', $latestEventIds)
                    ->where('destination_type', $entityType)
                    ->where('destination_id', $user->owner_id)
                    ->pluck('shipment_id');

                $query->whereIn('id', $eligibleIds);

            } elseif ($type === 'delivery') {
                // Shipments currently AT this branch (latest event is at_branch here)
                $latestEventIds = ShipmentEvent::select(DB::raw('MAX(id) as id'))
                    ->groupBy('shipment_id')
                    ->pluck('id');

                $eligibleIds = ShipmentEvent::whereIn('id', $latestEventIds)
                    ->where('entity_type', 'App\\Models\\Branch')
                    ->where('entity_id', $user->owner_id)
                    ->where('event_type', 'at_branch')
                    ->pluck('shipment_id');

                $query->whereIn('id', $eligibleIds);

            } elseif ($type === 'outbound') {
                // Shipments currently AT this warehouse
                $latestEventIds = ShipmentEvent::select(DB::raw('MAX(id) as id'))
                    ->groupBy('shipment_id')
                    ->pluck('id');

                $eligibleIds = ShipmentEvent::whereIn('id', $latestEventIds)
                    ->where('entity_type', 'App\\Models\\Warehouse')
                    ->where('entity_id', $user->owner_id)
                    ->where('event_type', 'at_hub')
                    ->pluck('shipment_id');

                $query->whereIn('id', $eligibleIds);

            } else {
                // Pickup and dispatch — scoped to origin branch
                $query->where('branch_id', $user->owner_id);
            }
        }

        return response()->json(['data' => $query->get()]);
    }
}
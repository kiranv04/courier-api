<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Manifest;
use App\Models\Shipment;
use App\Models\ShipmentEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function branch(): JsonResponse
    {
        $user     = auth()->user();
        $branchId = $user->owner_id;
        $today    = now()->toDateString();

        $base = Shipment::where('branch_id', $branchId);

        $stats = [
            'bookings_today'     => (clone $base)->whereDate('created_at', $today)->count(),
            'pending_pickup'     => (clone $base)->where('status', 'booked')->count(),
            'in_transit'         => (clone $base)->where('status', 'in_transit')->count(),
            'at_branch'          => (clone $base)->where('status', 'at_branch')->count(),
            'out_for_delivery'   => (clone $base)->where('status', 'out_for_delivery')->count(),
            'delivered_today'    => (clone $base)
                ->where('status', 'delivered')
                ->whereHas('events', fn($q) => $q
                    ->where('event_type', 'delivered')
                    ->whereDate('created_at', $today)
                )->count(),
            'open_manifests'     => Manifest::where('origin_type', 'App\\Models\\Branch')
                ->where('origin_id', $branchId)
                ->where('status', 'open')
                ->count(),
            'recent_manifests'   => Manifest::where('origin_type', 'App\\Models\\Branch')
                ->where('origin_id', $branchId)
                ->whereDate('created_at', $today)
                ->withCount('shipments')
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get(['id', 'manifest_number', 'type', 'status', 'created_at']),
            'recent_shipments'   => (clone $base)
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get(['id', 'awb_number', 'status', 'consignee_name', 'consignee_city', 'created_at']),
        ];

        return response()->json(['data' => $stats]);
    }

    public function warehouse(): JsonResponse
    {
        $user        = auth()->user();
        $warehouseId = $user->owner_id;
        $today       = now()->toDateString();

        // Shipments currently at this warehouse
        $latestEventIds = ShipmentEvent::select(DB::raw('MAX(id) as id'))
            ->groupBy('shipment_id')
            ->pluck('id');

        // From those latest events, find ones that are at_hub at this warehouse
        $atHubIds = ShipmentEvent::whereIn('id', $latestEventIds)
            ->where('entity_type', 'App\\Models\\Warehouse')
            ->where('entity_id', $warehouseId)
            ->where('event_type', 'at_hub')
            ->pluck('shipment_id');

        // Inbound today — at_hub events at this warehouse today
        $inboundToday = ShipmentEvent::where('entity_type', 'App\\Models\\Warehouse')
            ->where('entity_id', $warehouseId)
            ->where('event_type', 'at_hub')
            ->whereDate('created_at', $today)
            ->count();

        // Outbound today — in_transit events created by this warehouse today
        $outboundToday = ShipmentEvent::where('entity_type', 'App\\Models\\Warehouse')
            ->where('entity_id', $warehouseId)
            ->where('event_type', 'in_transit')
            ->whereDate('created_at', $today)
            ->count();

        $stats = [
            'at_hub_count'     => $atHubIds->count(),
            'inbound_today'    => $inboundToday,
            'outbound_today'   => $outboundToday,
            'open_manifests'   => Manifest::where('origin_type', 'App\\Models\\Warehouse')
                ->where('origin_id', $warehouseId)
                ->where('status', 'open')
                ->count(),
            'recent_manifests' => Manifest::where('origin_type', 'App\\Models\\Warehouse')
                ->where('origin_id', $warehouseId)
                ->whereDate('created_at', $today)
                ->withCount('shipments')
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get(['id', 'manifest_number', 'type', 'status', 'created_at']),
            'recent_shipments' => Shipment::whereIn('id', $atHubIds)
                ->orderBy('updated_at', 'desc')
                ->limit(5)
                ->get(['id', 'awb_number', 'status', 'consignee_name', 'consignee_city', 'updated_at']),
        ];

        return response()->json(['data' => $stats]);
    }

    public function superadmin(): JsonResponse
    {
        $today = now()->toDateString();

        $stats = [
            'bookings_today'   => Shipment::whereDate('created_at', $today)->count(),
            'total_in_transit' => Shipment::where('status', 'in_transit')->count(),
            'out_for_delivery' => Shipment::where('status', 'out_for_delivery')->count(),
            'delivered_today'  => Shipment::where('status', 'delivered')
                ->whereHas('events', fn($q) => $q
                    ->where('event_type', 'delivered')
                    ->whereDate('created_at', $today)
                )->count(),
            'total_branches'   => \App\Models\Branch::where('is_active', true)->count(),
            'total_warehouses' => \App\Models\Warehouse::where('is_active', true)->count(),
            'open_manifests'   => Manifest::where('status', 'open')->count(),
            'recent_shipments' => Shipment::with('branch:id,name')
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get(['id', 'awb_number', 'status', 'consignee_name', 'consignee_city', 'branch_id', 'created_at']),
            'recent_manifests' => Manifest::withCount('shipments')
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get(['id', 'manifest_number', 'type', 'status', 'created_at']),
        ];

        return response()->json(['data' => $stats]);
    }
}
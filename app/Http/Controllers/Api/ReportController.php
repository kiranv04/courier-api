<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Invoice;
use App\Models\Shipment;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ReportController extends Controller
{
    private const SHIPMENT_STATUSES = [
        'booked', 'picked_up', 'in_transit', 'at_hub', 'at_branch',
        'out_for_delivery', 'delivered', 'exception', 'cancelled',
    ];

    private const INVOICE_TYPES = ['cash', 'corporate'];

    // ── Shipment report (JSON) ──────────────────────────────────

    public function shipments(Request $request): JsonResponse
    {
        $result = $this->buildShipmentReport($request);

        return response()->json(['data' => $result]);
    }

    public function shipmentsPdf(Request $request): Response
    {
        $result = $this->buildShipmentReport($request);

        $pdf = Pdf::loadView('pdf.shipment-report', [
            'report'  => $result,
            'filters' => $result['filters_applied'],
        ])->setPaper('a4', 'portrait');

        return $pdf->stream('shipment-report.pdf');
    }

    // ── Invoice report (JSON) ───────────────────────────────────

    public function invoices(Request $request): JsonResponse
    {
        $result = $this->buildInvoiceReport($request);

        return response()->json(['data' => $result]);
    }

    public function invoicesPdf(Request $request): Response
    {
        $result = $this->buildInvoiceReport($request);

        $pdf = Pdf::loadView('pdf.invoice-report', [
            'report'  => $result,
            'filters' => $result['filters_applied'],
        ])->setPaper('a4', 'portrait');

        return $pdf->stream('invoice-report.pdf');
    }

    // ── Builders ─────────────────────────────────────────────────

    private function buildShipmentReport(Request $request): array
    {
        $user    = auth()->user();
        $isAdmin = $user->hasAnyRole(['super-admin', 'admin']);

        $base = Shipment::query()->whereNotNull('booked_at');

        if (!$isAdmin) {
            $base->where('branch_id', $user->owner_id);
        } elseif ($request->filled('branch_id')) {
            $base->where('branch_id', $request->branch_id);
        }

        if ($request->filled('customer_type')) {
            $base->whereHas('customer', fn($q) => $q->where('customer_type', $request->customer_type));
        }

        if ($request->filled('service_type')) {
            $base->where('service_type', $request->service_type);
        }

        if ($request->filled('date_from')) {
            $base->whereDate('booked_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $base->whereDate('booked_at', '<=', $request->date_to);
        }

        $byStatus = $this->countsByKey((clone $base), 'status', self::SHIPMENT_STATUSES);

        $byBranch = null;
        if ($isAdmin && !$request->filled('branch_id')) {
            $byBranch = $this->shipmentsByBranch((clone $base));
        }

        return [
            'filters_applied' => [
                'branch_id'     => $isAdmin ? ($request->branch_id ?? null) : $user->owner_id,
                'customer_type' => $request->customer_type ?? null,
                'service_type'  => $request->service_type ?? null,
                'date_from'     => $request->date_from ?? null,
                'date_to'       => $request->date_to ?? null,
            ],
            'total'    => array_sum($byStatus),
            'by_status' => $byStatus,
            'by_branch' => $byBranch,
        ];
    }

    private function buildInvoiceReport(Request $request): array
    {
        $user    = auth()->user();
        $isAdmin = $user->hasAnyRole(['super-admin', 'admin']);

        $base = Invoice::query();

        if (!$isAdmin) {
            $base->where('branch_id', $user->owner_id);
        } elseif ($request->filled('branch_id')) {
            $base->where('branch_id', $request->branch_id);
        }

        if ($request->filled('customer_type')) {
            $base->whereHas('customer', fn($q) => $q->where('customer_type', $request->customer_type));
        }

        if ($request->filled('date_from')) {
            $base->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $base->whereDate('created_at', '<=', $request->date_to);
        }

        $byType = $this->countsByKey((clone $base), 'type', self::INVOICE_TYPES);

        $byBranch = null;
        if ($isAdmin && !$request->filled('branch_id')) {
            $byBranch = $this->invoicesByBranch((clone $base));
        }

        return [
            'filters_applied' => [
                'branch_id'     => $isAdmin ? ($request->branch_id ?? null) : $user->owner_id,
                'customer_type' => $request->customer_type ?? null,
                'date_from'     => $request->date_from ?? null,
                'date_to'       => $request->date_to ?? null,
            ],
            'total'   => array_sum($byType),
            'by_type' => $byType,
            'by_branch' => $byBranch,
        ];
    }

    // ── Helpers ──────────────────────────────────────────────────

    /**
     * Returns [key => count] for every value in $allKeys, defaulting to 0.
     */
    private function countsByKey($query, string $column, array $allKeys): array
    {
        $counts = $query
            ->groupBy($column)
            ->selectRaw("{$column}, count(*) as count")
            ->pluck('count', $column);

        $result = [];
        foreach ($allKeys as $key) {
            $result[$key] = (int) ($counts[$key] ?? 0);
        }

        return $result;
    }

    private function shipmentsByBranch($query): array
    {
        $rows = $query
            ->groupBy('branch_id', 'status')
            ->selectRaw('branch_id, status, count(*) as count')
            ->get();

        $branches = Branch::whereIn('id', $rows->pluck('branch_id')->unique())
            ->get(['id', 'name', 'code'])
            ->keyBy('id');

        $grouped = [];
        foreach ($rows as $row) {
            $branchId = $row->branch_id;

            if (!isset($grouped[$branchId])) {
                $grouped[$branchId] = [
                    'branch_id'   => $branchId,
                    'branch_name' => $branches[$branchId]->name ?? 'Unknown',
                    'branch_code' => $branches[$branchId]->code ?? '—',
                    'total'       => 0,
                    'by_status'   => array_fill_keys(self::SHIPMENT_STATUSES, 0),
                ];
            }

            $grouped[$branchId]['by_status'][$row->status] = (int) $row->count;
            $grouped[$branchId]['total'] += (int) $row->count;
        }

        return array_values($grouped);
    }

    private function invoicesByBranch($query): array
    {
        $rows = $query
            ->groupBy('branch_id', 'type')
            ->selectRaw('branch_id, type, count(*) as count')
            ->get();

        $branches = Branch::whereIn('id', $rows->pluck('branch_id')->unique())
            ->get(['id', 'name', 'code'])
            ->keyBy('id');

        $grouped = [];
        foreach ($rows as $row) {
            $branchId = $row->branch_id;

            if (!isset($grouped[$branchId])) {
                $grouped[$branchId] = [
                    'branch_id'   => $branchId,
                    'branch_name' => $branches[$branchId]->name ?? 'Unknown',
                    'branch_code' => $branches[$branchId]->code ?? '—',
                    'total'       => 0,
                    'by_type'     => array_fill_keys(self::INVOICE_TYPES, 0),
                ];
            }

            $grouped[$branchId]['by_type'][$row->type] = (int) $row->count;
            $grouped[$branchId]['total'] += (int) $row->count;
        }

        return array_values($grouped);
    }

    public function shipmentsDetail(Request $request): JsonResponse
    {
        $user    = auth()->user();
        $isAdmin = $user->hasAnyRole(['super-admin', 'admin']);

        $query = Shipment::with(['customer:id,company_name,customer_type'])
            ->whereNotNull('booked_at')
            ->select('id', 'awb_number', 'status', 'customer_id', 'branch_id',
                    'consignee_name', 'consignee_city', 'service_type', 'booked_at');

        if (!$isAdmin) {
            $query->where('branch_id', $user->owner_id);
        } elseif ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('customer_type')) {
            $query->whereHas('customer', fn($q) => $q->where('customer_type', $request->customer_type));
        }

        if ($request->filled('service_type')) {
            $query->where('service_type', $request->service_type);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('booked_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('booked_at', '<=', $request->date_to);
        }

        return response()->json(['data' => $query->orderByDesc('booked_at')->paginate(20)]);
    }

    // ── Invoice drill-down ──────────────────────────────────────

    public function invoicesDetail(Request $request): JsonResponse
    {
        $user    = auth()->user();
        $isAdmin = $user->hasAnyRole(['super-admin', 'admin']);

        $query = Invoice::with(['customer:id,company_name,customer_type'])
            ->select('id', 'invoice_number', 'type', 'customer_id', 'branch_id',
                    'grand_total', 'created_at');

        if (!$isAdmin) {
            $query->where('branch_id', $user->owner_id);
        } elseif ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('customer_type')) {
            $query->whereHas('customer', fn($q) => $q->where('customer_type', $request->customer_type));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        return response()->json(['data' => $query->orderByDesc('created_at')->paginate(20)]);
    }
}
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Shipment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InvoiceController extends Controller
{
    // ── List ────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $query = Invoice::with(['branch', 'customer', 'shipments', 'createdBy'])
            ->withCount('shipments')
            ->orderByDesc('created_at');

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        // if ($request->filled('status')) {
        //     $query->where('status', $request->status);
        // }

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        if($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        return response()->json(['data' => $query->paginate(20)]);
    }

    // ── Show ────────────────────────────────────────────────────

    public function show(Invoice $invoice): JsonResponse
    {
        $invoice->load([
            'branch',
            'customer',
            'createdBy',
            'shipments.charges',
            'shipments.parcels',
        ]);

        return response()->json(['data' => $invoice]);
    }

    // ── Create cash invoice ─────────────────────────────────────
    //
    // Called automatically when a shipment moves out of draft/booked
    // (i.e. when it is picked up). Can also be called manually.

    public function createCash(Request $request): JsonResponse
    {
        $data = $request->validate([
            'shipment_id' => 'required|exists:shipments,id',
        ]);

        $shipment = Shipment::with([
            'charges',
            'branch.location.state',
        ])->findOrFail($data['shipment_id']);

        // Guard: must not already have an invoice
        $existing = Invoice::whereHas('shipments', fn($q) => $q->where('shipments.id', $shipment->id))
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'An invoice already exists for this shipment.',
                'data'    => $existing,
            ], 409);
        }

        // Guard: shipment must be past draft
        if ($shipment->status === 'draft') {
            return response()->json([
                'message' => 'Cannot invoice a draft shipment.',
            ], 422);
        }

        if (!$shipment->charges) {
            return response()->json([
                'message' => 'Shipment has no charge record.',
            ], 422);
        }

        $isIntraState = $this->resolveIntraState($shipment);

        try {
            DB::beginTransaction();

            $invoice = new Invoice([
                'invoice_number' => Invoice::generateInvoiceNumber(),
                'type'           => 'cash',
                'branch_id'      => $shipment->branch_id,
                'customer_id'    => $shipment->customer_id,
                'status'         => 'finalized',
                'created_by'     => auth()->id(),
            ]);

            $invoice->computeTotalsFromCharges(
                collect([$shipment->charges]),
                $isIntraState
            );

            $invoice->save();
            $invoice->shipments()->attach($shipment->id);

            DB::commit();

            return response()->json([
                'message' => 'Cash invoice created successfully.',
                'data'    => $invoice->load('shipments'),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to create invoice.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    // ── Preview corporate invoice (before saving) ───────────────
    //
    // Admin selects customer + date range → system returns all
    // uninvoiced shipments in that period with a charge summary.

    public function previewCorporate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'branch_id'   => 'required|exists:branches,id',
            'from_date'   => 'required|date',
            'to_date'     => 'required|date|after_or_equal:from_date',
        ]);

        $shipments = $this->getUninvoicedShipments(
            $data['customer_id'],
            $data['branch_id'],
            $data['from_date'],
            $data['to_date']
        );

        if ($shipments->isEmpty()) {
            return response()->json([
                'message'   => 'No uninvoiced shipments found for this period.',
                'shipments' => [],
                'summary'   => null,
            ]);
        }

        $charges      = $shipments->pluck('charges')->filter();
        $isIntraState = $this->resolveIntraStateFromBranch(
            $shipments->first()->branch,
            $shipments->first()->consignee_state
        );

        $summary = $this->buildSummary($charges, $isIntraState);

        return response()->json([
            'shipments' => $shipments,
            'summary'   => $summary,
        ]);
    }

    // ── Create corporate invoice ────────────────────────────────

    public function createCorporate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'customer_id'  => 'required|exists:customers,id',
            'branch_id'    => 'required|exists:branches,id',
            'from_date'    => 'required|date',
            'to_date'      => 'required|date|after_or_equal:from_date',
            'shipment_ids' => 'required|array|min:1',
            'shipment_ids.*' => 'integer|exists:shipments,id',
        ]);

        // Verify all selected shipments belong to this customer/branch and are uninvoiced
        $shipments = Shipment::with(['charges', 'branch.location.state'])
            ->whereIn('id', $data['shipment_ids'])
            ->where('customer_id', $data['customer_id'])
            ->where('branch_id', $data['branch_id'])
            ->whereDoesntHave('vkInvoices')
            ->get();

        if ($shipments->count() !== count($data['shipment_ids'])) {
            return response()->json([
                'message' => 'One or more shipments are invalid, already invoiced, or do not belong to this customer/branch.',
            ], 422);
        }

        $charges      = $shipments->pluck('charges')->filter();
        $isIntraState = $this->resolveIntraState($shipments->first());

        try {
            DB::beginTransaction();

            $invoice = new Invoice([
                'invoice_number' => Invoice::generateInvoiceNumber(),
                'type'           => 'corporate',
                'branch_id'      => $data['branch_id'],
                'customer_id'    => $data['customer_id'],
                'from_date'      => $data['from_date'],
                'to_date'        => $data['to_date'],
                'status'         => 'finalized',
                'created_by'     => auth()->id(),
            ]);

            $invoice->computeTotalsFromCharges($charges, $isIntraState);
            $invoice->save();
            $invoice->shipments()->attach($data['shipment_ids']);

            DB::commit();

            return response()->json([
                'message' => 'Corporate invoice created successfully.',
                'data'    => $invoice->load('shipments.charges'),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to create corporate invoice.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    // ── Private helpers ─────────────────────────────────────────

    /**
     * Fetch shipments for a customer/branch/date range that are not yet invoiced.
     */
    private function getUninvoicedShipments(
        int    $customerId,
        int    $branchId,
        string $fromDate,
        string $toDate
    ) {
        return Shipment::with(['charges', 'branch.location.state', 'parcels'])
            ->where('customer_id', $customerId)
            ->where('branch_id', $branchId)
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->whereDoesntHave('vkInvoices')
            ->whereDate('booked_at', '>=', $fromDate)
            ->whereDate('booked_at', '<=', $toDate)
            ->orderBy('booked_at')
            ->get();
    }

    /**
     * Determine intra-state from a shipment's branch location vs consignee state.
     * Branch → location → state → gst_code compared to consignee_state (stored as state name).
     */
    private function resolveIntraState(Shipment $shipment): bool
    {
        $branchStateName    = $shipment->branch?->location?->state?->name;
        $consigneeStateName = $shipment->consignee_state;

        if (!$branchStateName || !$consigneeStateName) {
            return false;
        }

        return strtolower(trim($branchStateName)) === strtolower(trim($consigneeStateName));
    }

    private function resolveIntraStateFromBranch($branch, ?string $consigneeState): bool
    {
        $branchStateName = $branch?->location?->state?->name;

        if (!$branchStateName || !$consigneeState) {
            return false;
        }

        return strtolower(trim($branchStateName)) === strtolower(trim($consigneeState));
    }

    /**
     * Build a charge summary array from a collection of ShipmentCharge models.
     */
    private function buildSummary(\Illuminate\Support\Collection $charges, bool $isIntraState): array
    {
        $freightVas = $charges->sum(fn($c) =>
            (float) $c->freight + (float) $c->awb_fee + (float) $c->fov
            + (float) $c->handling + (float) $c->oda + (float) $c->dcc
            + (float) $c->pickup_charges + (float) $c->delivery_charges
            + (float) $c->other_charges + (float) $c->premium_charges
            + (float) ($c->insurance_type === 'carrier' ? $c->carrier_insurance : 0)
        );

        $fuel     = $charges->sum(fn($c) => (float) $c->fuel);
        $fodDod   = $charges->sum(fn($c) => (float) $c->fod + (float) $c->dod);
        $subtotal = round($freightVas + $fuel + $fodDod, 2);
        $gst      = round($subtotal * 0.18, 2);

        return [
            'freight_vas'    => round($freightVas, 2),
            'fuel_surcharge' => round($fuel, 2),
            'fod_dod'        => round($fodDod, 2),
            'subtotal'       => $subtotal,
            'cgst'           => $isIntraState ? round($gst / 2, 2) : 0,
            'sgst'           => $isIntraState ? round($gst / 2, 2) : 0,
            'igst'           => $isIntraState ? 0 : $gst,
            'grand_total'    => round($subtotal + $gst, 2),
            'is_intra_state' => $isIntraState,
        ];
    }
}
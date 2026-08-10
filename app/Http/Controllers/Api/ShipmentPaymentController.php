<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ShipmentPaymentController extends Controller
{
    /**
     * Fetch the existing payment for a shipment, if any.
     * Used to pre-fill the payment modal.
     */
    public function show(Shipment $shipment): JsonResponse
    {
        return response()->json([
            'data' => $shipment->payment()->with('collectedBy:id,name')->first(),
        ]);
    }
 
    /**
     * Record (or update) the single payment for a shipment, and mark
     * the shipment's cash invoice as paid, if one exists.
     */
    public function store(Request $request, Shipment $shipment): JsonResponse
    {
        $data = $request->validate([
            'payment_type'   => 'required|in:cash,neft,upi,cheque,dd,other',
            'transaction_id' => 'required_if:payment_type,neft,upi|nullable|string|max:100',
            'amount'         => 'required|numeric|min:0.01',
            'notes'          => 'nullable|string|max:255',
        ]);
 
        try {
            DB::beginTransaction();
 
            $payment = $shipment->payment()->updateOrCreate(
                ['shipment_id' => $shipment->id],
                [
                    'payment_type'   => $data['payment_type'],
                    'transaction_id' => $data['payment_type'] === 'cash' ? null : $data['transaction_id'],
                    'amount'         => $data['amount'],
                    'collected_by'   => auth()->id(),
                    'collected_at'   => now(),
                    'notes'          => $data['notes'] ?? null,
                ]
            );
 
            // Mark the linked cash invoice as paid, if one exists.
            // Corporate invoices are billed/settled monthly and aren't touched here.
            $cashInvoice = $shipment->invoices()->where('type', 'cash')->first();
            if ($cashInvoice) {
                $cashInvoice->update([
                    'payment_status' => 'paid',
                    'paid_at'        => now(),
                ]);
            }
 
            DB::commit();
 
            return response()->json([
                'message' => 'Payment recorded successfully!',
                'data'    => $payment->load('collectedBy:id,name'),
            ], 201);
 
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to record payment',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}

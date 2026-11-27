<?php

namespace App\Http\Controllers\Api;

use App\Helpers\AmountInWords;
use App\Http\Controllers\Controller;
use App\Models\BranchSetting;
use App\Models\CompanySettings;
use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class InvoicePdfController extends Controller
{
    public function generate(Invoice $invoice): Response
    {
        $this->authorizeInvoice($invoice);

        $invoice->load([
            'branch.location.state',
            'customer.addresses',
            'shipments.charges',
            'shipments.parcels',
            'shipments.manifests' => fn($q) => $q->where('type', 'pickup'),
            'shipments.events'    => fn($q) => $q->where('event_type', 'delivered'),
            'createdBy',
        ]);
 
        // ── Branch state info ─────────────────────────────────────
        $branchState     = $invoice->branch?->location?->state;
        $branchGstCode   = $branchState?->gst_code;
        $branchStateName = $branchState?->name;
 
        // ── Place of supply — use branch state since this is VK's invoice ──
        // For corporate invoices the place of supply is where VK's branch is
        $placeOfSupply = collect([$branchGstCode, $branchStateName])
            ->filter()
            ->implode('-');
 
        // ── Intra-state: compare branch state to customer's billing state ──
        $billingAddr = $invoice->customer?->addresses
            ?->firstWhere('address_type', 'billing')
            ?? $invoice->customer?->addresses?->first();
 
        $customerStateRecord = $billingAddr?->state_id
            ? \App\Models\State::find($billingAddr->state_id)
            : null;
 
        $isIntraState = $branchStateName && $customerStateRecord
            && strtolower(trim($branchStateName)) === strtolower(trim($customerStateRecord->name ?? ''));

        // ── Amount in words ───────────────────────────────────────
        $amountInWords = AmountInWords::convert($invoice->grand_total);
 
        // ── Company/branch GSTIN (branch override, falling back to company default) ──
        $vkGstin = $invoice->branch ? BranchSetting::gstinForBranch($invoice->branch) : null;

        // ── Company settings (letterhead, logo, terms, bank details) ──
        $company = CompanySettings::current();
        $logoAbsolutePath = $company->logo_path && Storage::disk('public')->exists($company->logo_path)
            ? Storage::disk('public')->path($company->logo_path)
            : null;

        $pdf = Pdf::loadView('pdf.corporate-invoice', [
            'vkInvoice'     => $invoice,
            'branchGstCode' => $branchGstCode,
            'placeOfSupply' => $placeOfSupply,
            'isIntraState'  => $isIntraState,
            'amountInWords' => $amountInWords,
            'vkGstin'       => $vkGstin,
            'company'       => $company,
            'logoPath'      => $logoAbsolutePath,
        ])->setPaper('a4', 'portrait');
 
        $filename = 'VK-CORP-' . $invoice->invoice_number . '.pdf';
 
        return $pdf->stream($filename);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\BranchSetting;
use App\Models\CompanySettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SettingsController extends Controller
{
    // ── Company settings (single row) ──────────────────────────────

    public function showCompany()
    {
        return response()->json([
            'data' => CompanySettings::current(),
        ]);
    }

    public function updateCompany(Request $request)
    {
        $data = $request->validate([
            'company_name'          => 'required|string|max:255',
            'company_abbreviation'  => 'required|string|max:10',
            'invoice_format'        => ['required', 'string', 'max:100', $this->invoiceFormatRule()],
            'default_gstin'         => 'nullable|string|size:15',
            'fy_start_month'        => 'required|integer|min:1|max:12',
            'company_address'       => 'nullable|string|max:1000',
            'terms_conditions'      => 'nullable|string',
            'bank_account_name'     => 'nullable|string|max:255',
            'bank_account_number'   => 'nullable|string|max:50',
            'bank_ifsc_code'        => 'nullable|string|max:20',
            'bank_name'             => 'nullable|string|max:255',
            'bank_branch_name'      => 'nullable|string|max:255',
        ]);

        $settings = CompanySettings::current();
        $settings->update($data);

        return response()->json([
            'message' => 'Company settings updated successfully!',
            'data'    => $settings->fresh(),
        ]);
    }

    public function uploadLogo(Request $request)
    {
        $request->validate([
            'logo' => 'required|image|mimes:jpg,jpeg,png,svg|max:2048',
        ]);

        $settings = CompanySettings::current();

        if ($settings->logo_path) {
            Storage::disk('public')->delete($settings->logo_path);
        }

        $path = $request->file('logo')->store('settings/logo', 'public');
        $settings->update(['logo_path' => $path]);

        return response()->json([
            'message' => 'Logo uploaded successfully!',
            'data'    => $settings->fresh(),
        ]);
    }

    public function uploadBankQr(Request $request)
    {
        $request->validate([
            'qr_code' => 'required|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $settings = CompanySettings::current();

        if ($settings->bank_qr_path) {
            Storage::disk('public')->delete($settings->bank_qr_path);
        }

        $path = $request->file('qr_code')->store('settings/bank-qr', 'public');
        $settings->update(['bank_qr_path' => $path]);

        return response()->json([
            'message' => 'Bank QR code uploaded successfully!',
            'data'    => $settings->fresh(),
        ]);
    }

    public function deleteBankQr()
    {
        $settings = CompanySettings::current();

        if ($settings->bank_qr_path) {
            Storage::disk('public')->delete($settings->bank_qr_path);
            $settings->update(['bank_qr_path' => null]);
        }

        return response()->json([
            'message' => 'Bank QR code removed successfully!',
            'data'    => $settings->fresh(),
        ]);
    }

    // ── Branch settings (one row per branch) ───────────────────────

    public function showBranch(Branch $branch)
    {
        $setting = $branch->branchSetting ?: new BranchSetting(['branch_id' => $branch->id]);

        return response()->json([
            'data' => $setting,
        ]);
    }

    public function updateBranch(Request $request, Branch $branch)
    {
        $data = $request->validate([
            'gstin'                   => 'nullable|string|size:15',
            'transporter_ids'         => 'nullable|array',
            'transporter_ids.*'       => 'string|max:20',
            'default_transport_mode'  => 'nullable|string|max:50',
        ]);

        $setting = BranchSetting::updateOrCreate(
            ['branch_id' => $branch->id],
            $data
        );

        return response()->json([
            'message' => 'Branch settings updated successfully!',
            'data'    => $setting,
        ]);
    }

    // ── Helpers ──────────────────────────────────────────────────

    /**
     * Only allow the three known placeholders in invoice_format so a typo
     * can't silently break invoice number generation.
     */
    private function invoiceFormatRule(): \Closure
    {
        return function (string $attribute, $value, \Closure $fail) {
            $stripped = str_replace(['{PREFIX}', '{FY}', '{SEQ}'], '', $value);

            if (str_contains($stripped, '{') || str_contains($stripped, '}')) {
                $fail('The invoice format may only use {PREFIX}, {FY}, and {SEQ} placeholders.');
            }

            if (!str_contains($value, '{SEQ}')) {
                $fail('The invoice format must include a {SEQ} placeholder.');
            }
        };
    }
}
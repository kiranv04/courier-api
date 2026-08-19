<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class CompanySettings extends Model
{
    protected $table = 'company_settings';

    protected $fillable = [
        'company_name',
        'company_abbreviation',
        'invoice_format',
        'default_gstin',
        'fy_start_month',
        'company_address',
        'logo_path',
        'terms_conditions',
        'bank_account_name',
        'bank_account_number',
        'bank_ifsc_code',
        'bank_name',
        'bank_branch_name',
        'bank_qr_path',
    ];

    protected $casts = [
        'fy_start_month' => 'integer',
    ];

    /**
     * There is only ever one row in this table. Fetch it (creating a
     * sensible default row the first time) instead of querying by id.
     */
    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'company_name'         => 'VK Enterprises',
            'company_abbreviation' => 'VK',
            'invoice_format'       => '{PREFIX}-{FY}-{SEQ}',
            'fy_start_month'       => 4,
        ]);
    }

    /**
     * Financial year label for "now", e.g. "2526" for Apr 2025 - Mar 2026.
     * Mirrors the boundary logic that used to live in Invoice::generateInvoiceNumber().
     */
    public function financialYearLabel(?Carbon $at = null): string
    {
        $now = $at ?? Carbon::now();

        if ($now->month >= $this->fy_start_month) {
            $fyStart = $now->year;
            $fyEnd   = $now->year + 1;
        } else {
            $fyStart = $now->year - 1;
            $fyEnd   = $now->year;
        }

        return substr((string) $fyStart, 2, 2) . substr((string) $fyEnd, 2, 2);
    }

    /**
     * Financial year boundaries for "now", used to scope the invoice count query.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public function financialYearBounds(?Carbon $at = null): array
    {
        $now = $at ?? Carbon::now();

        if ($now->month >= $this->fy_start_month) {
            $fyStart = $now->year;
            $fyEnd   = $now->year + 1;
        } else {
            $fyStart = $now->year - 1;
            $fyEnd   = $now->year;
        }

        $from = Carbon::create($fyStart, $this->fy_start_month, 1)->startOfDay();
        $to   = Carbon::create($fyEnd, $this->fy_start_month, 1)->subDay()->endOfDay();

        return [$from, $to];
    }

    /**
     * Render invoice_format with {PREFIX} {FY} {SEQ} placeholders filled in.
     * SEQ is always zero-padded to 6 digits, matching current behavior.
     */
    public function renderInvoiceNumber(int $sequence, ?Carbon $at = null): string
    {
        $replacements = [
            '{PREFIX}' => $this->company_abbreviation,
            '{FY}'     => $this->financialYearLabel($at),
            '{SEQ}'    => str_pad((string) $sequence, 6, '0', STR_PAD_LEFT),
        ];

        return strtr($this->invoice_format, $replacements);
    }
}
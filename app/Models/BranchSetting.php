<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BranchSetting extends Model
{
    protected $table = 'branch_settings';

    protected $fillable = [
        'branch_id',
        'gstin',
        'transporter_ids',
        'default_transport_mode',
    ];

    protected $casts = [
        'transporter_ids' => 'array',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Resolve the effective GSTIN for a branch: its own if set,
     * otherwise the company-wide default.
     */
    public static function gstinForBranch(Branch $branch): ?string
    {
        return $branch->branchSetting?->gstin
            ?? CompanySettings::current()->default_gstin;
    }
}
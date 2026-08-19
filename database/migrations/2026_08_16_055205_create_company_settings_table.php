<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('company_settings', function (Blueprint $table) {
            $table->id();
            $table->string('company_name');
            $table->string('company_abbreviation', 10);
 
            // Template string using {PREFIX} {FY} {SEQ} placeholders,
            // e.g. "{PREFIX}-{FY}-{SEQ}" -> VK-2526-000001
            $table->string('invoice_format')->default('{PREFIX}-{FY}-{SEQ}');
 
            // Fallback GSTIN when a branch has no branch_settings row yet
            $table->string('default_gstin', 15)->nullable();
 
            // 1-12, financial year start month (India default: April)
            $table->unsignedTinyInteger('fy_start_month')->default(4);
 
            $table->text('company_address')->nullable();
            $table->string('logo_path')->nullable();
            $table->text('terms_conditions')->nullable();
 
            $table->string('bank_account_name')->nullable();
            $table->string('bank_account_number')->nullable();
            $table->string('bank_ifsc_code')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('bank_branch_name')->nullable();
            $table->string('bank_qr_path')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_settings');
    }
};

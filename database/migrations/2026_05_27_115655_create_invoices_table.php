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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number', 20)->unique();
            $table->enum('type', ['cash', 'corporate']);
            $table->foreignId('branch_id')->constrained('branches');
            $table->foreignId('customer_id')->nullable()->constrained('customers');
            
            // Date range — only used for corporate invoices
            $table->date('from_date')->nullable();
            $table->date('to_date')->nullable();

            // Charge breakdown — sums across all shipments on this invoice
            // freight_vas = freight + awb_fee + fov + handling + oda + dcc
            //             + pickup_charges + delivery_charges + other_charges
            //             + premium_charges + carrier_insurance
            $table->decimal('freight_vas',    10, 2)->default(0);
            $table->decimal('fuel_surcharge', 10, 2)->default(0);
            $table->decimal('fod_dod',        10, 2)->default(0);
            $table->decimal('subtotal',       10, 2)->default(0); // freight_vas + fuel_surcharge + fod_dod
            $table->decimal('cgst',           10, 2)->default(0);
            $table->decimal('sgst',           10, 2)->default(0);
            $table->decimal('igst',           10, 2)->default(0);
            $table->decimal('grand_total',    10, 2)->default(0);
 
            $table->enum('status', ['draft', 'finalized'])->default('draft');
 
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};

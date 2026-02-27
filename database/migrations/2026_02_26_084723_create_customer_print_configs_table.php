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
        Schema::create('customer_print_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->unique()->constrained('customers')->onDelete('cascade');
            $table->boolean('show_shipper_details')->default(true);
            $table->boolean('show_consignee_details')->default(true);
            $table->boolean('show_shipper_gst')->default(true);
            $table->boolean('show_consignee_gst')->default(true);
            $table->boolean('show_parcel_dimensions')->default(true);
            $table->boolean('show_invoice_details')->default(true);
            $table->boolean('show_eway_bill')->default(true);
            $table->boolean('show_charges_breakdown')->default(true);
            $table->boolean('show_grand_total_only')->default(false);
            $table->boolean('show_special_instructions')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_print_configs');
    }
};

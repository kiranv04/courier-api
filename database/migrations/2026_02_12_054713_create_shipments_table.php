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
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches');
            $table->foreignId('customer_id')->nullable()->constrained('customers');
            $table->string('awb_number')->unique();
            $table->enum('status', ['draft', 'booked', 'picked_up', 'in_transit', 'at_hub', 'out_for_delivery', 'delivered', 'exception', 'cancelled'])->default('draft');
            $table->enum('service_type', ['Surface', 'Apex', 'Domestic Priority'])->default('Surface');
            $table->enum('service', ['Parcel', 'Document'])->default('Parcel');
            $table->enum('payment_mode', ['Regular', 'FOD', 'DOD', 'COD'])->default('Regular');
            $table->string('shipper_name')->nullable();
            $table->string('shipper_company_name')->nullable();
            $table->string('shipper_phone')->nullable();
            $table->string('shipper_email')->nullable();
            $table->string('shipper_address_line1')->nullable();
            $table->string('shipper_address_line2')->nullable();
            $table->string('shipper_city')->nullable();
            $table->foreignId('shipper_state_id')->nullable()->constrained('states')->onDelete('set null');
            $table->string('shipper_pincode')->nullable();
            $table->string('consignee_name')->nullable();
            $table->string('consignee_phone')->nullable();
            $table->string('consignee_email')->nullable();
            $table->string('consignee_address_line1')->nullable();
            $table->string('consignee_address_line2')->nullable();
            $table->string('consignee_city')->nullable();
            $table->foreignId('consignee_state_id')->nullable()->constrained('states')->onDelete('set null');
            $table->string('consignee_pincode')->nullable();
            $table->string('customer_reference')->nullable();
            $table->string('parcel_content')->nullable();
            $table->string('tracking_number')->nullable();
            $table->string('special_instructions')->nullable();
            $table->string('in_favor_of')->nullable();
            $table->string('payable_at')->nullable();
            $table->decimal('collectable_amount', 10, 2)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('booked_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};

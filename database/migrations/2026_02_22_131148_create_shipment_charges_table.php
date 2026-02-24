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
        Schema::create('shipment_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained('shipments')->onDelete('cascade');
            $table->tinyInteger('cft')->nullable();
            $table->decimal('chargeable_weight', 10, 2)->nullable();
            $table->decimal('package_yield', 10, 2)->nullable();
            $table->decimal('freight', 10, 2)->nullable();
            $table->decimal('fuel', 10, 2)->nullable();
            $table->decimal('awb_fee', 10, 2)->nullable();
            $table->decimal('fov', 10, 2)->nullable();
            $table->enum('insurance_type', ['owner', 'carrier'])->default('owner');
            $table->decimal('carrier_insurance', 10, 2)->nullable();
            $table->decimal('fod', 10, 2)->nullable();
            $table->decimal('dod', 10, 2)->nullable();
            $table->decimal('oda', 10, 2)->nullable();
            $table->decimal('handling', 10, 2)->nullable();
            $table->decimal('dcc', 10, 2)->nullable();
            $table->decimal('pickup_charges', 10, 2)->nullable();
            $table->decimal('delivery_charges', 10, 2)->nullable(); 
            $table->decimal('total', 10, 2)->nullable(); 
            $table->decimal('gst', 10, 2)->nullable(); 
            $table->decimal('grand_total', 10, 2)->nullable(); 
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipment_charges');
    }
};

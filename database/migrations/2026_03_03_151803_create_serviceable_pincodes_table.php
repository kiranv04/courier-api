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
        Schema::create('serviceable_pincodes', function (Blueprint $table) {
            $table->id();
             $table->string('pincode', 6)->unique()->index();
            $table->foreignId('state_id')->nullable()->constrained('states')->nullOnDelete();
            $table->string('area_code', 10)->nullable();   // e.g. DEL, BOM, HYD
            $table->string('area_name', 191)->nullable();  // local hub name
            $table->string('region', 30)->nullable();      // NORTH, SOUTH1, EAST, WEST1
            $table->boolean('is_edl')->default(false);     // Extended Delivery Location (remote)
            $table->string('ecom_zone', 5)->nullable();    // for future rate calculations

            // APEX (Product: A)
            $table->enum('apex_service', ['both', 'inbound', 'none'])->nullable();
            $table->unsignedSmallInteger('apex_tat')->nullable(); // hours
            $table->string('apex_zone', 5)->nullable();

            // Surface (Product: E)
            $table->enum('surface_service', ['both', 'inbound', 'none'])->nullable();
            $table->unsignedSmallInteger('surface_tat')->nullable(); // hours
            $table->string('surface_zone', 5)->nullable();

            // Domestic Priority (Product: D)
            $table->enum('dp_service', ['both', 'inbound', 'none'])->nullable();
            $table->unsignedSmallInteger('dp_tat')->nullable(); // hours
            $table->string('dp_zone', 5)->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('serviceable_pincodes');
    }
};

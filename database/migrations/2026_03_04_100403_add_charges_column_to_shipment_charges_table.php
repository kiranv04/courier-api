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
        Schema::table('shipment_charges', function (Blueprint $table) {
            $table->decimal('other_charges', 10, 2)->nullable()->after('delivery_charges');
            $table->decimal('premium_charges', 10, 2)->nullable()->after('other_charges');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shipment_charges', function (Blueprint $table) {
            //
        });
    }
};

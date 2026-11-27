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
            $table->decimal('cft', 10, 2)->unsigned()->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shipment_charges', function (Blueprint $table) {
            $table->tinyInteger('cft')->nullable()->change();
        });
    }
};

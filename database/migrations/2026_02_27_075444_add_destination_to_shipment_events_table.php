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
        Schema::table('shipment_events', function (Blueprint $table) {
            $table->string('destination_type')->nullable()->after('entity_type');
            $table->unsignedBigInteger('destination_id')->nullable()->after('destination_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shipment_events', function (Blueprint $table) {
            //
        });
    }
};

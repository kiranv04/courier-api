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
        Schema::table('shipment_assignments', function (Blueprint $table) {
            $table->decimal('cod_amount_collected', 10, 2)->nullable()->after('notes');
            $table->timestamp('cod_collected_at')->nullable()->after('cod_amount_collected');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shipment_assignments', function (Blueprint $table) {
            $table->dropColumn(['cod_amount_collected', 'cod_collected_at']);
        });
    }
};

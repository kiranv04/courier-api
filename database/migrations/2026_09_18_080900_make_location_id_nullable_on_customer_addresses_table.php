<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * BE-04: location_id was a required FK but CustomerController::store()
     * never sets it (no location picker exists on the customer form) —
     * every POST /customers threw a not-null constraint violation on
     * customer_addresses. Making it nullable unblocks customer creation
     * without inventing a location-selection flow that doesn't exist yet.
     *
     * Guarded with hasColumn(): on this dev DB the column was already
     * dropped out-of-band (schema drifted ahead of the migrations table),
     * so there's nothing to alter here — this migration exists to keep a
     * fresh install (which still runs the original create_customer_addresses_table
     * migration with a required location_id) from reintroducing the bug.
     */
    public function up(): void
    {
        if (Schema::hasColumn('customer_addresses', 'location_id')) {
            Schema::table('customer_addresses', function (Blueprint $table) {
                $table->foreignId('location_id')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('customer_addresses', 'location_id')) {
            Schema::table('customer_addresses', function (Blueprint $table) {
                $table->foreignId('location_id')->nullable(false)->change();
            });
        }
    }
};

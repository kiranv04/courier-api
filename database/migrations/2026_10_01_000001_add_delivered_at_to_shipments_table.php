<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Status/manifest code already writes shipments.delivered_at, but no migration created it. */
    public function up(): void
    {
        if (! Schema::hasColumn('shipments', 'delivered_at')) {
            Schema::table('shipments', function (Blueprint $table) {
                $table->timestamp('delivered_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        // Intentionally empty: the column may pre-date this migration in production.
    }
};

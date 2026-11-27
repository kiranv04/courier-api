<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Raw MODIFY COLUMN is MySQL/MariaDB-only; other drivers (e.g. SQLite in tests) use the schema builder.
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            Schema::table('shipments', function (Blueprint $table) {
                $table->enum('status', ['draft','booked','picked_up','in_transit','at_hub','at_branch','out_for_delivery','delivered','exception','cancelled'])->default('draft')->change();
            });
            Schema::table('shipment_events', function (Blueprint $table) {
                $table->enum('event_type', ['created','booked','picked_up','in_transit','at_hub','at_branch','out_for_delivery','delivered','exception','cancelled'])->change();
            });
            return;
        }

        DB::statement("ALTER TABLE shipments MODIFY COLUMN status ENUM('draft','booked','picked_up','in_transit','at_hub','at_branch','out_for_delivery','delivered','exception','cancelled') NOT NULL");

        DB::statement("ALTER TABLE shipment_events MODIFY COLUMN event_type ENUM('created','booked','picked_up','in_transit','at_hub','at_branch','out_for_delivery','delivered','exception','cancelled') NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        DB::statement("ALTER TABLE shipments MODIFY COLUMN status ENUM('draft','booked','picked_up','in_transit','at_hub','out_for_delivery','delivered','exception','cancelled') NOT NULL");

        DB::statement("ALTER TABLE shipment_events MODIFY COLUMN event_type ENUM('created','booked','picked_up','in_transit','at_hub','out_for_delivery','delivered','exception','cancelled') NOT NULL");
    }
};

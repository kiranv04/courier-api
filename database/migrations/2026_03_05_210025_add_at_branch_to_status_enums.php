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
        DB::statement("ALTER TABLE shipments MODIFY COLUMN status ENUM('draft','booked','picked_up','in_transit','at_hub','at_branch','out_for_delivery','delivered','exception','cancelled') NOT NULL");

        DB::statement("ALTER TABLE shipment_events MODIFY COLUMN event_type ENUM('created','booked','picked_up','in_transit','at_hub','at_branch','out_for_delivery','delivered','exception','cancelled') NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE shipments MODIFY COLUMN status ENUM('draft','booked','picked_up','in_transit','at_hub','out_for_delivery','delivered','exception','cancelled') NOT NULL");

        DB::statement("ALTER TABLE shipment_events MODIFY COLUMN event_type ENUM('created','booked','picked_up','in_transit','at_hub','out_for_delivery','delivered','exception','cancelled') NOT NULL");
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipment_events', function (Blueprint $table) {
            // latest-event-per-shipment lookups (BE-22)
            $table->index(['shipment_id', 'id'], 'shipment_events_shipment_id_id_index');
            // "currently at this branch / warehouse" filters
            $table->index(['entity_type', 'entity_id', 'event_type'], 'shipment_events_entity_event_index');
        });
    }

    public function down(): void
    {
        Schema::table('shipment_events', function (Blueprint $table) {
            $table->dropIndex('shipment_events_shipment_id_id_index');
            $table->dropIndex('shipment_events_entity_event_index');
        });
    }
};

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
        Schema::create('manifests', function (Blueprint $table) {
            $table->id();
            $table->string('manifest_number')->unique();
            $table->enum('type', [
                'pickup',    // branch: booked → picked_up
                'dispatch',  // branch: picked_up/at_branch → in_transit
                'inbound',   // hub: in_transit → at_hub
                'outbound',  // hub: at_hub → in_transit
                'delivery',  // branch: at_branch → out_for_delivery
            ]);
            $table->morphs('origin');        // who created it (Branch or Warehouse)
            $table->string('destination_type')->nullable(); // for dispatch/outbound
            $table->unsignedBigInteger('destination_id')->nullable();
            $table->unsignedBigInteger('delivery_agent_id')->nullable();
            $table->foreign('delivery_agent_id')->references('id')->on('users')->nullOnDelete();
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('manifests');
    }
};

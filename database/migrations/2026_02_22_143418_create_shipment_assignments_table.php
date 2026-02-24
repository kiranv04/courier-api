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
        Schema::create('shipment_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained('shipments')->onDelete('cascade');
            $table->foreignId('assigned_to')->constrained('users')->onDelete('set null');
            $table->foreignId('assigned_by')->constrained('users')->onDelete('set null');
            $table->enum('assignment_type', ['pickup', 'linehaul', 'delivery'])->default('pickup');
            $table->enum('status', ['pending', 'accepted', 'completed', 'failed'])->default('pending');
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipment_assignments');
    }
};

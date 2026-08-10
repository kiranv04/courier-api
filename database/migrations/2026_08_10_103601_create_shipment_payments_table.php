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
        Schema::create('shipment_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->unique()->constrained('shipments')->onDelete('cascade');
            $table->enum('payment_type', ['cash', 'neft', 'upi', 'cheque', 'dd', 'other']);
            $table->string('transaction_id')->nullable();
            $table->decimal('amount', 10, 2);
            $table->foreignId('collected_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('collected_at')->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipment_payments');
    }
};

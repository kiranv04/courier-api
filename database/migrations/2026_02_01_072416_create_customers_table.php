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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('company_name');
            $table->string('customer_code')->unique();
            $table->enum('customer_type', ['cash', 'corporate'])->default('corporate');
            $table->string('gst_number')->nullable();
            $table->text('gst_image_path')->nullable();
            $table->string('pan_number')->nullable();
            $table->text('pan_image_path')->nullable();
            $table->string('aadhar_number')->nullable();
            $table->text('aadhar_image_path')->nullable();
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};

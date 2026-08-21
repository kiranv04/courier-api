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
        Schema::table('users', function (Blueprint $table) {
            $table->string('employee_number')->nullable()->after('id');
            $table->string('phone')->nullable()->after('email');
            $table->string('yield_ratio_door')->nullable()->after('remember_token');
            $table->string('yield_ratio_warehouse')->nullable()->after('yield_ratio_door');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['employee_number', 'phone', 'yield_ratio_door', 'yield_ratio_warehouse']);
        });
    }
};

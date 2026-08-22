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
        Schema::table('branches', function (Blueprint $table) {
            $table->string('address_line_1')->nullable()->after('location_id');
            $table->string('address_line_2')->nullable()->after('address_line_1');
            $table->string('address_line_3')->nullable()->after('address_line_2');
 
            $table->decimal('yield_ratio_door', 5, 2)->default(0.00)->after('yield_ratio');
            $table->decimal('yield_ratio_warehouse', 5, 2)->default(0.00)->after('yield_ratio_door');
 
            $table->string('region')->nullable()->after('yield_ratio_warehouse');
            $table->string('pincode')->nullable()->after('region');
 
            $table->foreignId('state_id')->nullable()->after('pincode')->constrained('states')->nullOnDelete();
 
            $table->decimal('discount', 8, 2)->nullable()->after('state_id');
            $table->enum('discount_type', ['percentage', 'flat'])->nullable()->after('discount');
        });
 
        DB::table('branches')->update([
            'address_line_1' => DB::raw('address'),
        ]);
        DB::table('branches')->update([
            'yield_ratio_door' => DB::raw('yield_ratio'),
        ]);

        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn(['address', 'yield_ratio']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->string('address')->nullable()->after('location_id');
            $table->decimal('yield_ratio', 5, 2)->default(0.00)->after('phone');
        });
 
        DB::table('branches')->update([
            'address' => DB::raw('address_line_1'),
        ]);
        DB::table('branches')->update([
            'yield_ratio' => DB::raw('yield_ratio_door'),
        ]);
 
        // Step 3: drop the new columns.
        Schema::table('branches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('state_id');
            $table->dropColumn([
                'address_line_1',
                'address_line_2',
                'address_line_3',
                'yield_ratio_door',
                'yield_ratio_warehouse',
                'region',
                'pincode',
                'discount',
                'discount_type',
            ]);
        });
    }
};

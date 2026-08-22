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
        Schema::table('warehouses', function (Blueprint $table) {
            $table->string('address_line_1')->nullable()->after('location_id');
            $table->string('address_line_2')->nullable()->after('address_line_1');
            $table->string('address_line_3')->nullable()->after('address_line_2');
 
            $table->string('region')->nullable()->after('email');
            $table->string('pincode')->nullable()->after('region');
 
            $table->foreignId('state_id')->nullable()->after('pincode')->constrained('states')->nullOnDelete();
        });
 
        DB::table('warehouses')->update([
            'address_line_1' => DB::raw('address'),
        ]);

        Schema::table('warehouses', function (Blueprint $table) {
            $table->dropColumn('address');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            $table->string('address')->nullable()->after('location_id');
        });
 
        DB::table('warehouses')->update([
            'address' => DB::raw('address_line_1'),
        ]);
 
        // Step 3: drop the new columns.
        Schema::table('branches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('state_id');
            $table->dropColumn([
                'address_line_1',
                'address_line_2',
                'address_line_3',
                'region',
                'pincode',
            ]);
        });
    }
};

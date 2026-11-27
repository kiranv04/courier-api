<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * CustomerController writes contact_person / contact_phone, but no earlier migration created
     * them (production likely had them added by hand). Guarded so it is a no-op where they exist.
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            if (! Schema::hasColumn('customers', 'contact_person')) {
                $table->string('contact_person', 150)->nullable();
            }
            if (! Schema::hasColumn('customers', 'contact_phone')) {
                $table->string('contact_phone', 15)->nullable();
            }
        });
    }

    public function down(): void
    {
        // Intentionally empty: the columns may pre-date this migration in production.
    }
};

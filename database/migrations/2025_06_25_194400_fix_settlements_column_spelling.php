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
        if (! Schema::hasTable('settlements')) {
            return;
        }

        // Original create migration used typo `total_recieved`; rename before touching `total_received`.
        if (Schema::hasColumn('settlements', 'total_recieved') && ! Schema::hasColumn('settlements', 'total_received')) {
            DB::statement('ALTER TABLE settlements CHANGE total_recieved total_received VARCHAR(255) NULL');
        }

        if (! Schema::hasColumn('settlements', 'total_received')) {
            return;
        }

        // First, clean up any invalid data
        DB::statement("UPDATE settlements SET total_received = '0' WHERE total_received IS NULL OR total_received = '' OR total_received = 'null'");
        DB::statement("UPDATE settlements SET outstanding = '0' WHERE outstanding IS NULL OR outstanding = '' OR outstanding = 'null'");

        // Convert string values to numeric format (remove currency formatting)
        DB::statement("UPDATE settlements SET total_received = REGEXP_REPLACE(REPLACE(REPLACE(total_received, 'Rp', ''), '.', ''), '[^0-9]', '')");
        DB::statement("UPDATE settlements SET outstanding = REGEXP_REPLACE(REPLACE(REPLACE(outstanding, 'Rp', ''), '.', ''), '[^0-9]', '')");

        // Ensure we have valid numeric values
        DB::statement("UPDATE settlements SET total_received = '0' WHERE total_received = '' OR total_received IS NULL");
        DB::statement("UPDATE settlements SET outstanding = '0' WHERE outstanding = '' OR outstanding IS NULL");

        Schema::table('settlements', function (Blueprint $table) {
            // Change data types to decimal for proper numeric calculations
            $table->decimal('total_received', 15, 2)->nullable()->change();
            $table->decimal('outstanding', 15, 2)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('settlements', function (Blueprint $table) {
            // Change back to string
            $table->string('total_received')->nullable()->change();
            $table->string('outstanding')->nullable()->change();
        });
    }
};

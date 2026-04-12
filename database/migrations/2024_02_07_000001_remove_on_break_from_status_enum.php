<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('attendances')) {
            return;
        }

        // Update any existing 'on_break' status to 'checked_in'
        DB::table('attendances')
            ->where('status', 'on_break')
            ->update(['status' => 'checked_in']);

        // Modify the enum to remove 'on_break'
        DB::statement("ALTER TABLE attendances MODIFY COLUMN status ENUM('checked_in', 'checked_out') NOT NULL DEFAULT 'checked_in'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('attendances')) {
            return;
        }

        // Add back the 'on_break' option to the enum
        DB::statement("ALTER TABLE attendances MODIFY COLUMN status ENUM('checked_in', 'checked_out', 'on_break') NOT NULL DEFAULT 'checked_in'");
    }
};

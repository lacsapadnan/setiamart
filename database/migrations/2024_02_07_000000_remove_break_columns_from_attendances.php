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
        if (! Schema::hasTable('attendances')) {
            return;
        }

        $columns = collect(['break_start', 'break_end'])->filter(function (string $column) {
            return Schema::hasColumn('attendances', $column);
        })->values()->all();

        if ($columns === []) {
            return;
        }

        Schema::table('attendances', function (Blueprint $table) use ($columns) {
            $table->dropColumn($columns);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('attendances')) {
            return;
        }

        if (! Schema::hasColumn('attendances', 'break_start')) {
            Schema::table('attendances', function (Blueprint $table) {
                $table->string('break_start')->nullable();
            });
        }

        if (! Schema::hasColumn('attendances', 'break_end')) {
            Schema::table('attendances', function (Blueprint $table) {
                $table->string('break_end')->nullable();
            });
        }
    }
};

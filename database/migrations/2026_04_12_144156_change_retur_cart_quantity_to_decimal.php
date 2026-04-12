<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Retur cart and detail tables used integer columns, which truncated decimal quantities.
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE sell_retur_carts MODIFY quantity DECIMAL(12,2) NOT NULL');
        DB::statement('ALTER TABLE purchase_retur_carts MODIFY quantity DECIMAL(12,2) NOT NULL');
        DB::statement('ALTER TABLE sell_retur_details MODIFY qty DECIMAL(12,2) NOT NULL');
        DB::statement('ALTER TABLE purchase_retur_details MODIFY qty DECIMAL(12,2) NOT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE sell_retur_carts MODIFY quantity INT NOT NULL');
        DB::statement('ALTER TABLE purchase_retur_carts MODIFY quantity INT NOT NULL');
        DB::statement('ALTER TABLE sell_retur_details MODIFY qty INT NOT NULL');
        DB::statement('ALTER TABLE purchase_retur_details MODIFY qty INT NOT NULL');
    }
};

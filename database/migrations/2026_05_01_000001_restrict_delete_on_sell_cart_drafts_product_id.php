<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Prevent silent removal of draft cart rows when a product is deleted from master data.
     */
    public function up(): void
    {
        $this->dropProductForeignKeys();

        Schema::table('sell_cart_drafts', function (Blueprint $table) {
            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        $this->dropProductForeignKeys();

        Schema::table('sell_cart_drafts', function (Blueprint $table) {
            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->onDelete('cascade');
        });
    }

    private function dropProductForeignKeys(): void
    {
        $databaseName = DB::getDatabaseName();
        $constraints = DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('TABLE_SCHEMA', $databaseName)
            ->where('TABLE_NAME', 'sell_cart_drafts')
            ->where('COLUMN_NAME', 'product_id')
            ->whereNotNull('REFERENCED_TABLE_NAME')
            ->pluck('CONSTRAINT_NAME');

        foreach ($constraints as $constraintName) {
            DB::statement("ALTER TABLE `sell_cart_drafts` DROP FOREIGN KEY `{$constraintName}`");
        }
    }
};

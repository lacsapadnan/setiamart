<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Prevent silent removal of draft cart rows when a product is deleted from master data.
     */
    public function up(): void
    {
        Schema::table('sell_cart_drafts', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
        });

        Schema::table('sell_cart_drafts', function (Blueprint $table) {
            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sell_cart_drafts', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
        });

        Schema::table('sell_cart_drafts', function (Blueprint $table) {
            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->onDelete('cascade');
        });
    }
};

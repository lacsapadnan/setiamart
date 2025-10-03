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
        Schema::create('payment_settings', function (Blueprint $table) {
            $table->id();
            $table->string('in_town_account_number')->default('7285132827');
            $table->string('in_town_account_name')->default('Andreas Jati Perkasa');
            $table->string('out_of_town_account_number')->default('7285132827');
            $table->string('out_of_town_account_name')->default('Andreas Jati Perkasa');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_settings');
    }
};

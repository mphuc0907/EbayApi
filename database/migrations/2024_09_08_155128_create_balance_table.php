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
        Schema::create('balance', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id');
            $table->bigInteger('balance');
            $table->integer('id_wallet');
            $table->integer('topup');
            $table->integer('giftcode');
            $table->integer('balance_support');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('balance');
    }
};

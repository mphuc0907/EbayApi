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
        Schema::create('balance_log', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id');
            $table->integer('id_balance');
            $table->string('action_user', 50);
            $table->integer('last_balance', 50);
            $table->integer('current_balance', 50);
            $table->bigInteger('balance');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('balance_log');
    }
};

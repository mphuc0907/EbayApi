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
        Schema::create('kiosk_sub', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('kiosk_id');
            $table->string('name', 255);
            $table->float('price');
            $table->integer('status');
            $table->bigInteger('quantity');
            $table->integer('soft_unit');
            $table->text('soft_url');
            $table->float('soft_life_time_price');
            $table->text('post_back');
            $table->text('api_quantity');
            $table->text('api_data');
            $table->string('public_token', 100);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kiosk_sub');
    }
};

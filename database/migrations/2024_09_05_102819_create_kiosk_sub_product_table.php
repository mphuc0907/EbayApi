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
        Schema::create('kiosk_sub_product', function (Blueprint $table) {
            $table->id();
            $table->integer('kiosk_sub_id');
            $table->text('value');
            $table->integer('status');
            $table->string('namefile');
            $table->dateTime('sold_date');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kiosk_sub_product');
    }
};

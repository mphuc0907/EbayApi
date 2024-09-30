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
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->string('promotion_code', 255);
            $table->string('description', 255);
            $table->integer('amount');
            $table->integer('is_admin_created');
            $table->integer('max_amount');
            $table->integer('kiosk_id');
            $table->integer('sub_kiosk_id');
            $table->integer('type');
            $table->integer('total_for_using');
            $table->string('percent', 255);
            $table->dateTime('start_date');
            $table->dateTime('end_date');
            $table->integer('created_user_id');
            $table->integer('status');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promotions');
    }
};

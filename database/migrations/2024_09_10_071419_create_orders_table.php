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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_code')->unique();
            $table->integer('user_id')->nullable();
            $table->dateTime('accept_date');
            $table->string('kiosk_sub_name')->nullable();
            $table->integer('kiosk_sub_id')->nullable();
            $table->integer('ref_user_id');
            $table->integer('price')->nullable();
            $table->integer('total_price')->nullable();
            $table->tinyInteger('is_api');
            $table->string('code_vorcher');
            $table->tinyInteger('is_ratting');
            $table->integer('promotion_id');
            $table->integer('soft_use_time');
            $table->integer('service_waitingdays');
            $table->string('service_product', 255);
            $table->integer('quality');
            $table->integer('kiosk_id');
            $table->dateTime('finish_date');
            $table->integer('sale_percent');
            $table->integer('reduce_amount');
            $table->integer('admin_amount');
            $table->integer('reseller_amount');
            $table->integer('status');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};

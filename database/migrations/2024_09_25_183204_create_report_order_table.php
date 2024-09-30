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
        Schema::create('report_order', function (Blueprint $table) {
            $table->id();
            $table->integer('order_code')->unique();
            $table->integer('id_order');
            $table->integer('id_kiot');
            $table->integer('id_user');
            $table->string('name_user');
            $table->string('reason');
            $table->integer('status');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('_report_order');
    }
};

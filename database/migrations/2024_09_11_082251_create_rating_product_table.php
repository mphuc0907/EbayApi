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
        Schema::create('rating_product', function (Blueprint $table) {
            $table->id();
            $table->text('comment')->nullable();
            $table->text('imageGallery');
            $table->integer('id_product');
            $table->integer('star');
            $table->integer('kiosk_id')->nullable();
            $table->integer('order_id')->nullable();
            $table->string('reply');
            $table->string('name_user');
            $table->string('avatar_user');
            $table->string('email');
            $table->integer('status');
            $table->integer('user_id');
            $table->integer('total_like', 20);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rating_product');
    }
};

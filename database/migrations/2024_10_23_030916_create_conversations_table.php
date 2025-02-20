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
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_user1');
            $table->unsignedBigInteger('id_user2');
            $table->unsignedBigInteger('last_mess_id')->nullable(); // Tin nhắn cuối cùng
            $table->timestamps();

            $table->foreign('id_user1')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('id_user2')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};

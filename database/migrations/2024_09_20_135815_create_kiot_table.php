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
        Schema::create('kiot', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id');
            $table->integer('category_parent_id');
            $table->integer('category_id');
            $table->integer('category_sub_id');
            $table->float('refund_person')->default(0);
            $table->integer('allow_reseller');
            $table->integer('is_private');
            $table->integer('is_duplicate');
            $table->string('short_des')->nullable();
            $table->text('description');
            $table->integer('status');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kiot');
    }
};

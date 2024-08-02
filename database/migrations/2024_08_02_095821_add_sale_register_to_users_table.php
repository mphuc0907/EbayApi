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
        Schema::table('users', function (Blueprint $table) {
            $table->integer('role')->default(1);
            $table->string('phone')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('front_id_card')->nullable();
            $table->string('back_id_card')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
            $table->dropColumn('phone');
            $table->dropColumn('bank_name');
            $table->dropColumn('front_id_card');
            $table->dropColumn('back_id_card');
        });
    }
};

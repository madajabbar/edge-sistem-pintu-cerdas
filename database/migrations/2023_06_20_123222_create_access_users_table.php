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
        Schema::create('access_users', function (Blueprint $table) {
            $table->integer('id')->primary();
            $table->integer('user_id');
            $table->integer('access_id');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onUpdate('CASCADE')->onDelete('CASCADE')->onDelete('CASCADE');
            $table->foreign('access_id')->references('id')->on('accesses')->onUpdate('CASCADE')->onDelete('CASCADE')->onDelete('CASCADE');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('access_users');
    }
};

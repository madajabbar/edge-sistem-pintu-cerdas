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
        Schema::create('accesses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamp('start_at');
            $table->timestamp('end_at');
            $table->string('unique_key');
            $table->string('slug');
            $table->unsignedBigInteger('room_id');
            $table->timestamps();

            $table->foreign('room_id')->references('id')->on('rooms')->onDelete('CASCADE')->onUpdate('CASCADE');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accesses');
    }
};

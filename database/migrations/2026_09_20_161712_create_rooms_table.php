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
        Schema::create('rooms', function (Blueprint $table) {
            $table->id('room_id');
            $table->char('room_code', 6)->unique(); // เพิ่ม unique
            $table->enum('room_status', ['waiting', 'day_discussion', 'day_voting', 'night', 'ended'])->default('waiting'); // ตรงตาม ENUM ใน DATABASE.md
            $table->timestamp('room_phase_end_time')->nullable();
            $table->enum('difficulty', ['easy', 'hard'])->default('easy');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};

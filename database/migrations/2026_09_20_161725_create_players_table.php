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
        Schema::create('players', function (Blueprint $table) {
            $table->id('player_id');
            $table->string('player_name', 45);
            $table->boolean('is_host')->default(false);
            $table->char('player_uuid', 36);
            $table->string('role')->nullable();
            $table->boolean('is_alive')->default(true);
            $table->boolean('is_connected')->default(true);
            
            // Foreign Key เชื่อมกับตาราง rooms
            $table->foreignId('rooms_room_id')->constrained('rooms', 'room_id')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('players');
    }
};

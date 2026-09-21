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
        Schema::create('votes_and_actions', function (Blueprint $table) {
            $table->id('vote_id');
            $table->integer('phase_number')->default(1);
            $table->enum('phase_type', ['day_discussion', 'day_voting', 'night']); // ไม่ใช่ nullable แล้ว ตาม DATABASE.md
            $table->enum('action_type', ['vote_lynch', 'werewolf_kill', 'seer_check']); // ตรงตาม ENUM ใน DATABASE.md
            
            // Foreign Keys
            $table->foreignId('rooms_room_id')->constrained('rooms', 'room_id')->onDelete('cascade');
            $table->foreignId('players_voter_id')->constrained('players', 'player_id')->onDelete('cascade');
            $table->foreignId('players_target_id')->constrained('players', 'player_id')->onDelete('cascade'); // ใน DATABASE.md กำหนด NOT NULL
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('votes_and_actions');
    }
};

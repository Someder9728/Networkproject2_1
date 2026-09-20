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
            $table->integer('phase_number');
            $table->string('phase_type')->nullable();
            $table->string('action_type')->nullable();

            // Foreign Keys
            $table->foreignId('rooms_room_id')->constrained('rooms', 'room_id')->onDelete('cascade');
            $table->foreignId('players_voter_id')->constrained('players', 'player_id')->onDelete('cascade');
            $table->foreignId('players_target_id')->nullable()->constrained('players', 'player_id')->onDelete('set null');
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

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
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id('chat_id');
            $table->enum('chat_channel', ['all', 'werewolf', 'dead'])->default('all');
            $table->text('message');

            // Foreign Keys
            $table->foreignId('rooms_room_id')->constrained('rooms', 'room_id')->onDelete('cascade');
            $table->foreignId('players_sender_id')->constrained('players', 'player_id')->onDelete('cascade');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
    }
};

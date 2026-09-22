<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->boolean('has_left')->default(false);

            $table->dropUnique(['player_uuid']);

            $table->unique(
                ['rooms_room_id', 'player_uuid'],
                'players_room_uuid_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->dropUnique('players_room_uuid_unique');
            $table->dropColumn('has_left');
            $table->unique('player_uuid');
        });
    }
};
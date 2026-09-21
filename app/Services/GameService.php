<?php

namespace App\Services;

use Illuminate\Support\Str;

class GameService
{
    public function buildSession(array $room): array
    {
        $players = array_map(
            fn (array $player) => [
                'player_uuid' => $player['player_uuid'],
                'name' => $player['name'],
                'is_alive' => true,
                'role' => null,
            ],
            $room['players']
        );

        return [
            'game_uuid' => (string) Str::uuid(),
            'room_code' => $room['code'],
            'difficulty' => $room['difficulty'],
            'status' => 'initializing',
            'current_phase' => null,
            'current_round' => 0,
            'players' => $players,
            'phase_end_time' => null,
            'winner' => null,
        ];
    }
}
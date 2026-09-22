<?php

namespace App\Services;

use App\GameLogic\RoleAssignment;
use Illuminate\Support\Str;
use App\GameLogic\DifficultyConfig;

class GameService
{
    public function buildSession(array $room): array
    {
        $playerCount = count($room['players']);

        $config = DifficultyConfig::get(
            $playerCount,
            $room['difficulty']
        );

        $werewolfCount = $config['werewolf_count'];

        $playerUuids = array_column(
            $room['players'],
            'player_uuid'
        );

        $roles = RoleAssignment::assignRoles(
            $playerUuids,
            $werewolfCount
        );

        $players = array_map(
            fn (array $player) => [
                'player_uuid' => $player['player_uuid'],
                'name' => $player['name'],
                'is_alive' => true,
                'role' => $roles[$player['player_uuid']],
            ],
            $room['players']
        );

        return [
            'game_uuid' => (string) Str::uuid(),
            'room_code' => $room['code'],
            'difficulty' => $room['difficulty'],
            'status' => 'roles_assigned',
            'current_phase' => null,
            'current_round' => 0,
            'players' => $players,
            'phase_end_time' => null,
            'winner' => null,
            'config' => $config,
        ];
    }
}
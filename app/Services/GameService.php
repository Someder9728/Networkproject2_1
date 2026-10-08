<?php

namespace App\Services;

use App\GameLogic\DifficultyConfig;
use App\GameLogic\RoleAssignment;
use Illuminate\Support\Str;

class GameService
{
    public function buildSession(array $room): array
    {
        $playerCount = count($room['players']);

        $config = DifficultyConfig::get(
            $playerCount,
            $room['difficulty']
        );

        $mode = $room['game_mode'] ?? 'normal';
        $config['night_sec'] = (int) config('game.night_seconds', 45);
        if ($mode === 'short') {
            $config['day_discussion_sec'] = max(1, (int) config('game.short_discussion_seconds', 30));
            $config['day_voting_sec'] = max(1, (int) config('game.short_voting_seconds', 20));
            $config['night_sec'] = max(1, (int) config('game.short_night_seconds', 20));
        }

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
                'is_connected' => true,
                'last_seen_at' => now()->toIso8601String(),
                'disconnected_at' => null,
                'reconnect_deadline' => null,
                'has_left' => false,
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
            'game_mode' => $mode,
            'max_rounds' => max(1, (int) config('game.short_max_rounds', 3)),
            'match_end_time' => null,
            'guardian_last_targets' => [],
            'evidence' => [],
            'night_rule' => null,
            'day_skip_votes' => [],
            'discussion_skip_votes' => [],
            'config' => $config,
            'seer_checks_used' => [],
            'seer_results' => [],
            'night_event' => null,
            'day_config' => $config,
            'day_event' => null,
        ];
    }
}

<?php

namespace App\GameLogic;

class GameConfiguration
{
    public static function create(
        array $playerIds,
        string $difficulty,
        int $eventChance = 30
    ): array {
        $playerCount = count($playerIds);

        // ดึงค่าการตั้งค่าตามจำนวนผู้เล่นและระดับความยาก
        $settings = DifficultyConfig::get(
            $playerCount,
            $difficulty
        );

        // สุ่ม Role โดยใช้กติกาจาก RoleAssignment ของเพื่อนคนที่ 3
        $roles = RoleAssignment::assignRoles(
            $playerIds,
            $settings['werewolf_count']
        );

        // สุ่ม Random Event
        $randomEvent = RandomEvent::random(
            $playerCount,
            $difficulty,
            $eventChance
        );

        return [
            'player_count' => $playerCount,
            'difficulty' => strtolower($difficulty),
            'settings' => $settings,
            'roles' => $roles,
            'random_event' => $randomEvent,
        ];
    }
}
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

        $effectiveSettings = self::applyRandomEvent(
            $settings,
            $randomEvent
        );

        return [
            'player_count' => $playerCount,
            'difficulty' => strtolower($difficulty),
            'settings' => $settings,
            'effective_settings' => $effectiveSettings,
            'roles' => $roles,
            'random_event' => $randomEvent,
        ];
    }

    public static function applyRandomEvent(
        array $settings,
        array $randomEvent
    ): array {
        $effectiveSettings = $settings;

        if (($randomEvent['id'] ?? 'none') === 'none') {
            return $effectiveSettings;
        }

        $effect = $randomEvent['effect'] ?? [];

        if (isset($effect['discussion_time_change'])) {
            $effectiveSettings['day_discussion_sec'] +=
                $effect['discussion_time_change'];
        }

        if (isset($effect['voting_time_change'])) {
            $effectiveSettings['day_voting_sec'] +=
                $effect['voting_time_change'];
        }

        if (isset($effect['tie_breaking'])) {
            $effectiveSettings['tie_breaking'] =
                $effect['tie_breaking'];
        }

        return $effectiveSettings;
    }
}
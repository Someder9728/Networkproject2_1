<?php

namespace App\GameLogic;

class DifficultyConfig
{
    public const EASY = 'easy';

    public const HARD = 'hard';

    public static function supportedPlayerCounts(): array
    {
        return [4, 6];
    }

    protected static function table(): array
    {
        return [
            4 => [
                self::EASY => [
                    'werewolf_count' => 1,
                    'day_discussion_sec' => 15,
                    // fix time for debug 120,45 -> 15
                    'day_voting_sec' => 15,
                    'seer_checks_limit' => null,
                    'seer_accuracy' => true,
                    'reveal_role_on_death' => true,
                    'tie_breaking' => 'no_death',
                ],
                self::HARD => [
                    'werewolf_count' => 1,
                    'day_discussion_sec' => 60,
                    'day_voting_sec' => 30,
                    'seer_checks_limit' => 3,
                    'seer_accuracy' => true,
                    'reveal_role_on_death' => false,
                    'tie_breaking' => 'revote_or_random',
                ],
            ],
            6 => [
                self::EASY => [
                    'werewolf_count' => 1,
                    'day_discussion_sec' => 120,
                    'day_voting_sec' => 45,
                    'seer_checks_limit' => null,
                    'seer_accuracy' => true,
                    'reveal_role_on_death' => true,
                    'tie_breaking' => 'no_death',
                ],
                self::HARD => [
                    'werewolf_count' => 2,
                    'day_discussion_sec' => 60,
                    'day_voting_sec' => 30,
                    'seer_checks_limit' => 3,
                    'seer_accuracy' => true,
                    'reveal_role_on_death' => false,
                    'tie_breaking' => 'revote_or_random',
                ],
            ],
        ];
    }

    public static function get(int $playerCount, string $difficulty): array
    {
        $difficulty = strtolower($difficulty);
        $table = self::table();

        if (! isset($table[$playerCount])) {
            throw new \InvalidArgumentException(
                "ยังไม่รองรับจำนวนผู้เล่น {$playerCount} คน (รองรับแค่ ".
                implode(', ', self::supportedPlayerCounts()).')'
            );
        }

        if (! in_array($difficulty, [self::EASY, self::HARD], true)) {
            throw new \InvalidArgumentException(
                "ความยากไม่ถูกต้อง: {$difficulty} (ต้องเป็น 'easy' หรือ 'hard')"
            );
        }

        return $table[$playerCount][$difficulty];
    }

    public static function isSupported(int $playerCount, string $difficulty): bool
    {
        $difficulty = strtolower($difficulty);

        return isset(self::table()[$playerCount][$difficulty]);
    }
}
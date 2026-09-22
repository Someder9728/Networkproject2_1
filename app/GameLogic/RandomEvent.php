<?php

namespace App\GameLogic;

class RandomEvent
{
    public const EVENT_NONE = 'none';

    // Easy Events
    public const EVENT_EXTRA_DISCUSSION = 'extra_discussion';
    public const EVENT_EXTRA_VOTING_TIME = 'extra_voting_time';
    public const EVENT_SECOND_CHANCE = 'second_chance';

    // Hard Events
    public const EVENT_SHORT_DISCUSSION = 'short_discussion';
    public const EVENT_SHORT_VOTING_TIME = 'short_voting_time';
    public const EVENT_RANDOM_TIE = 'random_tie';

    protected static function events(): array
    {
        return [
            DifficultyConfig::EASY => [
                self::EVENT_EXTRA_DISCUSSION => [
                    'name' => 'เวลาอภิปรายเพิ่ม',
                    'description' => 'เพิ่มเวลาพูดคุยอีก 30 วินาที',
                    'phase' => 'day',
                    'effect' => [
                        'discussion_time_change' => 30,
                    ],
                ],

                self::EVENT_EXTRA_VOTING_TIME => [
                    'name' => 'เวลาลงคะแนนเพิ่ม',
                    'description' => 'เพิ่มเวลาโหวตอีก 15 วินาที',
                    'phase' => 'day',
                    'effect' => [
                        'voting_time_change' => 15,
                    ],
                ],

                self::EVENT_SECOND_CHANCE => [
                    'name' => 'โอกาสลงคะแนนใหม่',
                    'description' => 'หากผลโหวตเสมอ สามารถลงคะแนนใหม่ได้ 1 ครั้ง',
                    'phase' => 'day',
                    'effect' => [
                        'tie_breaking' => 'revote_once',
                    ],
                ],
            ],

            DifficultyConfig::HARD => [
                self::EVENT_SHORT_DISCUSSION => [
                    'name' => 'เวลาอภิปรายจำกัด',
                    'description' => 'ลดเวลาพูดคุยลง 30 วินาที',
                    'phase' => 'day',
                    'effect' => [
                        'discussion_time_change' => -30,
                    ],
                ],

                self::EVENT_SHORT_VOTING_TIME => [
                    'name' => 'เวลาลงคะแนนจำกัด',
                    'description' => 'ลดเวลาโหวตลง 15 วินาที',
                    'phase' => 'day',
                    'effect' => [
                        'voting_time_change' => -15,
                    ],
                ],

                self::EVENT_RANDOM_TIE => [
                    'name' => 'ตัดสินผลโหวตแบบสุ่ม',
                    'description' => 'หากผลโหวตเสมอ ระบบจะสุ่มผู้เล่นจากกลุ่มที่ได้คะแนนสูงสุด',
                    'phase' => 'day',
                    'effect' => [
                        'tie_breaking' => 'random',
                    ],
                ],
            ],
        ];
    }

    public static function random(
        int $playerCount,
        string $difficulty,
        int $chance = 30
    ): array {
        $difficulty = strtolower($difficulty);

        if (! DifficultyConfig::isSupported($playerCount, $difficulty)) {
            throw new \InvalidArgumentException(
                "ไม่รองรับ {$playerCount} คน ในระดับ {$difficulty}"
            );
        }

        if ($chance < 0 || $chance > 100) {
            throw new \InvalidArgumentException(
                'โอกาสเกิด Event ต้องอยู่ระหว่าง 0 ถึง 100'
            );
        }

        if (random_int(1, 100) > $chance) {
            return self::none($playerCount, $difficulty);
        }

        $events = self::events()[$difficulty];
        $eventId = array_rand($events);

        return [
            'id' => $eventId,
            'player_count' => $playerCount,
            'difficulty' => $difficulty,
            ...$events[$eventId],
        ];
    }

    public static function none(
        int $playerCount,
        string $difficulty
    ): array {
        return [
            'id' => self::EVENT_NONE,
            'player_count' => $playerCount,
            'difficulty' => strtolower($difficulty),
            'name' => 'ไม่มี Event',
            'description' => 'รอบนี้ไม่มี Random Event',
            'phase' => null,
            'effect' => [],
        ];
    }

    public static function exists(
        string $eventId,
        string $difficulty
    ): bool {
        $difficulty = strtolower($difficulty);

        if (! isset(self::events()[$difficulty])) {
            return false;
        }

        return isset(self::events()[$difficulty][$eventId]);
    }

    public static function get(
        string $eventId,
        string $difficulty,
        int $playerCount = 4
    ): array {
        $difficulty = strtolower($difficulty);

        if (! DifficultyConfig::isSupported($playerCount, $difficulty)) {
            throw new \InvalidArgumentException(
                "ไม่รองรับ {$playerCount} คน ในระดับ {$difficulty}"
            );
        }

        if ($eventId === self::EVENT_NONE) {
            return self::none($playerCount, $difficulty);
        }

        if (! self::exists($eventId, $difficulty)) {
            throw new \InvalidArgumentException(
                "ไม่พบ Event {$eventId} ในระดับ {$difficulty}"
            );
        }

        return [
            'id' => $eventId,
            'player_count' => $playerCount,
            'difficulty' => $difficulty,
            ...self::events()[$difficulty][$eventId],
        ];
    }

    public static function isForPhase(
        string $eventId,
        string $difficulty,
        string $phase,
        int $playerCount = 4
    ): bool {
        $event = self::get(
            $eventId,
            $difficulty,
            $playerCount
        );

        return $event['phase'] === strtolower($phase);
    }

    public static function all(
        string $difficulty,
        int $playerCount = 4
    ): array {
        $difficulty = strtolower($difficulty);

        if (! DifficultyConfig::isSupported($playerCount, $difficulty)) {
            throw new \InvalidArgumentException(
                "ไม่รองรับ {$playerCount} คน ในระดับ {$difficulty}"
            );
        }

        $result = [];

        foreach (self::events()[$difficulty] as $id => $event) {
            $result[] = [
                'id' => $id,
                'player_count' => $playerCount,
                'difficulty' => $difficulty,
                ...$event,
            ];
        }

        return $result;
    }
}
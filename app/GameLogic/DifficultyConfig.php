<?php

namespace App\GameLogic;

/**
 * DifficultyConfig
 *
 * เก็บค่า config ความยาก/ง่ายของเกม Werewolf ตามจำนวนผู้เล่น
 * เป็น pure logic ล้วนๆ ไม่พึ่งฐานข้อมูลหรือ service อื่นใด
 * สามารถเรียกใช้และเขียน unit test ได้ทันที
 *
 * ขอบเขตปัจจุบัน: รองรับเฉพาะผู้เล่น 4 และ 6 คน
 * (จำนวนอื่นยังไม่ใส่ ตัดออกตามที่ตกลงกันไว้ก่อน)
 *
 * ครอบคลุมกฎ 4 ข้อ (ตัดข้อ 5 เรื่อง self-vote ออกตามที่ตกลง):
 * 1. เวลาพูดคุย / เวลาโหวต
 * 2. ความแม่นยำ/ขอบเขตการตรวจของ Seer
 * 3. การเปิดเผย role เมื่อตาย
 * 4. กฎการโหวตเสมอ (tie-breaking)
 */
class DifficultyConfig
{
    public const EASY = 'easy';

    public const HARD = 'hard';

    /**
     * รายชื่อจำนวนผู้เล่นที่รองรับตอนนี้
     */
    public static function supportedPlayerCounts(): array
    {
        return [4, 6];
    }

    /**
     * ตารางค่า config ทั้งหมด
     *
     * werewolf_count      : จำนวนหมาป่าที่ตั้งไว้ในโหมดนี้
     * day_discussion_sec  : เวลาช่วง Day Discussion (วินาที)
     * day_voting_sec      : เวลาช่วง Day Voting (วินาที)
     * seer_checks_limit   : null = ตรวจได้ไม่จำกัด (ทุกคืน) / int = จำกัดจำนวนครั้งตลอดเกม
     * seer_accuracy       : true = บอกผลชัดเจน 100%
     * reveal_role_on_death: true = ประกาศ role คนตายทันที / false = ไม่เปิดเผย
     * tie_breaking        : 'no_death' = เสมอแล้วไม่มีใครตาย
     *                       'revote_or_random' = เสมอแล้วโหวตรอบสอง หรือสุ่มจากคนที่เสมอ
     */
    protected static function table(): array
    {
        return [
            4 => [
                self::EASY => [
                    'werewolf_count' => 1, // สมมติฐาน: ไม่มีในสเปกต้นฉบับ ใช้ค่าต่ำสุดเพื่อความบาลานซ์
                    'day_discussion_sec' => 120,
                    'day_voting_sec' => 45,
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

    /**
     * ดึง config ตามจำนวนผู้เล่น + ความยาก
     *
     * @throws \InvalidArgumentException ถ้าจำนวนผู้เล่นหรือความยากไม่รองรับ
     */
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

    /**
     * เช็คว่ารองรับ combo (จำนวนผู้เล่น + ความยาก) นี้ไหม
     */
    public static function isSupported(int $playerCount, string $difficulty): bool
    {
        $difficulty = strtolower($difficulty);

        return isset(self::table()[$playerCount][$difficulty]);
    }
}

<?php

namespace App\GameLogic;

/**
 * RoleAssignment
 *
 * สุ่มแจก role (Werewolf / Seer / Villager) ให้ผู้เล่น
 * เป็น pure logic ล้วนๆ ไม่พึ่งฐานข้อมูลหรือ service อื่นใด
 * ใช้ค่า werewolf_count จาก DifficultyConfig เป็นตัวกำหนดจำนวน Werewolf
 *
 * กติกาที่ใช้ (จากไฟล์สเปก):
 * - Werewolf: จำนวนตาม DifficultyConfig
 * - Seer: 1 คนเสมอ
 * - Villager: ผู้เล่นที่เหลือทั้งหมด
 */
class RoleAssignment
{
    public const WEREWOLF = 'werewolf';

    public const SEER = 'seer';

    public const VILLAGER = 'villager';

    /**
     * สุ่มแจก role ให้ผู้เล่น
     *
     * @param  array<int|string, mixed>  $players  list ของผู้เล่น เช่น ['P1', 'P2', 'P3', 'P4', 'P5', 'P6']
     *                                             หรือจะเป็น id ก็ได้ (int/string) ไม่จำกัดรูปแบบ
     * @param  string  $difficulty  'easy' หรือ 'hard'
     * @return array<int|string, string> map ผู้เล่น => role เช่น ['P1' => 'werewolf', 'P2' => 'seer', ...]
     *
     * @throws \InvalidArgumentException ถ้าจำนวนผู้เล่นไม่ตรงกับที่ DifficultyConfig รองรับ
     */
    public static function assign(array $players, string $difficulty): array
    {
        $playerCount = count($players);

        // ใช้ DifficultyConfig ตัวเดิมเช็คว่ารองรับจำนวนผู้เล่นนี้ไหม
        // และดึงจำนวน Werewolf ที่ต้องใช้ออกมา
        $config = DifficultyConfig::get($playerCount, $difficulty);
        $werewolfCount = $config['werewolf_count'];

        // กัน edge case: Werewolf + Seer ต้องไม่เกินจำนวนผู้เล่นทั้งหมด
        if ($werewolfCount + 1 > $playerCount) {
            throw new \InvalidArgumentException(
                "จำนวน Werewolf ({$werewolfCount}) + Seer (1) เกินจำนวนผู้เล่นทั้งหมด ({$playerCount})"
            );
        }

        // สลับลำดับผู้เล่นแบบสุ่ม แล้วค่อยหยิบจากหัวแถวไปแจก role ทีละกลุ่ม
        $shuffled = $players;
        shuffle($shuffled);

        $roles = [];

        // หยิบกลุ่มแรกเป็น Werewolf
        for ($i = 0; $i < $werewolfCount; $i++) {
            $roles[$shuffled[$i]] = self::WEREWOLF;
        }

        // คนถัดมา 1 คนเป็น Seer
        $roles[$shuffled[$werewolfCount]] = self::SEER;

        // ที่เหลือทั้งหมดเป็น Villager
        for ($i = $werewolfCount + 1; $i < $playerCount; $i++) {
            $roles[$shuffled[$i]] = self::VILLAGER;
        }

        return $roles;
    }

    /**
     * Helper: จัดกลุ่มผู้เล่นตาม role ที่แจกไปแล้ว
     * เช่น เอาไว้ให้ Werewolf ด้วยกันเห็นว่าใครเป็นทีมเดียวกันบ้าง (ตามกฎ "การมองเห็นข้อมูล")
     *
     * @param  array<int|string, string>  $assignedRoles  ผลลัพธ์จาก assign()
     * @return array<string, array<int, mixed>> เช่น ['werewolf' => [...], 'seer' => [...], 'villager' => [...]]
     */
    public static function groupByRole(array $assignedRoles): array
    {
        $groups = [
            self::WEREWOLF => [],
            self::SEER => [],
            self::VILLAGER => [],
        ];

        foreach ($assignedRoles as $player => $role) {
            $groups[$role][] = $player;
        }

        return $groups;
    }
}

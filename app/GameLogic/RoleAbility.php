<?php

namespace App\GameLogic;

/**
 * RoleAssignment
 *
 * สุ่มแจก role (Werewolf / Seer / Villager) ให้ผู้เล่น เป็น pure logic ล้วนๆ
 * ไม่พึ่งฐานข้อมูลหรือ service อื่นใด ใช้ค่า werewolf_count จาก DifficultyConfig
 * เป็นตัวกำหนดจำนวน Werewolf
 *
 * กติกาที่ใช้ (จากไฟล์สเปก):
 * - Werewolf: จำนวนตาม DifficultyConfig
 * - Seer: 1 คนเสมอ
 * - Villager: ที่เหลือทั้งหมด
 *
 * ผลลัพธ์จาก assign() คือ rolesMap รูปแบบ ['P1' => 'werewolf', 'P2' => 'seer', ...]
 * ซึ่งสามารถส่งต่อให้ RoleAbility::buildPlayerState() เพื่อสร้าง player state เต็มรูปแบบได้ทันที
 */
class RoleAssignment
{
    // ชื่อ role ที่ใช้ในระบบ (RoleAbility.php อ้างอิง constant เหล่านี้)
    public const WEREWOLF = 'werewolf';
    public const SEER     = 'seer';
    public const VILLAGER = 'villager';

    /**
     * สุ่มแจก role ให้ผู้เล่นทั้งหมด
     *
     * @param array<int, string> $playerIds     รายชื่อ/ไอดีผู้เล่นทั้งหมด เช่น ['P1','P2','P3',...]
     * @param int                $werewolfCount จำนวน Werewolf ที่ต้องการ (ปกติมาจาก DifficultyConfig)
     * @return array<string, string> rolesMap เช่น ['P1' => 'werewolf', 'P2' => 'seer', 'P3' => 'villager']
     *
     * @throws \InvalidArgumentException ถ้าจำนวนผู้เล่นไม่พอสำหรับกติกา (ต้องมี Werewolf + Seer อย่างน้อย)
     */
    public static function assign(array $playerIds, int $werewolfCount): array
    {
        $playerCount = count($playerIds);

        if ($playerCount < 1) {
            throw new \InvalidArgumentException("ต้องมีผู้เล่นอย่างน้อย 1 คน");
        }

        if ($werewolfCount < 1) {
            throw new \InvalidArgumentException("จำนวน Werewolf ต้องมีอย่างน้อย 1 คน");
        }

        // ต้องเหลือที่ให้ Seer อย่างน้อย 1 คนเสมอตามสเปก
        if ($werewolfCount + 1 > $playerCount) {
            throw new \InvalidArgumentException(
                "จำนวนผู้เล่น ({$playerCount}) ไม่พอสำหรับ Werewolf {$werewolfCount} คน + Seer 1 คน"
            );
        }

        // กันเคสมี player id ซ้ำ ซึ่งจะทำให้แจก role ผิดพลาด
        if (count(array_unique($playerIds)) !== $playerCount) {
            throw new \InvalidArgumentException("มี player id ซ้ำกันใน \$playerIds");
        }

        $shuffled = $playerIds;
        shuffle($shuffled);

        $roles = [];

        // แจก Werewolf ตามจำนวนที่กำหนด
        for ($i = 0; $i < $werewolfCount; $i++) {
            $roles[$shuffled[$i]] = self::WEREWOLF;
        }

        // แจก Seer 1 คนเสมอ (คนถัดจาก Werewolf ในลิสต์ที่สับแล้ว)
        $roles[$shuffled[$werewolfCount]] = self::SEER;

        // ที่เหลือทั้งหมดเป็น Villager
        for ($i = $werewolfCount + 1; $i < $playerCount; $i++) {
            $roles[$shuffled[$i]] = self::VILLAGER;
        }

        return $roles;
    }

    /**
     * แจก role โดยรับ DifficultyConfig object ตรงๆ (สะดวกใช้จากภายนอก)
     * รองรับกรณีที่ DifficultyConfig มี method getWerewolfCount(): int
     *
     * @param array<int, string> $playerIds
     * @param object             $difficultyConfig ต้องมี method getWerewolfCount(): int
     * @return array<string, string>
     *
     * @throws \InvalidArgumentException ถ้า $difficultyConfig ไม่มี method getWerewolfCount()
     */
    public static function assignWithConfig(array $playerIds, object $difficultyConfig): array
    {
        if (!method_exists($difficultyConfig, 'getWerewolfCount')) {
            throw new \InvalidArgumentException(
                "DifficultyConfig ต้องมี method getWerewolfCount()"
            );
        }

        return self::assign($playerIds, $difficultyConfig->getWerewolfCount());
    }
}
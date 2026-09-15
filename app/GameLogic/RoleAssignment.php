<?php

namespace App\GameLogic;

class RoleAssignment
{
    public const ROLE_WEREWOLF = 'werewolf';
    public const ROLE_VILLAGER = 'villager';
    public const ROLE_SEER = 'seer';

    public const TEAM_WEREWOLF = 'werewolf';
    public const TEAM_VILLAGER = 'villager';

    // เช็คทีมของ role
    public static function getTeamByRole(string $role): string
    {
        return match ($role) {
            self::ROLE_WEREWOLF => self::TEAM_WEREWOLF,
            self::ROLE_VILLAGER, self::ROLE_SEER => self::TEAM_VILLAGER,
            default => throw new \InvalidArgumentException("ไม่รู้จักบทบาท: {$role}"),
        };
    }

    // สุ่มแจก role ให้คนในห้อง
    public static function assignRoles(array $playerIds, int $werewolfCount): array
    {
        $totalPlayers = count($playerIds);

        if (! in_array($totalPlayers, DifficultyConfig::supportedPlayerCounts(), true)) {
            throw new \InvalidArgumentException("รองรับแค่ 4 หรือ 6 คนเท่านั้น");
        }

        if ($totalPlayers < $werewolfCount + 1) {
            throw new \InvalidArgumentException("ผู้เล่นไม่พอใส่ role");
        }

        // ยัด role ลง pool (มี Seer 1 ตัว เสมอ)
        $rolesPool = [];

        for ($i = 0; $i < $werewolfCount; $i++) {
            $rolesPool[] = self::ROLE_WEREWOLF;
        }

        $rolesPool[] = self::ROLE_SEER;

        $remainingVillagers = $totalPlayers - count($rolesPool);
        for ($i = 0; $i < $remainingVillagers; $i++) {
            $rolesPool[] = self::ROLE_VILLAGER;
        }

        // สุ่มสลับตำแหน่ง
        shuffle($rolesPool);

        // จับคู่กับ id คนเล่น
        $assigned = [];
        foreach ($playerIds as $index => $playerId) {
            $assigned[$playerId] = $rolesPool[$index];
        }

        return $assigned;
    }
}
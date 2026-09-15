<?php

namespace App\GameLogic;

class RoleAbility
{
    // เช็คว่า Seer ตรวจได้ไหม
    public static function canSeerCheck(
        bool $actorIsAlive,
        bool $targetIsAlive,
        int $actorCurrentChecks,
        ?int $seerChecksLimit
    ): bool {
        if (! $actorIsAlive || ! $targetIsAlive) {
            return false;
        }

        // โหมด Hard จะจำกัดจำนวนครั้ง[cite: 1]
        if ($seerChecksLimit !== null && $actorCurrentChecks >= $seerChecksLimit) {
            return false;
        }

        return true;
    }

    // เช็คว่า หมาป่า เลือกยิงเป้าหมายนี้ได้ไหม
    public static function canWerewolfKill(
        bool $actorIsAlive,
        bool $targetIsAlive,
        string $targetRole
    ): bool {
        if (! $actorIsAlive || ! $targetIsAlive) {
            return false;
        }

        // ห้ามยิงหมาป่าด้วยกัน[cite: 1]
        if ($targetRole === RoleAssignment::ROLE_WEREWOLF) {
            return false;
        }

        return true;
    }

    // ผลตรวจ Seer (เป็นหมา = true)[cite: 1]
    public static function resolveSeerCheck(string $targetRole): bool
    {
        return $targetRole === RoleAssignment::ROLE_WEREWOLF;
    }

    // สรุปผลหมาป่ายิงตอนกลางคืน
    public static function resolveNightKill(array $werewolfVotes): ?string
    {
        if (empty($werewolfVotes)) {
            return null;
        }

        $voteCounts = array_count_values($werewolfVotes);
        arsort($voteCounts);

        $maxVotes = max($voteCounts);
        $topTargets = array_keys($voteCounts, $maxVotes);

        // โหวตเสมอ = คืนนี้ไม่มีใครตาย[cite: 1]
        if (count($topTargets) > 1) {
            return null;
        }

        return $topTargets[0];
    }

    // สรุปผลโหวตแขวนคอตอนกลางวัน
    public static function resolveDayVote(array $votes, string $tieBreakingRule = 'no_death'): ?string
    {
        if (empty($votes)) {
            return null;
        }

        $voteCounts = array_count_values($votes);
        arsort($voteCounts);

        $maxVotes = max($voteCounts);
        $topTargets = array_keys($voteCounts, $maxVotes);

        // จัดการกรณีโหวตเสมอ
        if (count($topTargets) > 1) {
            if ($tieBreakingRule === 'no_death') {
                return null;
            }

            return $topTargets[array_rand($topTargets)]; // สุ่มคนตาย[cite: 1]
        }

        return $topTargets[0];
    }

    // เช็คผล แพ้/ชนะ
    public static function checkWinCondition(array $playersData): ?string
    {
        $aliveWerewolves = 0;
        $aliveVillagers = 0;

        foreach ($playersData as $player) {
            if (! $player['is_alive']) {
                continue;
            }

            $team = RoleAssignment::getTeamByRole($player['role']);

            if ($team === RoleAssignment::TEAM_WEREWOLF) {
                $aliveWerewolves++;
            } else {
                $aliveVillagers++;
            }
        }

        // หมาตายหมด = Villager ชนะ[cite: 1]
        if ($aliveWerewolves === 0) {
            return RoleAssignment::TEAM_VILLAGER;
        }

        // หมาเยอะกว่าหรือเท่ากับชาวบ้าน = Werewolf ชนะ[cite: 1]
        if ($aliveWerewolves >= $aliveVillagers) {
            return RoleAssignment::TEAM_WEREWOLF;
        }

        return null; // เกมยังไม่จบ
    }
}
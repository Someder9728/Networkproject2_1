<?php

namespace App\GameLogic;

class ActionQueue
{
    private array $nightActions = [];
    private array $dayVotes = [];

    // ล้าง Queue เมื่อเริ่ม Phase ใหม่
    public function clear(): void
    {
        $this->nightActions = [];
        $this->dayVotes = [];
    }

    // บันทึกคำสั่งของ Seer หรือ Werewolf ตอนกลางคืน
    public function addNightAction(string $actorId, string $actorRole, string $targetId): bool
    {
        // บันทึก action ล่าสุดของผู้เล่น (ส่งซ้ำจะทับของเดิม)
        $this->nightActions[$actorId] = [
            'role' => $actorRole,
            'target_id' => $targetId,
            'timestamp' => microtime(true)
        ];
        return true;
    }

    // บันทึกโหวตตอนกลางวัน
    public function addDayVote(string $voterId, string $targetId): void
    {
        $this->dayVotes[$voterId] = $targetId;
    }

    // ดึงโหวตของ Werewolf ทั้งหมดเพื่อนำไปคำนวณ
    public function getWerewolfVotes(): array
    {
        $votes = [];
        foreach ($this->nightActions as $actorId => $action) {
            if ($action['role'] === RoleAssignment::ROLE_WEREWOLF) {
                $votes[] = $action['target_id'];
            }
        }
        return $votes;
    }

    // ดึงโหวตแขวนคอตอนกลางวันทั้งหมด
    public function getDayVotes(): array
    {
        return $this->dayVotes;
    }
}
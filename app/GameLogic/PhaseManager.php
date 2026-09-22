<?php

namespace App\GameLogic;

class PhaseManager
{
    public const PHASE_DAY_DISCUSSION = 'day_discussion';
    public const PHASE_DAY_VOTING = 'day_voting';
    public const PHASE_NIGHT = 'night';
    public const PHASE_GAME_OVER = 'game_over';

    private string $currentPhase;
    private int $playerCount;
    private string $difficulty;
    private array $config;
    private ?int $phaseStartedAt = null;

    public function __construct(int $playerCount, string $difficulty)
    {
        $this->playerCount = $playerCount;
        $this->difficulty = $difficulty;
        $this->config = DifficultyConfig::get($playerCount, $difficulty);
        
        // เริ่มต้นเกมที่ Day Discussion
        $this->currentPhase = self::PHASE_DAY_DISCUSSION;
        $this->startPhase();
    }

    public function getCurrentPhase(): string
    {
        return $this->currentPhase;
    }

    // เริ่มนับเวลาของ Phase ปัจจุบันบน Server
    public function startPhase(): void
    {
        $this->phaseStartedAt = time();
    }

    // ดึงเวลาถอยหลัง (วินาที) ตาม Phase ปัจจุบัน
    public function getPhaseDuration(): int
    {
        return match ($this->currentPhase) {
            self::PHASE_DAY_DISCUSSION => $this->config['day_discussion_sec'],
            self::PHASE_DAY_VOTING => $this->config['day_voting_sec'],
            self::PHASE_NIGHT => 45, // เวลามาตรฐานของ Night Phase
            default => 0,
        };
    }

    // คืนค่าเวลาที่ Phase นี้จะจบลง (Unix Timestamp) สำหรับส่งให้ WebSocket Broadcast
    public function getPhaseEndsAt(): int
    {
        return ($this->phaseStartedAt ?? time()) + $this->getPhaseDuration();
    }

    // คืนค่าเวลาที่เหลืออยู่จริง (วินาที)
    public function getRemainingSeconds(): int
    {
        if ($this->phaseStartedAt === null) {
            return $this->getPhaseDuration();
        }
        $remaining = $this->getPhaseEndsAt() - time();
        return max(0, $remaining);
    }

    // สลับไปยัง Phase ถัดไปตาม Sequence
    public function nextPhase(): string
    {
        $this->currentPhase = match ($this->currentPhase) {
            self::PHASE_DAY_DISCUSSION => self::PHASE_DAY_VOTING,
            self::PHASE_DAY_VOTING => self::PHASE_NIGHT,
            self::PHASE_NIGHT => self::PHASE_DAY_DISCUSSION,
            default => self::PHASE_GAME_OVER,
        };

        $this->startPhase(); // เริ่มนับเวลาใหม่ทันทีที่เปลี่ยน Phase

        return $this->currentPhase;
    }

    // ประมวลผลเมื่อจบ Night Phase
    public function resolveNight(ActionQueue $queue, array &$playersData): array
    {
        // 1. สรุปผลหมาป่าฆ่าคน
        $killedTargetId = RoleAbility::resolveNightKill($queue->getWerewolfVotes());
        
        if ($killedTargetId && isset($playersData[$killedTargetId])) {
            $playersData[$killedTargetId]['is_alive'] = false;
        }

        // 2. เช็ค Win Condition หลังจบ Night
        $winner = RoleAbility::checkWinCondition($playersData);

        return [
            'killed_player_id' => $killedTargetId,
            'winner' => $winner
        ];
    }

    // ประมวลผลเมื่อจบ Day Voting Phase
    public function resolveDayVoting(ActionQueue $queue, array &$playersData): array
    {
        // 1. สรุปผลโหวตแขวนคอ
        $executedTargetId = RoleAbility::resolveDayVote(
            $queue->getDayVotes(),
            $this->config['tie_breaking'] ?? 'no_death'
        );

        if ($executedTargetId && isset($playersData[$executedTargetId])) {
            $playersData[$executedTargetId]['is_alive'] = false;
        }

        // 2. เช็ค Win Condition หลังจบ Day Voting
        $winner = RoleAbility::checkWinCondition($playersData);

        return [
            'executed_player_id' => $executedTargetId,
            'winner' => $winner
        ];
    }
}
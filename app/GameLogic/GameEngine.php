<?php

namespace App\GameLogic;

class GameEngine
{
    private PhaseManager $phaseManager;
    private ActionQueue $actionQueue;
    private array $players = []; // เก็บรายชื่อและสถานะผู้เล่นในห้อง
    private ?string $winner = null;

    public function __construct(array $playerIds, string $difficulty = DifficultyConfig::EASY)
    {
        $playerCount = count($playerIds);
        
        // 1. สุ่มแจกบทบาท
        $assignedRoles = RoleAssignment::assignRoles($playerIds, DifficultyConfig::get($playerCount, $difficulty)['werewolf_count']);

        // 2. ตั้งค่าผู้เล่นทุกคน
        foreach ($assignedRoles as $id => $role) {
            $this->players[$id] = [
                'id' => $id,
                'role' => $role,
                'is_alive' => true,
                'is_connected' => true,
                'seer_checks_count' => 0,
            ];
        }

        $this->phaseManager = new PhaseManager($playerCount, $difficulty);
        $this->actionQueue = new ActionQueue();
    }

    // ผู้เล่นส่ง Action เข้ามา (รองรับ WebSocket / API Route)
    public function handlePlayerAction(string $actorId, string $actionType, ?string $targetId = null): array
    {
        if (! isset($this->players[$actorId])) {
            return ['status' => 'error', 'message' => 'ไม่พบผู้เล่นนี้ในห้อง'];
        }

        $actor = $this->players[$actorId];
        $currentPhase = $this->phaseManager->getCurrentPhase();

        // Validate Server-side
        if (! $actor['is_alive']) {
            return ['status' => 'error', 'message' => 'ผู้เล่นที่ตายแล้วไม่สามารถส่ง action ได้'];
        }

        // กรณี Seer ตรวจสอบ
        if ($actionType === 'seer_check' && $currentPhase === PhaseManager::PHASE_NIGHT) {
            $target = $this->players[$targetId] ?? null;
            $config = DifficultyConfig::get(count($this->players), DifficultyConfig::EASY);

            if (! RoleAbility::canSeerCheck($actor['is_alive'], $target['is_alive'] ?? false, $actor['seer_checks_count'], $config['seer_checks_limit'])) {
                return ['status' => 'error', 'message' => 'ไม่สามารถตรวจสอบเป้าหมายนี้ได้'];
            }

            $this->players[$actorId]['seer_checks_count']++;
            $isWerewolf = RoleAbility::resolveSeerCheck($target['role']);

            return [
                'status' => 'success',
                'type' => 'seer_result',
                'target_id' => $targetId,
                'is_werewolf' => $isWerewolf, // ส่งผลกลับทันทีเฉพาะ Seer
            ];
        }

        // กรณี Werewolf เลือกเป้าสังหาร
        if ($actionType === 'werewolf_kill' && $currentPhase === PhaseManager::PHASE_NIGHT) {
            $target = $this->players[$targetId] ?? null;

            if (! RoleAbility::canWerewolfKill($actor['is_alive'], $target['is_alive'] ?? false, $target['role'] ?? '')) {
                return ['status' => 'error', 'message' => 'เป้าหมายไม่ถูกต้อง'];
            }

            $this->actionQueue->addNightAction($actorId, RoleAssignment::ROLE_WEREWOLF, $targetId);
            return ['status' => 'success', 'message' => 'บันทึกการโหวตฆ่าแล้ว'];
        }

        // กรณีโหวตแขวนคอตอนกลางวัน
        if ($actionType === 'day_vote' && $currentPhase === PhaseManager::PHASE_DAY_VOTING) {
            $this->actionQueue->addDayVote($actorId, $targetId);
            return ['status' => 'success', 'message' => 'บันทึกโหวตแล้ว'];
        }

        return ['status' => 'error', 'message' => 'Action ไม่ถูกต้องตาม Phase ปัจจุบัน'];
    }

    // เมื่อหมดเวลาใน Phase ปัจจุบัน ให้เปลี่ยนไป Phase ถัดไป
    public function advancePhase(): array
    {
        $currentPhase = $this->phaseManager->getCurrentPhase();
        $resultData = [];

        if ($currentPhase === PhaseManager::PHASE_NIGHT) {
            $resolution = $this->phaseManager->resolveNight($this->actionQueue, $this->players);
            $resultData['killed_player_id'] = $resolution['killed_player_id'];
            $this->winner = $resolution['winner'];
        } elseif ($currentPhase === PhaseManager::PHASE_DAY_VOTING) {
            $resolution = $this->phaseManager->resolveDayVoting($this->actionQueue, $this->players);
            $resultData['executed_player_id'] = $resolution['executed_player_id'];
            $this->winner = $resolution['winner'];
        }

        // เคลียร์ Queue หลังจบ Phase
        $this->actionQueue->clear();

        // ถ้ามีผู้ชนะแล้ว ปรับไปหน้า Game Over
        if ($this->winner !== null) {
            return [
                'next_phase' => PhaseManager::PHASE_GAME_OVER,
                'winner' => $this->winner,
                'result' => $resultData,
            ];
        }

        $nextPhase = $this->phaseManager->nextPhase();

        return [
            'next_phase' => $nextPhase,
            'duration' => $this->phaseManager->getPhaseDuration(),
            'result' => $resultData,
        ];
    }

    public function getGameState(): array
    {
        return [
            'current_phase' => $this->phaseManager->getCurrentPhase(),
            'duration' => $this->phaseManager->getPhaseDuration(),
            'players' => $this->players,
            'winner' => $this->winner,
        ];
    }
}
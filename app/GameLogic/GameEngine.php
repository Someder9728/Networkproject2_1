<?php

namespace App\GameLogic;

class GameEngine
{
    private PhaseManager $phaseManager;
    private ActionQueue $actionQueue;
    private array $players = []; // เก็บรายชื่อและสถานะผู้เล่นในห้อง
    private ?string $winner = null;
    private array $snapshot = [];

    public function __construct(array $snapshot)
    {
        foreach ([
            'game_uuid',
            'difficulty',
            'status',
            'current_phase',
            'current_round',
            'players',
            'config',
            'phase_end_time',
            'winner',
        ] as $key) {
            if (!array_key_exists($key, $snapshot)) {
                throw new \InvalidArgumentException(
                    "Snapshot ไม่มีข้อมูล {$key}"
                );
            }
        }

        if (!is_array($snapshot['players'])) {
            throw new \InvalidArgumentException(
                'ข้อมูล players ต้องเป็น array'
            );
        }

        $this->snapshot = $snapshot;
        $this->winner = $snapshot['winner'];
        $this->actionQueue = new ActionQueue();

        foreach ($snapshot['players'] as $player) {
            $uuid = $player['player_uuid'] ?? null;

            if (!is_string($uuid) || $uuid === '') {
                throw new \InvalidArgumentException(
                    'ข้อมูลผู้เล่นไม่มี player_uuid'
                );
            }

            if (isset($this->players[$uuid])) {
                throw new \InvalidArgumentException(
                    'พบ player_uuid ซ้ำใน Snapshot'
                );
            }

            $this->players[$uuid] = $player;
        }
    }

    // ผู้เล่นส่ง Action เข้ามา (รองรับ WebSocket / API Route)
    public function handlePlayerAction(
        string $actorId,
        string $actionType,
        ?string $targetId = null
    ): array {
        $error = fn (string $message) => [
            'status' => 'error',
            'message' => $message,
        ];

        if ($this->snapshot['status'] !== 'in_progress') {
            return $error('เกมยังไม่เริ่มหรือจบแล้ว');
        }

        if ($this->getRemainingSeconds() <= 0) {
            return $error('หมดเวลาส่งคำสั่งแล้ว');
        }

        $actor = $this->players[$actorId] ?? null;

        if (
            $actor === null
            || !$actor['is_alive']
            || ($actor['has_left'] ?? false)
        ) {
            return $error('ผู้เล่นไม่มีสิทธิ์ส่งคำสั่ง');
        }

        $requiredPhase = match ($actionType) {
            'day_vote', 'vote_lynch' => 'day_voting',
            'werewolf_kill', 'seer_check' => 'night',
            default => null,
        };

        if (
            $requiredPhase === null
            || $this->snapshot['current_phase'] !== $requiredPhase
        ) {
            return $error('คำสั่งไม่ตรงกับ Phase ปัจจุบัน');
        }

        $target = $targetId !== null
            ? ($this->players[$targetId] ?? null)
            : null;

        if (
            $target === null
            || !$target['is_alive']
            || ($target['has_left'] ?? false)
        ) {
            return $error('ต้องเลือกผู้เล่นที่ยังอยู่ในเกมและมีชีวิต');
        }

        if ($actionType === 'werewolf_kill') {
            if ($actor['role'] !== RoleAssignment::ROLE_WEREWOLF) {
                return $error('เฉพาะหมาป่าเท่านั้นที่ใช้คำสั่งนี้ได้');
            }

            if (!RoleAbility::canWerewolfKill(
                $actor['is_alive'],
                $target['is_alive'],
                $target['role']
            )) {
                return $error('หมาป่าเลือกหมาป่าด้วยกันไม่ได้');
            }
        }

        if ($actionType === 'seer_check') {
            if ($actor['role'] !== RoleAssignment::ROLE_SEER) {
                return $error('เฉพาะ Seer เท่านั้นที่ใช้คำสั่งนี้ได้');
            }

            $used = $this->snapshot['seer_checks_used'][$actorId] ?? 0;
            $limit = $this->snapshot['config']['seer_checks_limit'];

            if (!RoleAbility::canSeerCheck(
                $actor['is_alive'],
                $target['is_alive'],
                $used,
                $limit
            )) {
                return $error('Seer ใช้สิทธิ์ตรวจครบแล้ว');
            }
        }

        if ($requiredPhase === 'day_voting') {
            $this->snapshot['day_votes'][$actorId] = $targetId;
        } else {
            $this->snapshot['night_actions'][$actorId] = [
                'role' => $actor['role'],
                'target_id' => $targetId,
            ];
        }

        return [
            'status' => 'success',
            'message' => 'บันทึกคำสั่งแล้ว',
        ];
    }

    // เมื่อหมดเวลาใน Phase ปัจจุบัน ให้เปลี่ยนไป Phase ถัดไป
    // public function advancePhase(): array
    // {
    //     $currentPhase = $this->phaseManager->getCurrentPhase();
    //     $resultData = [];

    //     if ($currentPhase === PhaseManager::PHASE_NIGHT) {
    //         $resolution = $this->phaseManager->resolveNight($this->actionQueue, $this->players);
    //         $resultData['killed_player_id'] = $resolution['killed_player_id'];
    //         $this->winner = $resolution['winner'];
    //     } elseif ($currentPhase === PhaseManager::PHASE_DAY_VOTING) {
    //         $resolution = $this->phaseManager->resolveDayVoting($this->actionQueue, $this->players);
    //         $resultData['executed_player_id'] = $resolution['executed_player_id'];
    //         $this->winner = $resolution['winner'];
    //     }

    //     // เคลียร์ Queue หลังจบ Phase
    //     $this->actionQueue->clear();

    //     // ถ้ามีผู้ชนะแล้ว ปรับไปหน้า Game Over
    //     if ($this->winner !== null) {
    //         return [
    //             'next_phase' => PhaseManager::PHASE_GAME_OVER,
    //             'winner' => $this->winner,
    //             'result' => $resultData,
    //         ];
    //     }

    //     $nextPhase = $this->phaseManager->nextPhase();

    //     return [
    //         'next_phase' => $nextPhase,
    //         'duration' => $this->phaseManager->getPhaseDuration(),
    //         'result' => $resultData,
    //     ];
    // }

    // ยังใช้ไม่ได้ ไม่รองรับหลายระบบ จึงยังเปลี่ยน phase with phasemanager ไปก่อน
        public function advancePhase(): array
    {
        throw new \LogicException(
            'ให้เปลี่ยน Phase ผ่าน RoomService จนกว่าจะเชื่อมครบ'
        );
    }

    public function getGameState(): array
        {
            return $this->snapshot;
        }

        public function getRemainingSeconds(): int
    {
        $endTime = $this->snapshot['phase_end_time'];

        if (
            $this->snapshot['status'] !== 'in_progress'
            || $endTime === null
        ) {
            return 0;
        }

        $deadline = new \DateTimeImmutable($endTime);

        return max(0, $deadline->getTimestamp() - time());
    }

    public function resolveDayVoteOutcome(): array
    {
        if (
            $this->snapshot['status'] !== 'in_progress'
            || $this->snapshot['current_phase'] !== 'day_voting'
        ) {
            throw new \LogicException('เกมไม่ได้อยู่ในช่วงโหวต');
        }

        if (
            $this->snapshot['phase_end_time'] === null
            || $this->getRemainingSeconds() > 0
        ) {
            throw new \LogicException('ยังสรุปผลโหวตไม่ได้');
        }

        $votes = $this->snapshot['day_votes'] ?? [];

        $config = $this->snapshot['day_config']
            ?? $this->snapshot['config'];

        $rule = $config['tie_breaking'];
        $ballot = $this->snapshot['ballot_number'] ?? 1;

        // ไม่มีคะแนน ไม่ถือว่าเสมอ
        if ($votes === []) {
            return [
                'requires_revote' => false,
                'eliminated_uuid' => null,
            ];
        }

        $counts = array_count_values(array_values($votes));
        $highest = max($counts);
        $topTargets = array_keys($counts, $highest);

        if (
            $rule === 'revote_once'
            && $ballot === 1
            && count($topTargets) > 1
        ) {
            return [
                'requires_revote' => true,
                'eliminated_uuid' => null,
            ];
        }

        return [
            'requires_revote' => false,
            'eliminated_uuid' => RoleAbility::resolveDayVote(
                $votes,
                $rule === 'revote_once' ? 'no_death' : $rule
            ),
        ];
    }

    public function resolveNightKillOutcome(): array
    {
        if (
            $this->snapshot['status'] !== 'in_progress'
            || $this->snapshot['current_phase'] !== 'night'
        ) {
            throw new \LogicException('เกมไม่ได้อยู่ในช่วงกลางคืน');
        }

        if (
            $this->snapshot['phase_end_time'] === null
            || $this->getRemainingSeconds() > 0
        ) {
            throw new \LogicException('ยังสรุปผลกลางคืนไม่ได้');
        }

        $queue = new ActionQueue();

        foreach (
            $this->snapshot['night_actions'] ?? []
            as $actorUuid => $action
        ) {
            if (
                !is_array($action)
                || ($action['role'] ?? null) !== 'werewolf'
            ) {
                continue;
            }

            $targetUuid = $action['target_id'] ?? null;

            if (!is_string($targetUuid)) {
                continue;
            }

            $actor = $this->players[$actorUuid] ?? null;
            $target = $this->players[$targetUuid] ?? null;

            if (
                $actor === null
                || $target === null
                || $actor['role'] !== RoleAssignment::ROLE_WEREWOLF
                || ($actor['has_left'] ?? false)
                || ($target['has_left'] ?? false)
            ) {
                continue;
            }

            if (!RoleAbility::canWerewolfKill(
                $actor['is_alive'],
                $target['is_alive'],
                $target['role']
            )) {
                continue;
            }

            $queue->addNightAction(
                $actorUuid,
                RoleAssignment::ROLE_WEREWOLF,
                $targetUuid
            );
        }

        return [
            'killed_uuid' => RoleAbility::resolveNightKill(
                $queue->getWerewolfVotes()
            ),
        ];
    }

    public function resolveSeerOutcome(): array
    {
        if (
            $this->snapshot['status'] !== 'in_progress'
            || $this->snapshot['current_phase'] !== 'night'
            || $this->snapshot['phase_end_time'] === null
            || $this->getRemainingSeconds() > 0
        ) {
            throw new \LogicException('ยังสรุปผล Seer ไม่ได้');
        }

        $results = $this->snapshot['seer_results'] ?? [];
        $used = $this->snapshot['seer_checks_used'] ?? [];

        $round = $this->snapshot['current_round'];
        $limit = $this->snapshot['config']['seer_checks_limit'];

        foreach (
            $this->snapshot['night_actions'] ?? []
            as $actorUuid => $action
        ) {
            if (
                !is_array($action)
                || ($action['role'] ?? null) !== 'seer'
            ) {
                continue;
            }

            $targetUuid = $action['target_id'] ?? null;

            if (!is_string($targetUuid)) {
                continue;
            }

            $actor = $this->players[$actorUuid] ?? null;
            $target = $this->players[$targetUuid] ?? null;

            if (
                $actor === null
                || $target === null
                || $actor['role'] !== RoleAssignment::ROLE_SEER
                || ($actor['has_left'] ?? false)
                || ($target['has_left'] ?? false)
            ) {
                continue;
            }

            $alreadyResolved = collect($results[$actorUuid] ?? [])
                ->contains('round', $round);

            if (
                $alreadyResolved
                || !RoleAbility::canSeerCheck(
                    $actor['is_alive'],
                    $target['is_alive'],
                    $used[$actorUuid] ?? 0,
                    $limit
                )
            ) {
                continue;
            }

            $results[$actorUuid][] = [
                'round' => $round,
                'target_name' => $target['name'],
                'is_werewolf' => RoleAbility::resolveSeerCheck(
                    $target['role']
                ),
            ];

            $used[$actorUuid] = ($used[$actorUuid] ?? 0) + 1;
        }

        return [
            'seer_results' => $results,
            'seer_checks_used' => $used,
        ];
    }
}
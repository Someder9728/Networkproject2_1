<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use App\GameLogic\PhaseManager;
use App\GameLogic\ActionQueue;
use App\GameLogic\RoleAbility;

class RoomService
{
    public function create(
        string $hostName,
        string $hostUuid,
        string $difficulty
    ): array {
        $cache = Cache::store('file');

        return $cache->lock('room-create-lock', 5)
            ->block(3, function () use (
                $cache,
                $hostName,
                $hostUuid,
                $difficulty
            ) {
                // ลองสร้างรหัสใหม่ได้สูงสุด 10 ครั้ง
                for ($attempt = 0; $attempt < 10; $attempt++) {
                    $code = strtoupper(Str::random(6));
                    $key = 'room:' . $code;

                    if ($cache->has($key)) {
                        continue;
                    }

                    $room = [
                        'code' => $code,
                        'status' => 'waiting',
                        'game_uuid' => null,
                        'difficulty' => $difficulty,
                        'host_uuid' => $hostUuid,
                        'players' => [
                            [
                                'player_uuid' => $hostUuid,
                                'name' => $hostName,
                            ],
                        ],
                    ];

                    $cache->put($key, $room, now()->addHours(2));

                    return $room;
                }

                throw ValidationException::withMessages([
                    'room' => 'สร้างรหัสห้องไม่สำเร็จ กรุณาลองใหม่',
                ]);
            });
    }

    public function join(
        string $code,
        string $playerName,
        string $playerUuid
        ): array {
    $code = strtoupper($code);
    $cache = Cache::store('file');

    return $cache->lock('room-lock:' . $code, 5)
        ->block(3, function () use (
            $cache,
            $code,
            $playerName,
            $playerUuid
        ) {
            $key = 'room:' . $code;
            $room = $cache->get($key);

            if ($room === null) {
                throw ValidationException::withMessages([
                    'code' => 'ไม่พบห้องนี้ กรุณาตรวจสอบรหัสห้อง',
                ]);
            }

            // เป็นสมาชิกอยู่แล้ว: คืนห้องเดิม ไม่เพิ่มซ้ำ
            foreach ($room['players'] as $player) {
                if ($player['player_uuid'] === $playerUuid) {
                    return $room;
                }
            }

            // ผู้เล่นใหม่เข้าได้เฉพาะช่วง Lobby
            if ($room['status'] !== 'waiting') {
                throw ValidationException::withMessages([
                    'room' => 'ห้องนี้เริ่มเกมแล้ว',
                ]);
            }
            
            if (count($room['players']) >= 6) {
                throw ValidationException::withMessages([
                    'room' => 'ห้องเต็มแล้ว รับผู้เล่นได้สูงสุด 6 คน',
                ]);
                }


            $room['players'][] = [
                'player_uuid' => $playerUuid,
                'name' => $playerName,
            ];

            $cache->put($key, $room, now()->addHours(2));

            return $room;
        });
    }

    public function getRoom(string $code): array
    {
        $room = Cache::store('file')->get(
            'room:' . strtoupper($code)
        );

        abort_if($room === null, 404, 'Room not found');

        return $room;
    }


    public function leave(string $code, string $playerUuid): void
    {
        $code = strtoupper($code);
        $cache = Cache::store('file');

        $cache->lock('room-lock:' . $code, 5)
            ->block(3, function () use ($cache, $code, $playerUuid) {
                $key = 'room:' . $code;
                $room = $cache->get($key);

                abort_if($room === null, 404, 'ไม่พบห้อง');

                $isMember = collect($room['players'])
                    ->contains('player_uuid', $playerUuid);

                abort_unless($isMember, 403, 'คุณไม่ได้อยู่ในห้องนี้');

                if ($room['status'] !== 'waiting') {
                    throw ValidationException::withMessages([
                        'room' => 'ตอนนี้ออกได้เฉพาะช่วง Lobby',
                    ]);
                }

                // ลบสมาชิกและเรียง index ใหม่
                $room['players'] = array_values(array_filter(
                    $room['players'],
                    fn (array $player) => $player['player_uuid'] !== $playerUuid
                ));

                // คนสุดท้ายออก: ลบห้อง
                if (count($room['players']) === 0) {
                    $cache->forget($key);
                    return;
                }

                // Host ออก: ส่งต่อให้สมาชิกคนแรกที่เหลือ
                if ($room['host_uuid'] === $playerUuid) {
                    $room['host_uuid'] = $room['players'][0]['player_uuid'];
                }

                $cache->put($key, $room, now()->addHours(2));
            });
    }

    public function start(
        string $code,
        string $playerUuid,
        GameService $gameService
    ): array {
        $code = strtoupper($code);
        $cache = Cache::store('file');

        return $cache->lock('room-lock:' . $code, 5)
            ->block(3, function () use (
                $cache,
                $code,
                $playerUuid,
                $gameService
            ) {
                $key = 'room:' . $code;
                $room = $cache->get($key);

                abort_if($room === null, 404, 'ไม่พบห้อง');

                abort_unless(
                    $room['host_uuid'] === $playerUuid,
                    403,
                    'เฉพาะ Host เท่านั้นที่เริ่มเกมได้'
                );

                if (
                    $room['status'] !== 'waiting'
                    || ($room['game_uuid'] ?? null) !== null
                ) {
                    throw ValidationException::withMessages([
                        'room' => 'ห้องนี้เริ่มเกมไปแล้ว',
                    ]);
                }

                if (!in_array(count($room['players']), [4, 6], true)) {
                    throw ValidationException::withMessages([
                        'room' => 'ต้องมีผู้เล่น 4 หรือ 6 คนจึงเริ่มเกมได้',
                    ]);
                }

                $game = $gameService->buildSession($room);

                $room['game_uuid'] = $game['game_uuid'];
                $room['status'] = 'initializing';
                $room['game'] = $game;

                $cache->put($key, $room, now()->addHours(2));

                return $room;
            });
    }

    public function getGameView(
        string $code,
        string $playerUuid
    ): array {
        $room = $this->getRoom($code);

        $isMember = collect($room['players'])
            ->contains('player_uuid', $playerUuid);

        abort_unless($isMember, 403, 'คุณไม่ได้อยู่ในห้องนี้');

        $game = $room['game'] ?? null;

        abort_if($game === null, 404, 'ห้องนี้ยังไม่ได้เริ่มเกม');

        $me = collect($game['players'])
            ->firstWhere('player_uuid', $playerUuid);

        abort_if($me === null, 403, 'คุณไม่ได้เป็นผู้เล่นในเกมนี้');

        $voteResult = null;
        $result = $game['vote_result'] ?? null;

        if ($result !== null) {
            $eliminated = collect($game['players'])->firstWhere(
                'player_uuid',
                $result['eliminated_uuid']
            );

            $voteResult = [
                'round' => $result['round'],
                'name' => $eliminated['name'] ?? null,
            ];

            if ($eliminated !== null && $game['difficulty'] === 'easy') {
                $voteResult['role'] = $eliminated['role'];
            }
        }

        return [
            'game_uuid' => $game['game_uuid'],
            'room_code' => $game['room_code'],
            'difficulty' => $game['difficulty'],
            'status' => $game['status'],
            'current_phase' => $game['current_phase'],
            'current_round' => $game['current_round'],
            'my_role' => $me['role'],
            'players' => array_map(
                fn (array $player) => [
                    'player_uuid' => $player['player_uuid'],
                    'name' => $player['name'],
                    'is_alive' => $player['is_alive'],
                ],
                $game['players']
            ),
            'can_begin_discussion' =>
                $room['host_uuid'] === $playerUuid
                && $game['status'] === 'roles_assigned',
            'phase_end_time' => $game['phase_end_time'],
            'server_time' => now()->toIso8601String(),
            'my_vote' => $game['day_votes'][$playerUuid] ?? null,
            'can_vote' =>
                $game['status'] === 'in_progress'
                && $game['current_phase'] === 'day_voting'
                && $me['is_alive']
                && $game['phase_end_time'] !== null
                && now()->lt(
                    \Carbon\CarbonImmutable::parse($game['phase_end_time'])
                ),
            'vote_result' => $voteResult,
            'winner' => $game['winner'],
        ];
    }


    public function findRoomForPlayer(
        string $code,
        string $playerUuid
    ): ?array {
        $room = Cache::store('file')->get(
            'room:' . strtoupper($code)
        );

        if ($room === null) {
            return null;
        }

        $isMember = collect($room['players'])
            ->contains('player_uuid', $playerUuid);

        if (!$isMember) {
            return null;
        }

        return [
            'code' => $room['code'],
            'game_uuid' => $room['game_uuid'] ?? null,
        ];
    }

    public function beginDiscussion(
        string $code,
        string $playerUuid
    ): void {
        $code = strtoupper($code);
        $cache = Cache::store('file');

        $cache->lock('room-lock:' . $code, 5)
            ->block(3, function () use ($cache, $code, $playerUuid) {
                $key = 'room:' . $code;
                $room = $cache->get($key);

                abort_if($room === null, 404, 'ไม่พบห้อง');

                abort_unless(
                    $room['host_uuid'] === $playerUuid,
                    403,
                    'เฉพาะ Host เท่านั้น'
                );

                $game = $room['game'] ?? null;

                if (
                    $game === null
                    || $game['status'] !== 'roles_assigned'
                ) {
                    throw ValidationException::withMessages([
                        'game' => 'เกมยังไม่พร้อม หรือเริ่มพูดคุยไปแล้ว',
                    ]);
                }

                $phaseManager = new PhaseManager(
                    count($game['players']),
                    $game['difficulty']
                );

                $game['status'] = 'in_progress';
                $game['current_phase'] = $phaseManager->getCurrentPhase();
                $game['current_round'] = 1;
                $game['phase_end_time'] = now()
                    ->addSeconds($phaseManager->getPhaseDuration())
                    ->toIso8601String();

                $room['status'] = $game['current_phase'];
                $room['game'] = $game;

                $cache->put($key, $room, now()->addHours(2));
            });
    }

    public function finishDiscussion(
        string $code,
        string $playerUuid,
        string $expectedEndTime
    ): void {
        $code = strtoupper($code);
        $cache = Cache::store('file');

        $cache->lock('room-lock:' . $code, 5)
            ->block(3, function () use (
                $cache,
                $code,
                $playerUuid,
                $expectedEndTime
            ) {
                $key = 'room:' . $code;
                $room = $cache->get($key);

                abort_if($room === null, 404, 'ไม่พบห้อง');

                abort_unless(
                    collect($room['players'])
                        ->contains('player_uuid', $playerUuid),
                    403,
                    'คุณไม่ได้อยู่ในห้องนี้'
                );

                $game = $room['game'] ?? null;

                abort_if($game === null, 404, 'ยังไม่มีเกม');

                // ข้ามคำขอซ้ำ หรือคำขอจากหน้าของ Phase เก่า
                if (
                    $game['status'] !== 'in_progress'
                    || $game['current_phase'] !== 'day_discussion'
                    || $game['phase_end_time'] !== $expectedEndTime
                ) {
                    return;
                }

                $deadline = \Carbon\CarbonImmutable::parse(
                    $game['phase_end_time']
                );

                if (now()->lt($deadline)) {
                    throw ValidationException::withMessages([
                        'game' => 'ยังไม่หมดเวลาพูดคุย',
                    ]);
                }

                // Constructor ของ P3 เริ่มที่ Discussion
                $phaseManager = new PhaseManager(
                    count($game['players']),
                    $game['difficulty']
                );

                $game['current_phase'] = $phaseManager->nextPhase();
                $game['day_votes'] = [];
                $game['phase_end_time'] = now()
                    ->addSeconds($phaseManager->getPhaseDuration())
                    ->toIso8601String();

                $room['status'] = $game['current_phase'];
                $room['game'] = $game;

                $cache->put($key, $room, now()->addHours(2));
            });
    }


    public function vote(
        string $code,
        string $playerUuid,
        string $targetUuid,
        string $expectedEndTime
    ): void {
        $code = strtoupper($code);
        $cache = Cache::store('file');

        $cache->lock('room-lock:' . $code, 5)
            ->block(3, function () use (
                $cache,
                $code,
                $playerUuid,
                $targetUuid,
                $expectedEndTime
            ) {
                $key = 'room:' . $code;
                $room = $cache->get($key);

                abort_if($room === null, 404, 'ไม่พบห้อง');

                $game = $room['game'] ?? null;

                if (
                    $game === null
                    || $game['status'] !== 'in_progress'
                    || $game['current_phase'] !== 'day_voting'
                    || $game['phase_end_time'] === null
                    || $game['phase_end_time'] !== $expectedEndTime
                ) {
                    throw ValidationException::withMessages([
                        'vote' => 'ไม่ใช่ช่วงโหวตปัจจุบัน กรุณารีเฟรช',
                    ]);
                }

                if (now()->gte(
                    \Carbon\CarbonImmutable::parse($game['phase_end_time'])
                )) {
                    throw ValidationException::withMessages([
                        'vote' => 'หมดเวลาโหวตแล้ว',
                    ]);
                }

                $players = collect($game['players']);
                $actor = $players->firstWhere('player_uuid', $playerUuid);
                $target = $players->firstWhere('player_uuid', $targetUuid);

                abort_if($actor === null, 403, 'คุณไม่ได้อยู่ในเกมนี้');

                if (!$actor['is_alive']) {
                    throw ValidationException::withMessages([
                        'vote' => 'ผู้เล่นที่ตายแล้วโหวตไม่ได้',
                    ]);
                }

                if ($target === null || !$target['is_alive']) {
                    throw ValidationException::withMessages([
                        'vote' => 'ต้องเลือกผู้เล่นที่ยังมีชีวิตในเกมนี้',
                    ]);
                }

                $queue = new ActionQueue();

                // โหลดคะแนนเดิมกลับเข้า Queue
                foreach ($game['day_votes'] ?? [] as $voter => $targetId) {
                    $queue->addDayVote($voter, $targetId);
                }

                $queue->addDayVote($playerUuid, $targetUuid);

                $game['day_votes'] = $queue->getDayVotes();
                $room['game'] = $game;

                $cache->put($key, $room, now()->addHours(2));
            });
    }

    public function finishVoting(
        string $code,
        string $playerUuid,
        string $expectedEndTime
    ): void {
        $code = strtoupper($code);
        $cache = Cache::store('file');

        $cache->lock('room-lock:' . $code, 5)
            ->block(3, function () use (
                $cache,
                $code,
                $playerUuid,
                $expectedEndTime
            ) {
                $key = 'room:' . $code;
                $room = $cache->get($key);

                abort_if($room === null, 404, 'ไม่พบห้อง');

                $game = $room['game'] ?? null;

                abort_if($game === null, 404, 'ยังไม่มีเกม');

                abort_unless(
                    collect($game['players'])
                        ->contains('player_uuid', $playerUuid),
                    403,
                    'คุณไม่ได้อยู่ในเกมนี้'
                );

                // คำขอซ้ำหรือมาจากช่วงโหวตเก่า: ไม่ทำซ้ำ
                if (
                    $game['status'] !== 'in_progress'
                    || $game['current_phase'] !== 'day_voting'
                    || $game['phase_end_time'] === null
                    || $game['phase_end_time'] !== $expectedEndTime
                ) {
                    return;
                }

                if (now()->lt(
                    \Carbon\CarbonImmutable::parse($game['phase_end_time'])
                )) {
                    throw ValidationException::withMessages([
                        'game' => 'ยังไม่หมดเวลาโหวต',
                    ]);
                }

                $targetUuid = RoleAbility::resolveDayVote(
                    $game['day_votes'] ?? [],
                    $game['config']['tie_breaking']
                );

                $game['vote_result'] = [
                    'round' => $game['current_round'],
                    'eliminated_uuid' => $targetUuid,
                ];

                if ($targetUuid !== null) {
                    foreach ($game['players'] as &$player) {
                        if ($player['player_uuid'] === $targetUuid) {
                            $player['is_alive'] = false;
                            break;
                        }
                    }

                    unset($player);
                }

                $game['winner'] = RoleAbility::checkWinCondition(
                    $game['players']
                );

                $game['day_votes'] = [];

                if ($game['winner'] !== null) {
                    $game['status'] = 'finished';
                    $game['current_phase'] = PhaseManager::PHASE_GAME_OVER;
                    $game['phase_end_time'] = null;
                    $room['status'] = 'ended';
                } else {
                    // P3 เริ่มที่ Discussion → Voting → Night
                    $phaseManager = new PhaseManager(
                        count($game['players']),
                        $game['difficulty']
                    );

                    $phaseManager->nextPhase();
                    $game['current_phase'] = $phaseManager->nextPhase();

                    $game['phase_end_time'] = now()
                        ->addSeconds($phaseManager->getPhaseDuration())
                        ->toIso8601String();

                    $game['night_actions'] = [];
                    $room['status'] = $game['current_phase'];
                }

                $room['game'] = $game;

                $cache->put($key, $room, now()->addHours(2));
            });
    }

}
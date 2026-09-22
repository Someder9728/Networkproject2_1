<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use App\GameLogic\PhaseManager;
use App\GameLogic\ActionQueue;
use App\GameLogic\RoleAbility;
use App\Models\Room;
use Illuminate\Support\Facades\DB;
use App\Models\VoteAction;

class RoomService
{

    public function __construct(
        private RoomDatabaseService $roomDatabase
    ) {}


    public function create(
        string $hostName,
        string $hostUuid,
        string $difficulty
    ): array {
        $room = $this->roomDatabase->create(
            $hostName,
            $hostUuid,
            $difficulty
        );

        return $this->getRoom($room->room_code);
    }

    public function join(
        string $code,
        string $playerName,
        string $playerUuid
    ): array {
        $room = $this->roomDatabase->join(
            $code,
            $playerName,
            $playerUuid
        );

        return $this->getRoom($room->room_code);
    }

    public function leave(string $code, string $playerUuid): void
    {
        $this->roomDatabase->leave($code, $playerUuid);
    }


    public function getRoom(string $code): array
    {
        $room = $this->roomDatabase->findLobby($code);

        abort_if($room === null, 404, 'ไม่พบห้อง');

        // ยังไม่เริ่มเกม: ข้อมูลจาก Database เพียงพอ
        if ($room['game_uuid'] === null) {
            return $room;
        }

        $cachedRoom = Cache::store('file')->get(
            'room:' . $room['code']
        );

        $game = $cachedRoom['game'] ?? null;

        abort_if(
            $game === null
            || ($game['game_uuid'] ?? null) !== $room['game_uuid'],
            409,
            'ข้อมูลเกมชั่วคราวไม่พร้อมหรือหมดอายุ ไม่สามารถเล่นต่อได้'
        );

        $room['game'] = $game;

        // สถานะเตรียมเกมเป็นสถานะ Runtime
        if ($game['status'] === 'roles_assigned') {
            $room['status'] = 'initializing';
        }

        return $room;
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
                $room = $this->getRoom($code);

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

                $this->saveGameRoom($room);

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


        $myVote = null;

        if ($game['current_phase'] === 'day_voting') {
            $votes = $this->loadDayVotes(
                $code,
                $game['current_round'],
                $game['players']
            );

            $myVote = $votes[$playerUuid] ?? null;
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
                    'has_left' => $player['has_left'] ?? false,
                ],
                $game['players']
            ),
            'can_begin_discussion' =>
                $room['host_uuid'] === $playerUuid
                && $game['status'] === 'roles_assigned',
            'phase_end_time' => $game['phase_end_time'],
            'server_time' => now()->toIso8601String(),
            'my_vote' => $myVote,
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
        $room = $this->roomDatabase->findLobby($code);

        if ($room === null) {
            return null;
        }

        if (!collect($room['players'])->contains(
            'player_uuid',
            $playerUuid
        )) {
            return null;
        }

        return [
            'code' => $room['code'],
            'game_uuid' => $room['game_uuid'],
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
                $room = $this->getRoom($code);

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

                $this->saveGameRoom($room);
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
                $room = $this->getRoom($code);

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

                $this->saveGameRoom($room);
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
                $room = $this->getRoom($code);

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
                $storedVotes = $this->loadDayVotes(
                    $code,
                    $game['current_round'],
                    $game['players']
                );

                foreach ($storedVotes as $voter => $targetId) {
                    $queue->addDayVote($voter, $targetId);
                }

                $queue->addDayVote($playerUuid, $targetUuid);

                $game['day_votes'] = $queue->getDayVotes();
                $room['game'] = $game;

                $this->saveGameRoom($room, [
                    'voter_uuid' => $playerUuid,
                    'target_uuid' => $targetUuid,
                ]);
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
                $room = $this->getRoom($code);

                abort_if($room === null, 404, 'ไม่พบห้อง');

                $game = $room['game'] ?? null;

                abort_if($game === null, 404, 'ยังไม่มีเกม');

                abort_unless(
                    collect($game['players'])
                    ->filter(fn (array $player) => !($player['has_left'] ?? false))
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

                $votes = $this->loadDayVotes(
                    $code,
                    $game['current_round'],
                    $game['players']
                );

                $targetUuid = RoleAbility::resolveDayVote(
                    $votes,
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

                $this->saveGameRoom($room);
            });
    }

    private function saveGameRoom(
        array $room,
        ?array $voteData = null
    ): void {
        $game = $room['game'];

        DB::transaction(function () use ($room, $game, $voteData) {
            $record = Room::where('room_code', $room['code'])
                ->firstOrFail();

            $record->update([
                'game_uuid' => $game['game_uuid'],
                'room_status' => $room['status'] === 'initializing'
                    ? 'waiting'
                    : $room['status'],
                'room_phase_end_time' => $game['phase_end_time'],
            ]);

            foreach ($game['players'] as $player) {
                $record->players()
                    ->where('player_uuid', $player['player_uuid'])
                    ->update([
                        'role' => $player['role'],
                        'is_alive' => $player['is_alive'],
                        'is_connected' => $player['is_connected'] ?? true,
                        'has_left' => $player['has_left'] ?? false,
                        'is_host' => $player['player_uuid'] === $room['host_uuid'],
                    ]);
            }

            if ($voteData !== null) {
                $voter = $record->players()
                    ->where('player_uuid', $voteData['voter_uuid'])
                    ->firstOrFail();

                $target = $record->players()
                    ->where('player_uuid', $voteData['target_uuid'])
                    ->firstOrFail();

                VoteAction::updateOrCreate(
                    [
                        'rooms_room_id' => $record->room_id,
                        'phase_number' => $game['current_round'],
                        'phase_type' => 'day_voting',
                        'action_type' => 'vote_lynch',
                        'players_voter_id' => $voter->player_id,
                    ],
                    [
                        'players_target_id' => $target->player_id,
                    ]
                );
            }
        });

        Cache::store('file')->put(
            'room:' . $room['code'],
            $room,
            now()->addHours(2)
        );
    }


    private function loadDayVotes(
        string $code,
        int $round,
        array $gamePlayers
    ): array {
        $record = Room::where('room_code', strtoupper($code))
            ->firstOrFail();

        // แปลง ID ตัวเลขใน Database กลับเป็น UUID
        $uuidById = $record->players()
            ->pluck('player_uuid', 'player_id');

        $aliveUuids = collect($gamePlayers)
            ->where('is_alive', true)
            ->pluck('player_uuid')
            ->all();

        $rows = VoteAction::where('rooms_room_id', $record->room_id)
            ->where('phase_number', $round)
            ->where('phase_type', 'day_voting')
            ->where('action_type', 'vote_lynch')
            ->orderBy('vote_id')
            ->get();

        $votes = [];

        foreach ($rows as $row) {
            $voterUuid = $uuidById->get($row->players_voter_id);
            $targetUuid = $uuidById->get($row->players_target_id);

            if (
                !in_array($voterUuid, $aliveUuids, true)
                || !in_array($targetUuid, $aliveUuids, true)
            ) {
                continue;
            }

            $votes[$voterUuid] = $targetUuid;
        }

        return $votes;
    }

    public function disconnectForDebug(
        string $code,
        string $playerUuid
    ): string {
        $code = strtoupper($code);
        $cache = Cache::store('file');

        return $cache->lock('room-lock:' . $code, 10)
            ->block(3, function () use ($code, $playerUuid) {
                $room = $this->getRoom($code);
                $game = $room['game'] ?? null;

                abort_if($game === null, 404, 'ยังไม่มีเกม');

                if ($game['status'] === 'finished') {
                    throw ValidationException::withMessages([
                        'game' => 'เกมนี้จบแล้ว',
                    ]);
                }

                $index = array_search(
                    $playerUuid,
                    array_column($game['players'], 'player_uuid'),
                    true
                );

                abort_if($index === false, 403, 'คุณไม่ได้อยู่ในเกมนี้');

                $player = &$game['players'][$index];

                // กดซ้ำต้องไม่ยืดเวลารอกลับ
                if (
                    !($player['is_connected'] ?? true)
                    && !empty($player['reconnect_deadline'])
                ) {
                    return $player['reconnect_deadline'];
                }

                $seconds = max(
                    1,
                    (int) config('game.reconnect_timeout_seconds', 60)
                );

                $now = now();
                $deadline = $now->copy()
                    ->addSeconds($seconds)
                    ->toIso8601String();

                $player['is_connected'] = false;
                $player['disconnected_at'] = $now->toIso8601String();
                $player['reconnect_deadline'] = $deadline;

                unset($player);

                $room['game'] = $game;
                $this->saveGameRoom($room);

                return $deadline;
            });
    }

    public function leaveGame(string $code, string $playerUuid): void
    {
        $code = strtoupper(trim($code));
        $cache = Cache::store('file');

        $cache->lock('room-lock:' . $code, 10)
            ->block(3, function () use ($code, $playerUuid) {
                $room = $this->getRoom($code);
                $game = $room['game'] ?? null;

                abort_if($game === null, 404, 'ห้องนี้ยังไม่ได้เริ่มเกม');

                $index = array_search(
                    $playerUuid,
                    array_column($game['players'], 'player_uuid'),
                    true
                );

                abort_if($index === false, 403, 'คุณไม่ได้อยู่ในเกมนี้');

                // กดซ้ำไม่ประมวลผลใหม่
                if ($game['players'][$index]['has_left'] ?? false) {
                    return;
                }

                $game['players'][$index]['has_left'] = true;
                $game['players'][$index]['is_connected'] = false;
                $game['players'][$index]['is_alive'] = false;

                // ยกเลิกคำสั่งที่ยังรอประมวลผลของคนออก
                unset($game['day_votes'][$playerUuid]);
                unset($game['night_actions'][$playerUuid]);

                // รายชื่อสมาชิกปัจจุบันไม่รวมคนออก
                $room['players'] = array_values(array_filter(
                    $room['players'],
                    fn (array $player) =>
                        $player['player_uuid'] !== $playerUuid
                ));

                // ส่งต่อ Host ให้สมาชิกคนแรกที่เหลือ
                if ($room['host_uuid'] === $playerUuid) {
                    $room['host_uuid'] =
                        $room['players'][0]['player_uuid'] ?? null;
                }

                // เกมที่จบแล้วต้องไม่เปลี่ยนผลผู้ชนะเดิม
                if ($game['status'] !== 'finished') {
                    $game['winner'] = RoleAbility::checkWinCondition(
                        $game['players']
                    );

                    if (
                        $game['winner'] !== null
                        || count($room['players']) === 0
                    ) {
                        $game['status'] = 'finished';
                        $game['current_phase'] =
                            PhaseManager::PHASE_GAME_OVER;
                        $game['phase_end_time'] = null;
                        $room['status'] = 'ended';
                    }
                }

                $room['game'] = $game;
                $this->saveGameRoom($room);
            });
    }

}
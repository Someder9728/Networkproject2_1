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
use App\GameLogic\RandomEvent;
use App\GameLogic\GameConfiguration;
use App\GameLogic\GameEngine;
use App\Exceptions\GameSnapshotUnavailableException;
use App\Events\PhaseChanged;
use App\Events\RoomUpdated;


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

        $result = $this->getRoom($room->room_code);

        $this->broadcastRoomUpdated($room->room_code);

        return $result;
    }

    public function leave(
        string $code,
        string $playerUuid
    ): void {
        $this->roomDatabase->leave($code, $playerUuid);

        $this->broadcastRoomUpdated($code);
    }


    public function getRoom(string $code): array
    {
        $code = strtoupper(trim($code));

        return DB::transaction(function () use ($code) {
            $room = $this->roomDatabase->findLobby($code);

            abort_if($room === null, 404, 'ไม่พบห้อง');

            if ($room['game_uuid'] === null) {
                return $room;
            }

            $record = Room::where('room_code', $code)->firstOrFail();
            $game = $record->game_snapshot;

            if (
                !is_array($game)
                || ($game['game_uuid'] ?? null) !== $room['game_uuid']
            ) {
                throw new GameSnapshotUnavailableException($code);
            }

            $room['game'] = $game;

            if ($game['status'] === 'roles_assigned') {
                $room['status'] = 'initializing';
            }

            return $room;
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
                $room = $this->getRoom($code);

                abort_if($room === null, 404, 'ไม่พบห้อง');

                if ($playerUuid !== null) {
                    abort_unless(
                        collect($room['players'])
                            ->contains('player_uuid', $playerUuid),
                        403,
                        'คุณไม่ได้อยู่ในห้องนี้'
                    );
                }

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

        if ($playerUuid !== null) {
            abort_unless(
                collect($room['players'])
                    ->contains('player_uuid', $playerUuid),
                403,
                'คุณไม่ได้อยู่ในห้องนี้'
            );
        }

        $game = $room['game'] ?? null;

        abort_if($game === null, 404, 'ห้องนี้ยังไม่ได้เริ่มเกม');

        $me = collect($game['players'])
            ->firstWhere('player_uuid', $playerUuid);

        abort_if($me === null, 403, 'คุณไม่ได้เป็นผู้เล่นในเกมนี้');

        $werewolfTeammates = [];

        if ($me['role'] === 'werewolf') {
            $werewolfTeammates = collect($game['players'])
                ->filter(fn (array $player) =>
                    $player['role'] === 'werewolf'
                    && $player['player_uuid'] !== $playerUuid
                )
                ->map(fn (array $player) => [
                    'player_uuid' => $player['player_uuid'],
                    'name' => $player['name'],
                    'is_alive' => $player['is_alive'],
                    'has_left' => $player['has_left'] ?? false,
                ])
                ->values()
                ->all();
        }

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
                    $game['players'],
                    $game['ballot_number'] ?? 1
                );

            $myVote = $votes[$playerUuid] ?? null;
        }

        $canWerewolfAct =
            $game['status'] === 'in_progress'
            && $game['current_phase'] === 'night'
            && $me['role'] === 'werewolf'
            && $me['is_alive']
            && !($me['has_left'] ?? false)
            && $game['phase_end_time'] !== null
            && now()->lt(
                \Carbon\CarbonImmutable::parse($game['phase_end_time'])
            );

        $werewolfTargets = [];

        if ($canWerewolfAct) {
            $werewolfTargets = collect($game['players'])
                ->filter(fn (array $player) =>
                    $player['is_alive']
                    && !($player['has_left'] ?? false)
                    && $player['role'] !== 'werewolf'
                )
                ->map(fn (array $player) => [
                    'player_uuid' => $player['player_uuid'],
                    'name' => $player['name'],
                ])
                ->values()
                ->all();
        }

        $seerUsed = $game['seer_checks_used'][$playerUuid] ?? 0;
        $seerLimit = $game['config']['seer_checks_limit'];

        $canSeerAct =
            $game['status'] === 'in_progress'
            && $game['current_phase'] === 'night'
            && $me['role'] === 'seer'
            && $me['is_alive']
            && !($me['has_left'] ?? false)
            && ($seerLimit === null || $seerUsed < $seerLimit)
            && $game['phase_end_time'] !== null
            && now()->lt(
                \Carbon\CarbonImmutable::parse($game['phase_end_time'])
            );

        $seerTargets = [];

        if ($canSeerAct) {
            $seerTargets = collect($game['players'])
                ->filter(fn (array $player) =>
                    $player['is_alive']
                    && !($player['has_left'] ?? false)
                )
                ->map(fn (array $player) => [
                    'player_uuid' => $player['player_uuid'],
                    'name' => $player['name'],
                ])
                ->values()
                ->all();
        }


        $nightResult = null;
        $result = $game['night_result'] ?? null;

        if ($result !== null) {
            $killed = collect($game['players'])->firstWhere(
                'player_uuid',
                $result['killed_uuid']
            );

            $nightResult = [
                'round' => $result['round'],
                'name' => $killed['name'] ?? null,
            ];

            if ($killed !== null && $game['difficulty'] === 'easy') {
                $nightResult['role'] = $killed['role'];
            }
        }

        $nightEvent = null;
        $storedEvent = $game['night_event'] ?? null;

        if ($storedEvent !== null) {
            $event = $storedEvent['event'];

            $nightEvent = [
                'id' => $event['id'],
                'name' => $event['name'],
                'description' => $event['description'],
                'night_round' => $storedEvent['night_round'],
                'applies_to_round' => $storedEvent['applies_to_round'],
            ];
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
            'werewolf_teammates' => $werewolfTeammates,
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
            'can_werewolf_act' => $canWerewolfAct,
            'werewolf_targets' => $werewolfTargets,
            'can_seer_act' => $canSeerAct,
            'seer_targets' => $seerTargets,
            'my_seer_checks_used' => $me['role'] === 'seer' ? $seerUsed : null,
            'my_seer_checks_limit' => $me['role'] === 'seer' ? $seerLimit : null,
            'my_night_target' => in_array($me['role'], ['werewolf', 'seer'], true)
            ? ($game['night_actions'][$playerUuid]['target_id'] ?? null)
            : null,
            'night_result' => $nightResult,
            'my_seer_results' => $me['role'] === 'seer'
                ? ($game['seer_results'][$playerUuid] ?? [])
                : [],
            'night_event' => $nightEvent,
            'day_event' => $game['day_event'] ?? null,
            'vote_result' => $voteResult,
            'ballot_number' => $game['ballot_number'] ?? 1,
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

                if ($playerUuid !== null) {
                    abort_unless(
                        collect($room['players'])
                            ->contains('player_uuid', $playerUuid),
                        403,
                        'คุณไม่ได้อยู่ในห้องนี้'
                    );
                }

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
                ->addSeconds($game['config']['day_discussion_sec'])
                ->toIso8601String();

                $room['status'] = $game['current_phase'];
                $room['game'] = $game;

                $this->saveGameRoom($room);
            });
    }

    private function resolveDiscussion(
        string $code,
        ?string $playerUuid,
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

                if ($playerUuid !== null) {
                    abort_unless(
                        collect($room['players'])
                            ->contains('player_uuid', $playerUuid),
                        403,
                        'คุณไม่ได้อยู่ในห้องนี้'
                    );
                }

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
                $game['ballot_number'] = 1;

                $dayConfig = $game['day_config'] ?? $game['config'];

                $game['phase_end_time'] = now()
                    ->addSeconds($dayConfig['day_voting_sec'])
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
        $code = strtoupper(trim($code));

        Cache::store('file')->lock('room-lock:' . $code, 10)
            ->block(3, function () use (
                $code,
                $playerUuid,
                $targetUuid,
                $expectedEndTime
            ) {
                $room = $this->getRoom($code);
                $game = $room['game'] ?? null;

                abort_if($game === null, 404, 'ยังไม่มีเกม');

                if ($playerUuid !== null) {
                abort_unless(
                    collect($room['players'])
                        ->contains('player_uuid', $playerUuid),
                    403,
                    'คุณไม่ได้อยู่ในห้องนี้'
                );
            }

                if (
                    $game['phase_end_time'] === null
                    || $game['phase_end_time'] !== $expectedEndTime
                ) {
                    throw ValidationException::withMessages([
                        'vote' => 'ช่วงเวลาเกมเปลี่ยนแล้ว กรุณารีเฟรช',
                    ]);
                }

                // โหลดเฉพาะคะแนนของวันและรอบโหวตปัจจุบัน
                $game['day_votes'] = $this->loadDayVotes(
                    $code,
                    $game['current_round'],
                    $game['players'],
                    $game['ballot_number'] ?? 1
                );

                $engine = new GameEngine($game);

                $result = $engine->handlePlayerAction(
                    $playerUuid,
                    'vote_lynch',
                    $targetUuid
                );

                if ($result['status'] !== 'success') {
                    throw ValidationException::withMessages([
                        'vote' => $result['message'],
                    ]);
                }

                $room['game'] = $engine->getGameState();

                $this->saveGameRoom($room, [
                    'voter_uuid' => $playerUuid,
                    'target_uuid' => $targetUuid,
                    'action_type' => 'vote_lynch',
                ]);
            });
    }

    private function resolveVoting(
        string $code,
        ?string $playerUuid,
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

                if ($playerUuid !== null) {
                    abort_unless(
                        collect($game['players'])
                        ->filter(fn (array $player) => !($player['has_left'] ?? false))
                        ->contains('player_uuid', $playerUuid),
                        403,
                        'คุณไม่ได้อยู่ในเกมนี้'
                    );
                }

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
                // 
                $game['day_votes'] = $this->loadDayVotes(
                    $code,
                    $game['current_round'],
                    $game['players'],
                    $game['ballot_number'] ?? 1
                );

                $engine = new GameEngine($game);
                $outcome = $engine->resolveDayVoteOutcome();

                $dayConfig = $game['day_config'] ?? $game['config'];

                if ($outcome['requires_revote']) {
                    $game['ballot_number'] = 2;
                    $game['day_votes'] = [];

                    $game['phase_end_time'] = now()
                        ->addSeconds($dayConfig['day_voting_sec'])
                        ->toIso8601String();

                    $room['game'] = $game;
                    $this->saveGameRoom($room);

                    return;
                }

                $targetUuid = $outcome['eliminated_uuid'];

                



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

                    $game['night_event'] = [
                    'night_round' => $game['current_round'],
                    'applies_to_round' => $game['current_round'] + 1,
                    'event' => RandomEvent::random(
                        count($game['players']),
                        $game['difficulty']
                    ),
                    // 'event' => RandomEvent::get(
                    //     'short_discussion',
                    //     $game['difficulty'],
                    //     count($game['players'])
                    // ),
                ];
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

            $previousGame = $record->game_snapshot;

            $previousState = is_array($previousGame)
                ? $this->phaseBroadcastState($previousGame)
                : null;

            $nextState = $this->phaseBroadcastState($game);

            $phaseChanged = $previousState !== $nextState;

            $membershipChanged = is_array($previousGame)
                && $this->roomMembershipState($previousGame)
                    !== $this->roomMembershipState($game);

            $record->update([
                'game_uuid' => $game['game_uuid'],
                'room_status' => $room['status'] === 'initializing'
                    ? 'waiting'
                    : $room['status'],
                'room_phase_end_time' => $game['phase_end_time'],
                'game_snapshot' => $game,
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
                        'phase_type' => $game['current_phase'],
                        'action_type' => $voteData['action_type'] ?? 'vote_lynch',
                        'players_voter_id' => $voter->player_id,
                        'ballot_number' => $game['current_phase'] === 'day_voting'
                        ? ($game['ballot_number'] ?? 1)
                        : 1,
                    ],
                    [
                        'players_target_id' => $target->player_id,
                    ]
                );
            }

            if ($membershipChanged) {
                $this->broadcastRoomUpdated($room['code']);
            }

            if ($phaseChanged) {
                $roomCode = $room['code'];

                DB::afterCommit(function () use ($roomCode, $nextState) {
                    try {
                        PhaseChanged::dispatch($roomCode, $nextState);
                    } catch (\Throwable $exception) {
                        report($exception);
                    }
                });
            }

        });


    }


    private function loadDayVotes(
        string $code,
        int $round,
        array $gamePlayers,
        int $ballotNumber = 1
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
            ->where('ballot_number', $ballotNumber)
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

    public function werewolfAction(
        string $code,
        string $playerUuid,
        string $targetUuid,
        string $expectedEndTime
    ): void {
        $code = strtoupper(trim($code));

        Cache::store('file')->lock('room-lock:' . $code, 10)
            ->block(3, function () use (
                $code,
                $playerUuid,
                $targetUuid,
                $expectedEndTime
            ) {
                $room = $this->getRoom($code);
                $game = $room['game'] ?? null;

                abort_if($game === null, 404, 'ยังไม่มีเกม');

                if ($playerUuid !== null) {
                    abort_unless(
                        collect($room['players'])
                            ->contains('player_uuid', $playerUuid),
                        403,
                        'คุณไม่ได้อยู่ในห้องนี้'
                    );
                }

                if (
                    $game['phase_end_time'] === null
                    || $game['phase_end_time'] !== $expectedEndTime
                ) {
                    throw ValidationException::withMessages([
                        'action' => 'ช่วงเวลาเกมเปลี่ยนแล้ว กรุณารีเฟรช',
                    ]);
                }

                $engine = new GameEngine($game);

                $result = $engine->handlePlayerAction(
                    $playerUuid,
                    'werewolf_kill',
                    $targetUuid
                );

                if ($result['status'] !== 'success') {
                    throw ValidationException::withMessages([
                        'action' => $result['message'],
                    ]);
                }

                $room['game'] = $engine->getGameState();

                $this->saveGameRoom($room, [
                    'voter_uuid' => $playerUuid,
                    'target_uuid' => $targetUuid,
                    'action_type' => 'werewolf_kill',
                ]);
            });
    }

    public function seerAction(
        string $code,
        string $playerUuid,
        string $targetUuid,
        string $expectedEndTime
    ): void {
        $code = strtoupper(trim($code));

        Cache::store('file')->lock('room-lock:' . $code, 10)
            ->block(3, function () use (
                $code,
                $playerUuid,
                $targetUuid,
                $expectedEndTime
            ) {
                $room = $this->getRoom($code);
                $game = $room['game'] ?? null;

                abort_if($game === null, 404, 'ยังไม่มีเกม');

                // ตรวจตัวตนจากสมาชิกปัจจุบันของห้อง
                if ($playerUuid !== null) {
                    abort_unless(
                        collect($room['players'])
                            ->contains('player_uuid', $playerUuid),
                        403,
                        'คุณไม่ได้อยู่ในห้องนี้'
                    );
                }

                // ป้องกันคำสั่งจากหน้าเกมของรอบเก่า
                if (
                    $game['phase_end_time'] === null
                    || $game['phase_end_time'] !== $expectedEndTime
                ) {
                    throw ValidationException::withMessages([
                        'action' => 'ช่วงเวลาเกมเปลี่ยนแล้ว กรุณารีเฟรช',
                    ]);
                }

                $engine = new GameEngine($game);

                $result = $engine->handlePlayerAction(
                    $playerUuid,
                    'seer_check',
                    $targetUuid
                );

                if ($result['status'] !== 'success') {
                    throw ValidationException::withMessages([
                        'action' => $result['message'],
                    ]);
                }

                $room['game'] = $engine->getGameState();

                $this->saveGameRoom($room, [
                    'voter_uuid' => $playerUuid,
                    'target_uuid' => $targetUuid,
                    'action_type' => 'seer_check',
                ]);
            });
    }

    private function resolveNight(
        string $code,
        ?string $playerUuid,
        string $expectedEndTime
    ): void {
        $code = strtoupper(trim($code));

        Cache::store('file')->lock('room-lock:' . $code, 10)
            ->block(3, function () use (
                $code,
                $playerUuid,
                $expectedEndTime
            ) {
                $room = $this->getRoom($code);
                $game = $room['game'] ?? null;

                abort_if($game === null, 404, 'ยังไม่มีเกม');

                if ($playerUuid !== null) {
                    abort_unless(
                        collect($room['players'])
                            ->contains('player_uuid', $playerUuid),
                        403,
                        'คุณไม่ได้อยู่ในห้องนี้'
                    );
                }

                if (
                    $game['status'] !== 'in_progress'
                    || $game['current_phase'] !== 'night'
                    || $game['phase_end_time'] === null
                    || $game['phase_end_time'] !== $expectedEndTime
                ) {
                    return;
                }

                if (now()->lt(
                    \Carbon\CarbonImmutable::parse($game['phase_end_time'])
                )) {
                    throw ValidationException::withMessages([
                        'game' => 'ยังไม่หมดเวลากลางคืน',
                    ]);
                }

                $record = Room::where('room_code', $code)->firstOrFail();

                $uuidById = $record->players()
                    ->pluck('player_uuid', 'player_id');

                $actions = VoteAction::where(
                    'rooms_room_id',
                    $record->room_id
                )
                    ->where('phase_number', $game['current_round'])
                    ->where('phase_type', 'night')
                    ->whereIn('action_type', [
                        'werewolf_kill',
                        'seer_check',
                    ])
                    ->orderBy('vote_id')
                    ->get();

                $players = collect($game['players'])
                    ->keyBy('player_uuid');

                $validWerewolfActions = [];
                $validSeerActions = [];

                // โหลดเฉพาะคำสั่งที่ผู้ใช้และเป้าหมายยังมีสิทธิ์
                foreach ($actions as $action) {
                    $actorUuid = $uuidById->get($action->players_voter_id);
                    $targetUuid = $uuidById->get($action->players_target_id);

                    $actor = $players->get($actorUuid);
                    $target = $players->get($targetUuid);

                    if (
                        $actor === null
                        || $target === null
                        || !$actor['is_alive']
                        || !$target['is_alive']
                        || ($actor['has_left'] ?? false)
                        || ($target['has_left'] ?? false)
                    ) {
                        continue;
                    }

                    if (
                        $action->action_type === 'werewolf_kill'
                        && $actor['role'] === 'werewolf'
                        && RoleAbility::canWerewolfKill(
                            $actor['is_alive'],
                            $target['is_alive'],
                            $target['role']
                        )
                    ) {
                        $validWerewolfActions[$actorUuid] = [
                            'role' => 'werewolf',
                            'target_id' => $targetUuid,
                        ];
                    }

                    if (
                        $action->action_type === 'seer_check'
                        && $actor['role'] === 'seer'
                    ) {
                        $validSeerActions[$actorUuid] = [
                            'role' => 'seer',
                            'target_id' => $targetUuid,
                        ];
                    }
                }

                $resolutionSnapshot = $game;

                $resolutionSnapshot['night_actions'] = array_replace(
                    $validWerewolfActions,
                    $validSeerActions
                );

                $engine = new GameEngine($resolutionSnapshot);

                $nightOutcome = $engine->resolveNightKillOutcome();
                $seerOutcome = $engine->resolveSeerOutcome();

                $killedUuid = $nightOutcome['killed_uuid'];

                $game['seer_results'] = $seerOutcome['seer_results'];
                $game['seer_checks_used'] = $seerOutcome['seer_checks_used'];

                $game['night_result'] = [
                    'round' => $game['current_round'],
                    'killed_uuid' => $killedUuid,
                ];

                if ($killedUuid !== null) {
                    foreach ($game['players'] as &$player) {
                        if ($player['player_uuid'] === $killedUuid) {
                            $player['is_alive'] = false;
                            break;
                        }
                    }

                    unset($player);
                }

                $game['winner'] = RoleAbility::checkWinCondition(
                    $game['players']
                );

                $game['night_actions'] = [];

                if ($game['winner'] !== null) {
                    $game['status'] = 'finished';
                    $game['current_phase'] = PhaseManager::PHASE_GAME_OVER;
                    $game['phase_end_time'] = null;
                    $room['status'] = 'ended';
                } else {
                    $game['current_round']++;
                    $game['current_phase'] = PhaseManager::PHASE_DAY_DISCUSSION;

                    // เริ่มจากค่าพื้นฐานทุกครั้ง ไม่ใช้ค่าที่ถูกปรับจากรอบเก่า
                    $dayConfig = $game['config'];
                    $game['day_event'] = null;

                    $nightEvent = $game['night_event'] ?? null;

                    if (
                        $nightEvent !== null
                        && $nightEvent['applies_to_round'] === $game['current_round']
                    ) {
                        $event = $nightEvent['event'];
                        $supported = $event['id'] !== 'second_chance';

                        // second_chance ยังรอเชื่อมโฟลว์โหวตใหม่
                        $dayConfig = GameConfiguration::applyRandomEvent(
                            $game['config'],
                            $event
                        );

                        $game['day_event'] = [
                            'id' => $event['id'],
                            'name' => $event['name'],
                            'description' => $event['description'],
                            'round' => $game['current_round'],
                            'applied' => $event['id'] !== 'none',
                        ];
                    }

                    // ป้องกันค่าทดสอบที่ลดเวลาแล้วเหลือศูนย์หรือติดลบ
                    $dayConfig['day_discussion_sec'] = max(
                        1,
                        (int) $dayConfig['day_discussion_sec']
                    );

                    $dayConfig['day_voting_sec'] = max(
                        1,
                        (int) $dayConfig['day_voting_sec']
                    );

                    $game['day_config'] = $dayConfig;

                    $game['phase_end_time'] = now()
                        ->addSeconds($dayConfig['day_discussion_sec'])
                        ->toIso8601String();

                    $room['status'] = 'day_discussion';
                }

                $room['game'] = $game;
                $this->saveGameRoom($room);
            });
    }

    public function leaveUnavailableGame(
        string $code,
        string $playerUuid
    ): void {
        $code = strtoupper(trim($code));

        Cache::store('file')->lock('room-lock:' . $code, 10)
            ->block(3, function () use ($code, $playerUuid) {
                DB::transaction(function () use ($code, $playerUuid) {
                    $room = Room::where('room_code', $code)
                        ->lockForUpdate()
                        ->firstOrFail();

                    $player = $room->players()
                        ->where('player_uuid', $playerUuid)
                        ->first();

                    if ($playerUuid !== null) {
                        abort_unless(
                            collect($room['players'])
                                ->contains('player_uuid', $playerUuid),
                            403,
                            'คุณไม่ได้อยู่ในห้องนี้'
                        );
                    }

                    // คำขอซ้ำหลังออกแล้ว ไม่แก้ข้อมูลเพิ่ม
                    if ($player->has_left) {
                        return;
                    }

                    $snapshot = $room->game_snapshot;

                    $snapshotMatches = is_array($snapshot)
                        && ($snapshot['game_uuid'] ?? null)
                            === $room->game_uuid;

                    if ($room->game_uuid === null || $snapshotMatches) {
                        throw ValidationException::withMessages([
                            'room' => 'ห้องนี้ไม่เข้าเงื่อนไขกู้ทางออก กรุณาใช้ปุ่มออกตามปกติ',
                        ]);
                    }

                    $wasHost = $player->is_host;

                    $player->update([
                        'has_left' => true,
                        'is_connected' => false,
                        'is_alive' => false,
                        'is_host' => false,
                    ]);

                    if ($wasHost) {
                        $nextHost = $room->players()
                            ->where('has_left', false)
                            ->orderBy('player_id')
                            ->first();

                        if ($nextHost !== null) {
                            $nextHost->update(['is_host' => true]);
                        }
                    }

                    // ห้องนี้เล่นต่อไม่ได้ แต่เก็บประวัติเดิมไว้
                    $room->update([
                        'room_status' => 'ended',
                        'room_phase_end_time' => null,
                    ]);
                });
            });
        $this->broadcastRoomUpdated($code);
    }

    public function finishDiscussion(
        string $code,
        string $playerUuid,
        string $expectedEndTime
    ): void {
        $this->resolveDiscussion($code, $playerUuid, $expectedEndTime);
    }

    public function finishVoting(
        string $code,
        string $playerUuid,
        string $expectedEndTime
    ): void {
        $this->resolveVoting($code, $playerUuid, $expectedEndTime);
    }

    public function finishNight(
        string $code,
        string $playerUuid,
        string $expectedEndTime
    ): void {
        $this->resolveNight($code, $playerUuid, $expectedEndTime);
    }

    public function advanceExpiredPhase(string $code): void
    {
        $room = $this->getRoom($code);
        $game = $room['game'] ?? null;

        if (
            $game === null
            || $game['status'] !== 'in_progress'
            || $game['phase_end_time'] === null
        ) {
            return;
        }

        $expectedEndTime = $game['phase_end_time'];

        if (now()->lt(
            \Carbon\CarbonImmutable::parse($expectedEndTime)
        )) {
            return;
        }

        switch ($game['current_phase']) {
            case 'day_discussion':
                $this->resolveDiscussion($code, null, $expectedEndTime);
                break;

            case 'day_voting':
                $this->resolveVoting($code, null, $expectedEndTime);
                break;

            case 'night':
                $this->resolveNight($code, null, $expectedEndTime);
                break;
        }
    }


    // websocket
    private function phaseBroadcastState(array $game): array
    {
        return [
            'game_uuid' => $game['game_uuid'],
            'status' => $game['status'],
            'phase' => $game['current_phase'],
            'round' => $game['current_round'],
            'ballot_number' => $game['ballot_number'] ?? 1,
            'phase_end_time' => $game['phase_end_time'],
        ];
    }

    private function broadcastRoomUpdated(string $code): void
    {
        DB::afterCommit(function () use ($code) {
            try {
                RoomUpdated::dispatch(strtoupper(trim($code)));
            } catch (\Throwable $exception) {
                report($exception);
            }
        });
    }

    private function roomMembershipState(array $game): array
    {
        return array_map(
            fn (array $player) => [
                'player_uuid' => $player['player_uuid'],
                'has_left' => $player['has_left'] ?? false,
            ],
            $game['players']
        );
    }

}
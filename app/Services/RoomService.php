<?php

namespace App\Services;

use App\Events\PhaseChanged;
use App\Events\RoomUpdated;
use App\Exceptions\GameSnapshotUnavailableException;
use App\GameLogic\GameConfiguration;
use App\GameLogic\GameEngine;
use App\GameLogic\MatchRules;
use App\GameLogic\MatchTimeline;
use App\GameLogic\PhaseManager;
use App\GameLogic\RandomEvent;
use App\GameLogic\RoleAbility;
use App\Models\Room;
use App\Models\VoteAction;
use App\Support\AvatarCatalog;
use App\Support\RoomLock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RoomService
{
    use MatchPresence;
    use VotingTrial;

    public function __construct(
        private RoomDatabaseService $roomDatabase
    ) {}

    public function create(
        string $hostName,
        string $hostUuid,
        string $difficulty,
        int $playerLimit = 4,
        string $gameMode = 'normal'
    ): array {
        $room = $this->roomDatabase->create(
            $hostName,
            $hostUuid,
            $difficulty,
            $playerLimit,
            $gameMode
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
                ! is_array($game)
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

        return RoomLock::make($code, 5)
            ->block(3, function () use (

                $code,
                $playerUuid,
                $gameService
            ) {
                $key = 'room:'.$code;
                $room = $this->getRoom($code);

                abort_unless(
                    collect($room['players'])
                        ->contains('player_uuid', $playerUuid),
                    403,
                    'คุณไม่ได้อยู่ในห้องนี้'
                );

                if (
                    $room['status'] !== 'waiting'
                    || ($room['game_uuid'] ?? null) !== null
                ) {
                    return $room;
                }

                if (
                    count($room['players']) !== $room['player_limit']
                    || ! collect($room['players'])->every(fn (array $player) => $player['is_ready'])
                    || $room['start_countdown_at'] === null
                    || now()->lt(CarbonImmutable::parse($room['start_countdown_at']))
                ) {
                    throw ValidationException::withMessages([
                        'room' => 'ต้องมีผู้เล่นครบ ทุกคนพร้อม และนับถอยหลังจบก่อน',
                    ]);
                }

                $game = $gameService->buildSession($room);

                $game['game_started_at'] = now()->toIso8601String();
                $game['match_end_time'] = $game['game_mode'] === 'short' ? now()->addSeconds(max(30, (int) config('game.short_duration_seconds', 480)))->toIso8601String() : null;
                $game['status'] = 'in_progress';
                $game['current_phase'] = PhaseManager::PHASE_DAY_DISCUSSION;
                $game['current_round'] = 1;
                $game['phase_end_time'] = now()->addSeconds($game['config']['day_discussion_sec'])->toIso8601String();
                $room['game_uuid'] = $game['game_uuid'];
                $room['status'] = $game['current_phase'];
                $room['game'] = $game;

                $this->saveGameRoom($room);

                return $room;
            });
    }

    public function setReady(string $code, string $playerUuid, bool $ready): void
    {
        $code = strtoupper(trim($code));
        RoomLock::make($code, 10)->block(3, function () use ($code, $playerUuid, $ready) {
            DB::transaction(function () use ($code, $playerUuid, $ready) {
                $record = Room::where('room_code', $code)->firstOrFail();
                $player = $record->players()->where('player_uuid', $playerUuid)->where('has_left', false)->first();
                abort_if($player === null, 403, 'คุณไม่ได้อยู่ในห้องนี้');
                if ($record->game_uuid !== null || $record->room_status !== 'waiting') {
                    throw ValidationException::withMessages(['room' => 'เกมเริ่มแล้ว']);
                }
                $player->update(['is_ready' => $ready]);
                $players = $record->players()->where('has_left', false)->get();
                $everyoneReady = $players->count() === $record->player_limit && $players->every(fn ($member) => $member->is_ready);
                $deadline = $everyoneReady
                    ? ($record->start_countdown_at ?? now()->addSeconds(max(1, (int) config('game.lobby_countdown_seconds', 5))))
                    : null;
                $record->update(['start_countdown_at' => $deadline]);
                $this->broadcastRoomUpdated($code);
            });
        });
    }

    public function updateAvatar(string $code, string $uuid, array $avatar): void
    {
        $code = strtoupper(trim($code));
        RoomLock::make($code, 10)->block(3, function () use ($code, $uuid, $avatar) {
            DB::transaction(function () use ($code, $uuid, $avatar) {
                $room = Room::where('room_code', $code)->firstOrFail();
                $player = $room->players()->where('player_uuid', $uuid)->where('has_left', false)->first();
                abort_unless($player !== null, 403);
                if ($room->game_uuid !== null || $room->room_status !== 'waiting' || $player->is_ready) {
                    throw ValidationException::withMessages(['avatar' => 'ยกเลิกพร้อมก่อนเปลี่ยนหน้าตา และเลือกได้ก่อนเริ่มเกมเท่านั้น']);
                }
                $player->update(['avatar' => AvatarCatalog::normalize($avatar)]);
                DB::afterCommit(fn () => $this->broadcastRoomUpdated($code));
            });
        });
    }

    public function advanceLobby(string $code): void
    {
        $room = $this->getRoom($code);
        if ($room['game_uuid'] !== null || $room['start_countdown_at'] === null || now()->lt(CarbonImmutable::parse($room['start_countdown_at']))) {
            return;
        }
        // start rechecks the full roster and readiness under the existing room lock.
        $this->start($code, $room['host_uuid'], app(GameService::class));
    }

    public function getGameView(
        string $code,
        string $playerUuid
    ): array {
        $room = $this->getRoom($code);

        $isMember = collect($room['players'])
            ->contains('player_uuid', $playerUuid);

        abort_unless(
            collect($room['players'])
                ->contains('player_uuid', $playerUuid),
            403,
            'คุณไม่ได้อยู่ในห้องนี้'
        );

        $game = $room['game'] ?? null;

        abort_if($game === null, 404, 'ห้องนี้ยังไม่ได้เริ่มเกม');

        $me = collect($game['players'])
            ->firstWhere('player_uuid', $playerUuid);

        abort_if($me === null, 403, 'คุณไม่ได้เป็นผู้เล่นในเกมนี้');

        $werewolfTeammates = [];

        if ($me['role'] === 'werewolf') {
            $werewolfTeammates = collect($game['players'])
                ->filter(fn (array $player) => $player['role'] === 'werewolf'
                    && $player['player_uuid'] !== $playerUuid
                )
                ->map(fn (array $player) => [
                    'player_uuid' => $player['player_uuid'],
                    'name' => $player['name'],
                    'avatar' => AvatarCatalog::normalize($player['avatar'] ?? null),
                    'is_alive' => $player['is_alive'],
                    'has_left' => $player['has_left'] ?? false,
                    'is_connected' => $player['is_connected'] ?? true,
                    'reconnect_deadline' => $player['reconnect_deadline'] ?? null,
                    'is_sick' => (bool) ($player['is_sick'] ?? false),
                    'sickness_death_round' => $player['sickness_death_round'] ?? null,
                    'death_reason' => $player['death_reason'] ?? null,
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
                'totals' => $result['totals'] ?? [],
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
            && ! ($me['has_left'] ?? false)
            && $game['phase_end_time'] !== null
            && now()->lt(
                CarbonImmutable::parse($game['phase_end_time'])
            );

        $werewolfTargets = [];

        if ($canWerewolfAct) {
            $werewolfTargets = collect($game['players'])
                ->filter(fn (array $player) => $player['is_alive']
                    && ! ($player['has_left'] ?? false)
                    && $player['role'] !== 'werewolf'
                )
                ->map(fn (array $player) => [
                    'player_uuid' => $player['player_uuid'],
                    'name' => $player['name'],
                    'avatar' => AvatarCatalog::normalize($player['avatar'] ?? null),
                ])
                ->values()
                ->all();
        }

        $seerUsed = $game['seer_checks_used'][$playerUuid] ?? 0;
        $seerLimit = $game['config']['seer_checks_limit'] ?? 1;

        // ตรวจว่าคืนนี้ Seer ได้ใช้สิทธิ์ไปแล้วหรือยัง
        $hasSeerActionTonight = isset(
            $game['night_actions'][$playerUuid]
        );

        $seerUsed = $game['seer_checks_used'][$playerUuid] ?? 0;
        $seerLimit = $game['config']['seer_checks_limit'] ?? 1;

        // ตรวจว่า Seer ใช้สิทธิ์ตรวจในคืนนี้ไปแล้วหรือยัง
        $hasSeerActionTonight = isset(
            $game['night_actions'][$playerUuid]
        );

        $canSeerAct =
        $game['status'] === 'in_progress'
        && $game['current_phase'] === 'night'
        && $me['role'] === 'seer'
        && $me['is_alive']
        && ! ($me['has_left'] ?? false)
        && ! $hasSeerActionTonight
        && $game['phase_end_time'] !== null
        && now()->lt(
            CarbonImmutable::parse($game['phase_end_time'])
        );

        $seerTargets = [];

        if ($canSeerAct) {
            $seerTargets = collect($game['players'])
                ->filter(fn (array $player) => $player['is_alive']
                    && ! ($player['has_left'] ?? false)
                )
                ->map(fn (array $player) => [
                    'player_uuid' => $player['player_uuid'],
                    'name' => $player['name'],
                    'avatar' => AvatarCatalog::normalize($player['avatar'] ?? null),
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
                'blocked_by' => $result['blocked_by'] ?? null,
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
            'voting_stage' => $game['voting_stage'] ?? 'accusation',
            'trial' => $game['current_phase'] === 'day_voting' && ($game['voting_stage'] ?? 'accusation') !== 'accusation' ? [
                'name' => collect($game['players'])->firstWhere('player_uuid', $game['trial']['target_uuid'])['name'] ?? 'ผู้เล่น',
                'is_defendant' => $game['trial']['target_uuid'] === $playerUuid,
                'defense' => $game['trial']['defense'],
                'my_verdict' => $game['trial']['votes'][$playerUuid] ?? null,
            ] : null,
            'trial_result' => $game['trial_result'] ?? null,
            'vote_history' => $game['vote_history'] ?? [],
            'event_history' => $game['event_history'] ?? [],

            'sickness_deaths' => array_values(array_map(fn ($p) => ['name' => $p['name']], array_filter($game['players'], fn ($p) => in_array($p['player_uuid'], $game['night_result']['sickness_deaths'] ?? [], true)))),
            'game_mode' => $game['game_mode'] ?? 'normal',
            'max_rounds' => $game['max_rounds'] ?? null,
            'match_end_time' => $game['match_end_time'] ?? null,
            'finish_reason' => $game['finish_reason'] ?? null,
            'evidence' => $game['evidence'] ?? [],
            'night_rule' => isset($game['night_rule']) ? array_replace($game['night_rule'], ['description' => MatchRules::nightDescription($game['night_rule'], $game['players'])]) : null,
            'secret_ballot' => (bool) ($game['day_config']['secret_ballot'] ?? false),
            'vote_totals' => ($game['current_phase'] === 'day_voting' && ! ($game['day_config']['secret_ballot'] ?? false)) ? array_count_values(array_values($votes)) : [],
            'can_guardian_act' => $game['status'] === 'in_progress' && $game['current_phase'] === 'night' && $me['role'] === 'guardian' && $me['is_alive'] && $game['phase_end_time'] !== null && now()->lt(CarbonImmutable::parse($game['phase_end_time'])),
            'guardian_targets' => $me['role'] === 'guardian' ? array_values(array_map(fn ($p) => ['player_uuid' => $p['player_uuid'], 'name' => $p['name']], array_filter($game['players'], fn ($p) => $p['is_alive'] && ! ($p['has_left'] ?? false) && $p['player_uuid'] !== ($game['guardian_last_targets'][$playerUuid] ?? null)))) : [],
            'my_guardian_target' => $me['role'] === 'guardian' ? ($game['night_actions'][$playerUuid]['target_id'] ?? null) : null,
            'sync_version' => hash('sha256', json_encode([$game['status'], $game['current_phase'], $game['current_round'], $game['phase_end_time'], $game['players'] ? array_map(fn ($p) => [$p['player_uuid'], $p['is_alive'], $p['has_left'] ?? false, $p['is_connected'] ?? true], $game['players']) : [], $game['discussion_skip_votes'] ?? [], $game['trial']['defense'] ?? null, ($game['day_config']['secret_ballot'] ?? false) ? [] : ($game['day_votes'] ?? [])])),
            'players' => array_map(
                fn (array $player) => [
                    'player_uuid' => $player['player_uuid'],
                    'name' => $player['name'],
                    'avatar' => AvatarCatalog::normalize($player['avatar'] ?? null),
                    'is_alive' => $player['is_alive'],
                    'has_left' => $player['has_left'] ?? false,
                    'is_connected' => $player['is_connected'] ?? true,
                    'reconnect_deadline' => $player['reconnect_deadline'] ?? null,
                    'is_sick' => (bool) ($player['is_sick'] ?? false),
                    'sickness_death_round' => $player['sickness_death_round'] ?? null,
                    'death_reason' => $player['death_reason'] ?? null,
                ],
                $game['players']
            ),
            'werewolf_teammates' => $werewolfTeammates,
            'can_begin_discussion' => $room['host_uuid'] === $playerUuid
                && $game['status'] === 'roles_assigned',
            'phase_end_time' => $game['phase_end_time'],
            'server_time' => now()->toIso8601String(),
            'my_vote' => $myVote,
            'discussion_skip_count' => count(array_filter($game['players'], fn (array $player) => $player['is_alive'] && ! ($player['has_left'] ?? false) && in_array($player['player_uuid'], $game['discussion_skip_votes'] ?? [], true))),
            'discussion_skip_required' => count(array_filter($game['players'], fn (array $player) => $player['is_alive'] && ! ($player['has_left'] ?? false))),
            'my_discussion_skip' => in_array($playerUuid, $game['discussion_skip_votes'] ?? [], true),
            'can_skip_discussion' => $game['status'] === 'in_progress' && $game['current_phase'] === 'day_discussion' && $me['is_alive'] && ! ($me['has_left'] ?? false) && $game['phase_end_time'] !== null && now()->lt(CarbonImmutable::parse($game['phase_end_time'])),
            'can_vote' => $game['status'] === 'in_progress'
                && $game['current_phase'] === 'day_voting'
                && ($game['voting_stage'] ?? 'accusation') === 'accusation'
                && $me['is_alive']
                && $game['phase_end_time'] !== null
                && now()->lt(
                    CarbonImmutable::parse($game['phase_end_time'])
                ),
            'can_werewolf_act' => $canWerewolfAct && ($game['night_rule']['id'] ?? null) !== 'peaceful_night',
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
            // Reveal the authoritative snapshot only after the match has finished.
            'game_result' => $game['status'] === 'finished' && $game['winner'] !== null
                ? [
                    'winner' => $game['winner'],
                    'players' => array_map(
                        fn (array $player) => [
                            'player_uuid' => $player['player_uuid'],
                            'name' => $player['name'],
                            'avatar' => AvatarCatalog::normalize($player['avatar'] ?? null),
                            'role' => $player['role'],
                            'is_alive' => $player['is_alive'],
                            'has_left' => $player['has_left'] ?? false,
                            'is_connected' => $player['is_connected'] ?? true,
                            'reconnect_deadline' => $player['reconnect_deadline'] ?? null,
                            'is_sick' => (bool) ($player['is_sick'] ?? false),
                            'sickness_death_round' => $player['sickness_death_round'] ?? null,
                            'death_reason' => $player['death_reason'] ?? null,
                        ],
                        $game['players']
                    ),
                ]
                : null,
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

        if (! collect($room['players'])->contains(
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

        RoomLock::make($code, 5)
            ->block(3, function () use ($code, $playerUuid) {
                $key = 'room:'.$code;
                $room = $this->getRoom($code);

                abort_unless(
                    collect($room['players'])
                        ->contains('player_uuid', $playerUuid),
                    403,
                    'คุณไม่ได้อยู่ในห้องนี้'
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

        RoomLock::make($code, 5)
            ->block(3, function () use (

                $code,
                $playerUuid,
                $expectedEndTime
            ) {
                $key = 'room:'.$code;
                $room = $this->getRoom($code);

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

                $deadline = CarbonImmutable::parse(
                    $game['phase_end_time']
                );

                if (now()->lt($deadline)) {
                    throw ValidationException::withMessages([
                        'game' => 'ยังไม่หมดเวลาพูดคุย',
                    ]);
                }

                $this->enterVoting($room, $game);
            });
    }

    private function enterVoting(array $room, array $game): void
    {
        // Constructor ของ P3 เริ่มที่ Discussion
        $phaseManager = new PhaseManager(
            count($game['players']),
            $game['difficulty']
        );

        $game['current_phase'] = $phaseManager->nextPhase();
        $game['day_votes'] = [];
        $game['voting_stage'] = 'accusation';
        $game['day_skip_votes'] = [];
        $game['discussion_skip_votes'] = [];
        $game['ballot_number'] = 1;

        $dayConfig = $game['day_config'] ?? $game['config'];

        $game['phase_end_time'] = now()
            ->addSeconds($dayConfig['day_voting_sec'])
            ->toIso8601String();

        $room['status'] = $game['current_phase'];
        $room['game'] = $game;

        $this->saveGameRoom($room);
    }

    private function discussionSkippedByEveryone(array $game): bool
    {
        $eligible = array_filter($game['players'], fn (array $player) => $player['is_alive'] && ! ($player['has_left'] ?? false));

        return count($eligible) > 0 && collect($eligible)->every(
            fn (array $player) => in_array($player['player_uuid'], $game['discussion_skip_votes'] ?? [], true)
        );
    }

    public function skipDiscussion(string $code, string $playerUuid, string $expectedEndTime): void
    {
        $code = strtoupper(trim($code));
        RoomLock::make($code, 10)->block(3, function () use ($code, $playerUuid, $expectedEndTime) {
            $room = $this->getRoom($code);
            abort_unless(collect($room['players'])->contains('player_uuid', $playerUuid), 403, 'คุณไม่ได้อยู่ในห้องนี้');
            $game = $room['game'] ?? null;
            abort_if($game === null, 404, 'ยังไม่มีเกม');
            $me = collect($game['players'])->firstWhere('player_uuid', $playerUuid);
            abort_unless($me !== null && $me['is_alive'] && ! ($me['has_left'] ?? false), 403, 'เฉพาะผู้เล่นที่มีชีวิตและยังอยู่ในเกม');
            if ($game['status'] !== 'in_progress' || $game['current_phase'] !== 'day_discussion' || $game['phase_end_time'] !== $expectedEndTime || now()->gte(CarbonImmutable::parse($expectedEndTime))) {
                throw ValidationException::withMessages(['game' => 'ช่วงพูดคุยเปลี่ยนแล้วหรือหมดเวลา']);
            }
            $game['discussion_skip_votes'] = array_values(array_unique([
                ...($game['discussion_skip_votes'] ?? []), $playerUuid,
            ]));
            if ($this->discussionSkippedByEveryone($game)) {
                $this->enterVoting($room, $game);
            } else {
                $room['game'] = $game;
                $this->saveGameRoom($room);
                $this->broadcastRoomUpdated($code);
            }
        });
    }

    public function vote(
        string $code,
        string $playerUuid,
        string $targetUuid,
        string $expectedEndTime
    ): void {
        $code = strtoupper(trim($code));

        RoomLock::make($code, 10)
            ->block(3, function () use (
                $code,
                $playerUuid,
                $targetUuid,
                $expectedEndTime
            ) {
                $room = $this->getRoom($code);
                $game = $room['game'] ?? null;

                abort_if($game === null, 404, 'ยังไม่มีเกม');

                abort_unless(
                    collect($room['players'])
                        ->contains('player_uuid', $playerUuid),
                    403,
                    'คุณไม่ได้อยู่ในห้องนี้'
                );

                if (
                    $game['phase_end_time'] === null
                    || $game['phase_end_time'] !== $expectedEndTime
                ) {
                    throw ValidationException::withMessages([
                        'vote' => 'ช่วงเวลาเกมเปลี่ยนแล้ว กรุณารีเฟรช',
                    ]);
                }

                if (($game['voting_stage'] ?? 'accusation') !== 'accusation') {
                    throw ValidationException::withMessages(['vote' => 'ขณะนี้เป็นช่วงแก้ต่างหรือยืนยัน']);
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
                if ($targetUuid === 'skip') {
                    $room['game']['day_skip_votes'][$playerUuid] = true;
                } else {
                    unset($room['game']['day_skip_votes'][$playerUuid]);
                }

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

        RoomLock::make($code, 5)
            ->block(3, function () use (

                $code,
                $playerUuid,
                $expectedEndTime
            ) {
                $key = 'room:'.$code;
                $room = $this->getRoom($code);

                $game = $room['game'] ?? null;

                abort_if($game === null, 404, 'ยังไม่มีเกม');

                if ($playerUuid !== null) {
                    abort_unless(
                        collect($game['players'])
                            ->filter(fn (array $player) => ! ($player['has_left'] ?? false))
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
                    CarbonImmutable::parse($game['phase_end_time'])
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

                $stage = $game['voting_stage'] ?? 'accusation';
                if ($stage === 'accusation') {
                    $game = $this->recordBallot($game);
                    $engine = new GameEngine($game);
                    $outcome = $engine->resolveDayVoteOutcome();
                } else {
                    $outcome = $this->trialOutcome($game);
                    if ($outcome === null) {
                        $room['game'] = $game;
                        $this->saveGameRoom($room);

                        return;
                    }
                }

                $dayConfig = $game['day_config'] ?? $game['config'];

                if ($outcome['requires_revote']) {
                    $game['ballot_number'] = 2;
                    $game['day_votes'] = [];
                    $game['day_skip_votes'] = [];

                    $game['phase_end_time'] = now()
                        ->addSeconds($dayConfig['day_voting_sec'])
                        ->toIso8601String();

                    $room['game'] = $game;
                    $this->saveGameRoom($room);

                    return;
                }

                $targetUuid = $outcome['eliminated_uuid'];
                if ($targetUuid !== null && $stage === 'accusation' && ($game['defense_enabled'] ?? config('game.defense_enabled', true))) {
                    $game['voting_stage'] = 'defense';
                    $game['trial'] = ['target_uuid' => $targetUuid, 'defense' => null, 'votes' => []];
                    $game['phase_end_time'] = now()->addSeconds(max(1, (int) config('game.defense_seconds', 30)))->toIso8601String();
                    $room['game'] = $game;
                    $this->saveGameRoom($room);

                    return;
                }

                if ($targetUuid === null) {
                    $game = MatchRules::infectAfterNoElimination($game);
                }

                $game['vote_result'] = [
                    'round' => $game['current_round'],
                    'eliminated_uuid' => $targetUuid,
                    'totals' => array_count_values(array_values($game['day_votes'])),
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
                        ->addSeconds($game['config']['night_sec'] ?? $phaseManager->getPhaseDuration())
                        ->toIso8601String();

                    $game['night_rule'] = MatchRules::nightEvent($game['players']);
                    $game['night_actions'] = [];
                    $game['seer_checks_used'] = [];
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
            $game = MatchTimeline::update($game, is_array($previousGame) ? $previousGame : null);

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
                'start_countdown_at' => null,
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

                $isSkip = $voteData['target_uuid'] === 'skip';
                $target = $isSkip ? null : $record->players()
                    ->where('player_uuid', $voteData['target_uuid'])
                    ->firstOrFail();

                $ballotKey = [
                    'rooms_room_id' => $record->room_id,
                    'phase_number' => $game['current_round'],
                    'phase_type' => $game['current_phase'],
                    'action_type' => $voteData['action_type'] ?? 'vote_lynch',
                    'players_voter_id' => $voter->player_id,
                    'ballot_number' => $game['current_phase'] === 'day_voting'
                    ? ($game['ballot_number'] ?? 1)
                    : 1,
                ];
                if ($isSkip) {
                    VoteAction::where($ballotKey)->delete();
                } else {
                    VoteAction::updateOrCreate($ballotKey, ['players_target_id' => $target->player_id]);
                }
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
            ->filter(fn (array $player) => ! ($player['has_left'] ?? false))
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
                ! in_array($voterUuid, $aliveUuids, true)
                || ! in_array($targetUuid, $aliveUuids, true)
            ) {
                continue;
            }

            $votes[$voterUuid] = $targetUuid;
        }

        $snapshot = $record->game_snapshot;
        if (($snapshot['current_round'] ?? null) === $round && ($snapshot['ballot_number'] ?? 1) === $ballotNumber) {
            foreach ($snapshot['day_skip_votes'] ?? [] as $uuid => $skipped) {
                if ($skipped && in_array($uuid, $aliveUuids, true)) {
                    $votes[$uuid] = 'skip';
                }
            }
        }

        return $votes;
    }

    public function disconnectForDebug(
        string $code,
        string $playerUuid
    ): string {
        $code = strtoupper($code);

        return RoomLock::make($code, 10)
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
                    ! ($player['is_connected'] ?? true)
                    && ! empty($player['reconnect_deadline'])
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

        RoomLock::make($code, 10)
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
                    fn (array $player) => $player['player_uuid'] !== $playerUuid
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

                if ($game['status'] === 'in_progress' && $game['current_phase'] === 'day_discussion' && $this->discussionSkippedByEveryone($game)) {
                    $this->enterVoting($room, $game);

                    return;
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

        RoomLock::make($code, 10)
            ->block(3, function () use (
                $code,
                $playerUuid,
                $targetUuid,
                $expectedEndTime
            ) {
                $room = $this->getRoom($code);
                $game = $room['game'] ?? null;

                abort_if($game === null, 404, 'ยังไม่มีเกม');

                abort_unless(
                    collect($room['players'])
                        ->contains('player_uuid', $playerUuid),
                    403,
                    'คุณไม่ได้อยู่ในห้องนี้'
                );

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

        RoomLock::make($code, 10)
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
                abort_unless(
                    collect($room['players'])
                        ->contains('player_uuid', $playerUuid),
                    403,
                    'คุณไม่ได้อยู่ในห้องนี้'
                );

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

        RoomLock::make($code, 10)
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
                    CarbonImmutable::parse($game['phase_end_time'])
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
                        || ! $actor['is_alive']
                        || ! $target['is_alive']
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
                    $validSeerActions,
                    array_filter($game['night_actions'] ?? [], fn (array $action) => ($action['role'] ?? null) === 'guardian')
                );

                $engine = new GameEngine($resolutionSnapshot);

                $nightOutcome = $engine->resolveNightKillOutcome();
                $seerOutcome = $engine->resolveSeerOutcome();

                $killedUuid = $nightOutcome['killed_uuid'];

                $game['guardian_last_targets'] = $nightOutcome['guardian_last_targets'];
                $game['seer_results'] = $seerOutcome['seer_results'];
                $game['seer_checks_used'] = $seerOutcome['seer_checks_used'];

                $game['night_result'] = [
                    'round' => $game['current_round'],
                    'killed_uuid' => $killedUuid,
                    'blocked_by' => $nightOutcome['blocked_by'] ?? null,
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

                $game = MatchRules::resolveSickness($game);

                $game['winner'] = RoleAbility::checkWinCondition(
                    $game['players']
                );

                $game = MatchRules::addEvidence($game);
                $game = MatchRules::applyTimeLimit($game, true);
                $game['night_actions'] = [];

                if ($game['winner'] !== null) {
                    $game['status'] = 'finished';
                    $game['current_phase'] = PhaseManager::PHASE_GAME_OVER;
                    $game['phase_end_time'] = null;
                    $room['status'] = 'ended';
                } else {
                    $game['current_round']++;
                    $game['discussion_skip_votes'] = [];
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
                        $supported = true;

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

        RoomLock::make($code, 10)
            ->block(3, function () use ($code, $playerUuid) {
                DB::transaction(function () use ($code, $playerUuid) {
                    $room = Room::where('room_code', $code)
                        ->lockForUpdate()
                        ->firstOrFail();

                    $player = $room->players()
                        ->where('player_uuid', $playerUuid)
                        ->first();

                    abort_if($player === null, 403, 'คุณไม่ได้อยู่ในห้องนี้');

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
            CarbonImmutable::parse($expectedEndTime)
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

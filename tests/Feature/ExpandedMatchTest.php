<?php

use App\GameLogic\GameConfiguration;
use App\GameLogic\MatchRules;
use App\GameLogic\RandomEvent;
use App\Models\Room;
use App\Services\RoomService;
use Illuminate\Support\Str;

require_once __DIR__.'/../Support/MatchFixtures.php';

function prepareGuardianNight(): array
{
    [$service, $code, $uuids, $deadline] = prepareSkipGame('night');
    $record = Room::where('room_code', $code)->firstOrFail();
    $game = $record->game_snapshot;
    $game['players'][2]['role'] = 'guardian';
    $game['night_rule'] = ['id' => 'normal_night', 'name' => 'คืนปกติ', 'description' => 'หมาป่าโจมตีได้'];
    $record->update(['game_snapshot' => $game]);

    return [$service, $code, $uuids, $deadline];
}

it('protects a targeted player and publishes a truthful public report', function () {
    [$service, $code, $uuids, $deadline] = prepareGuardianNight();
    $service->werewolfAction($code, $uuids[0], $uuids[3], $deadline);
    $this->withSession(['player_uuid' => $uuids[2]])->post(route('games.guardian-action', ['code' => $code]), ['target_uuid' => $uuids[3], 'expected_end_time' => $deadline])->assertRedirect();
    expireSkipPhase($code);
    $game = Room::where('room_code', $code)->firstOrFail()->game_snapshot;
    expect($game['players'][3]['is_alive'])->toBeTrue()
        ->and($game['night_result']['blocked_by'])->toBe('guardian')
        ->and($game['guardian_last_targets'][$uuids[2]])->toBe($uuids[3])
        ->and($game['evidence'][0]['message'])->toContain('ได้รับการป้องกัน');
    $this->withoutVite()->withSession(['player_uuid' => $uuids[3]])->get(route('games.show', ['code' => $code]))->assertOk()->assertSee('กระดานหลักฐาน');
});

it('rejects consecutive guardian targets, wrong roles, and stale deadlines', function () {
    [$service, $code, $uuids, $deadline] = prepareGuardianNight();
    $record = Room::where('room_code', $code)->firstOrFail();
    $game = $record->game_snapshot;
    $game['guardian_last_targets'][$uuids[2]] = $uuids[3];
    $record->update(['game_snapshot' => $game]);
    $this->withSession(['player_uuid' => $uuids[2]])->postJson(route('games.guardian-action', ['code' => $code]), ['target_uuid' => $uuids[3], 'expected_end_time' => $deadline])->assertUnprocessable();
    $this->postJson(route('games.guardian-action', ['code' => $code]), ['target_uuid' => $uuids[4], 'expected_end_time' => $deadline])->assertRedirect();
    $this->withSession(['player_uuid' => $uuids[1]])->postJson(route('games.guardian-action', ['code' => $code]), ['target_uuid' => $uuids[4], 'expected_end_time' => $deadline])->assertUnprocessable();
    $view = $service->getGameView($code, $uuids[1]);
    expect($view['guardian_targets'])->toBe([])->and($view['my_guardian_target'])->toBeNull();
    $this->withSession(['player_uuid' => $uuids[2]])->postJson(route('games.guardian-action', ['code' => $code]), ['target_uuid' => $uuids[4], 'expected_end_time' => now()->addHour()->toIso8601String()])->assertUnprocessable();
});

it('enforces peaceful nights even against already recorded wolf actions', function () {
    [$service, $code, $uuids, $deadline] = prepareGuardianNight();
    $service->werewolfAction($code, $uuids[0], $uuids[3], $deadline);
    $record = Room::where('room_code', $code)->firstOrFail();
    $game = $record->game_snapshot;
    $game['night_rule'] = ['id' => 'peaceful_night', 'name' => 'คืนสงบ', 'description' => 'หมาป่าโจมตีไม่ได้'];
    $record->update(['game_snapshot' => $game]);
    $this->withSession(['player_uuid' => $uuids[0]])->postJson(route('games.werewolf-action', ['code' => $code]), ['target_uuid' => $uuids[4], 'expected_end_time' => $deadline])->assertUnprocessable();
    expect($service->getGameView($code, $uuids[0])['can_werewolf_act'])->toBeFalse();
    expireSkipPhase($code);
    expect($record->fresh()->game_snapshot['night_result']['blocked_by'])->toBe('peaceful_night')
        ->and($record->fresh()->game_snapshot['players'][3]['is_alive'])->toBeTrue();
});

it('limits the suspect clue to one truthful ambiguous group and keeps roles private', function () {
    [$service, $code, $uuids] = prepareGuardianNight();
    $game = Room::where('room_code', $code)->firstOrFail()->game_snapshot;
    $game['night_result'] = ['killed_uuid' => null];
    $game = MatchRules::addEvidence($game);
    $group = collect($game['evidence'])->firstWhere('type', 'suspect_group');
    expect($group['players'])->toHaveCount(3);
    $roles = collect($game['players'])->keyBy('player_uuid');
    expect(collect($group['players'])->filter(fn ($p) => $roles[$p['player_uuid']]['role'] === 'werewolf')->count())->toBe(1);
    foreach ($group['players'] as $suspect) {
        expect($suspect)->not->toHaveKey('role');
    }
    $game['current_round']++;
    $game = MatchRules::addEvidence($game);
    expect(collect($game['evidence'])->where('type', 'suspect_group')->count())->toBe(1)
        ->and(collect($game['evidence'])->where('type', 'night_report')->count())->toBe(2);
});

it('hides vote totals and vote-change signals on secret-ballot days then reveals the result', function () {
    [$service, $code, $uuids, $deadline] = prepareSkipGame();
    $record = Room::where('room_code', $code)->firstOrFail();
    $game = $record->game_snapshot;
    $game['day_config'] = GameConfiguration::applyRandomEvent($game['config'], RandomEvent::get('secret_ballot', 'hard', 5));
    $record->update(['game_snapshot' => $game]);
    $before = $service->getGameView($code, $uuids[1]);
    $service->vote($code, $uuids[2], 'skip', $deadline);
    $after = $service->getGameView($code, $uuids[1]);
    expect($after['vote_totals'])->toBe([])->and($after['sync_version'])->toBe($before['sync_version']);
    $this->withoutVite()->withSession(['player_uuid' => $uuids[1]])->get(route('games.show', ['code' => $code]))->assertOk()->assertSee('วันลงคะแนนลับ')->assertDontSee('คะแนนรวมระหว่างโหวต');
    expireSkipPhase($code);
    expect($service->getGameView($code, $uuids[1])['vote_result']['totals'])->toBe(['skip' => 1]);
});

it('makes second-chance events effective', function () {
    $settings = GameConfiguration::applyRandomEvent(['day_discussion_sec' => 20, 'day_voting_sec' => 25, 'tie_breaking' => 'no_death'], RandomEvent::get('second_chance', 'easy', 5));
    expect($settings['tie_breaking'])->toBe('revote_once');
});

it('ends short matches on the time limit and preserves the winner afterward', function () {
    [$service, $code, $uuids] = prepareSkipGame('day_discussion');
    $record = Room::where('room_code', $code)->firstOrFail();
    $game = $record->game_snapshot;
    $game['game_mode'] = 'short';
    $game['match_end_time'] = now()->subSecond()->toIso8601String();
    $game['max_rounds'] = 3;
    $record->update(['game_snapshot' => $game]);
    $service->maintainMatch($code, $uuids[1]);
    $game = $record->fresh()->game_snapshot;
    expect($game['winner'])->toBe('villager')->and($game['finish_reason'])->toBe('time_limit')->and($game['phase_end_time'])->toBeNull();
    $service->leaveGame($code, $uuids[1]);
    expect($record->fresh()->game_snapshot['winner'])->toBe('villager');
});

it('ends short matches after the final night of the configured last round', function () {
    [$service, $code] = prepareGuardianNight();
    $record = Room::where('room_code', $code)->firstOrFail();
    $game = $record->game_snapshot;
    $game['game_mode'] = 'short';
    $game['match_end_time'] = now()->addMinutes(8)->toIso8601String();
    $game['current_round'] = 3;
    $game['max_rounds'] = 3;
    $record->update(['game_snapshot' => $game]);
    expireSkipPhase($code);
    expect($record->fresh()->game_snapshot['finish_reason'])->toBe('round_limit')->and($record->fresh()->game_snapshot['status'])->toBe('finished');
});

it('reconnects within grace using the same role and expires players beyond grace', function () {
    [$service, $code, $uuids] = prepareSkipGame('day_discussion');
    $record = Room::where('room_code', $code)->firstOrFail();
    $game = $record->game_snapshot;
    $role = $game['players'][4]['role'];
    $game['players'][4]['last_seen_at'] = now()->subSeconds(30)->toIso8601String();
    $record->update(['game_snapshot' => $game]);
    $service->maintainMatch($code, $uuids[1]);
    expect($record->fresh()->game_snapshot['players'][4]['is_connected'])->toBeFalse();
    $this->withSession(['player_uuid' => $uuids[4]])->postJson(route('games.presence', ['code' => $code]))->assertOk();
    expect($record->fresh()->game_snapshot['players'][4]['role'])->toBe($role)->and($record->fresh()->game_snapshot['players'][4]['is_connected'])->toBeTrue();
    $game = $record->fresh()->game_snapshot;
    $game['players'][4]['last_seen_at'] = now()->subSeconds(90)->toIso8601String();
    $record->update(['game_snapshot' => $game]);
    $this->postJson(route('games.presence', ['code' => $code]))->assertForbidden();
    expect($record->fresh()->game_snapshot['players'][4]['has_left'])->toBeTrue();
    $this->withSession(['player_uuid' => (string) Str::uuid()])->postJson(route('games.presence', ['code' => $code]))->assertForbidden();
});

it('validates short mode and exposes short-mode settings when creating a room', function () {
    $this->post(route('rooms.store'), ['host_name' => 'Host', 'difficulty' => 'easy', 'player_limit' => 5, 'game_mode' => 'short'])->assertRedirect();
    expect(Room::firstOrFail()->game_mode)->toBe('short');
    $this->postJson(route('rooms.store'), ['host_name' => 'Host', 'difficulty' => 'easy', 'player_limit' => 5, 'game_mode' => 'bad'])->assertUnprocessable();
});

it('allows protecting an old target after skipping the intervening night', function () {
    [$service, $code, $uuids, $deadline] = prepareGuardianNight();
    $record = Room::where('room_code', $code)->firstOrFail();
    $game = $record->game_snapshot;
    $game['guardian_last_targets'][$uuids[2]] = $uuids[3];
    $game['night_actions'] = [];
    $record->update(['game_snapshot' => $game]);
    expireSkipPhase($code);
    $game = $record->fresh()->game_snapshot;
    expect($game['guardian_last_targets'])->toBe([]);
    $game['current_phase'] = 'night';
    $game['phase_end_time'] = now()->addMinute()->toIso8601String();
    $record->update(['game_snapshot' => $game]);
    $service->guardianAction($code, $uuids[2], $uuids[3], $game['phase_end_time']);
    expect($record->fresh()->game_snapshot['night_actions'][$uuids[2]]['target_id'])->toBe($uuids[3]);
});

it('uses normal wolf victory conditions at the short-match deadline', function () {
    [$service, $code, $uuids] = prepareSkipGame();
    $record = Room::where('room_code', $code)->firstOrFail();
    $game = $record->game_snapshot;
    $game['game_mode'] = 'short';
    $game['match_end_time'] = now()->subSecond()->toIso8601String();
    foreach ([2, 3, 4] as $index) {
        $game['players'][$index]['is_alive'] = false;
    }
    $record->update(['game_snapshot' => $game]);
    $service->maintainMatch($code, $uuids[0]);
    expect($record->fresh()->game_snapshot['winner'])->toBe('werewolf');
});

it('starts short rooms with a server deadline and shorter phase settings', function () {
    config(['game.short_duration_seconds' => 180, 'game.short_max_rounds' => 2]);
    $service = app(RoomService::class);
    $uuids = array_map(fn () => (string) Str::uuid(), range(1, 5));
    $room = $service->create('Host', $uuids[0], 'easy', 5, 'short');
    foreach (array_slice($uuids, 1) as $index => $uuid) {
        $service->join($room['code'], 'Guest '.$index, $uuid);
    }
    foreach ($uuids as $uuid) {
        $service->setReady($room['code'], $uuid, true);
    }
    Room::where('room_code', $room['code'])->update(['start_countdown_at' => now()->subSecond()]);
    $service->advanceLobby($room['code']);
    $game = Room::where('room_code', $room['code'])->firstOrFail()->game_snapshot;
    expect($game['game_mode'])->toBe('short')->and($game['max_rounds'])->toBe(2)
        ->and(strtotime($game['match_end_time']) - strtotime($game['game_started_at']))->toBe(180)
        ->and($game['config']['day_discussion_sec'])->toBe(30);
    $this->withoutVite()->withSession(['player_uuid' => $uuids[0]])->get(route('games.show', ['code' => $room['code']]))->assertOk()->assertSee('โหมดเกมสั้น');
});

it('applies the announced second-chance event on the next morning', function () {
    [$service, $code, $uuids] = prepareGuardianNight();
    $record = Room::where('room_code', $code)->firstOrFail();
    $game = $record->game_snapshot;
    $game['difficulty'] = 'easy';
    $game['night_event'] = ['night_round' => 1, 'applies_to_round' => 2, 'event' => RandomEvent::get('second_chance', 'easy', 5)];
    $record->update(['game_snapshot' => $game]);
    expireSkipPhase($code);
    $game = $record->fresh()->game_snapshot;
    expect($game['day_config']['tie_breaking'])->toBe('revote_once')->and($game['day_event']['applied'])->toBeTrue();
    config(['game.peaceful_night_chance' => 100]);
    expect(MatchRules::nightEvent()['id'])->toBe('peaceful_night');
});

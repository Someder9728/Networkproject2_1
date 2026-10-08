<?php

use App\GameLogic\DifficultyConfig;
use App\Models\Room;
use App\Models\VoteAction;
use App\Services\RoomService;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

require_once __DIR__.'/../Support/MatchFixtures.php';

it('supports five players in both difficulties', function (string $difficulty) {
    [$service, $code, $uuids] = prepareReadyLobby(5, $difficulty);
    expect(DifficultyConfig::supportedPlayerCounts())->toBe([4, 5, 6, 7, 8, 9, 10]);
    foreach ($uuids as $uuid) {
        $service->setReady($code, $uuid, true);
    }
    $record = Room::where('room_code', $code)->firstOrFail();
    expect($record->start_countdown_at)->not->toBeNull()->and($record->game_uuid)->toBeNull();
    $record->update(['start_countdown_at' => now()->subSecond()]);
    $this->artisan('game:advance-phases')->assertSuccessful();
    $game = $record->fresh()->game_snapshot;
    expect($game['status'])->toBe('in_progress')
        ->and($game['current_phase'])->toBe('day_discussion')
        ->and($game['players'])->toHaveCount(5)
        ->and(array_count_values(array_column($game['players'], 'role')))->toEqual(['werewolf' => 1, 'seer' => 1, 'guardian' => 1, 'villager' => 2]);
})->with(['easy', 'hard']);

it('requires a full room and every ready vote before a countdown', function () {
    $service = app(RoomService::class);
    $host = (string) Str::uuid();
    $room = $service->create('Host', $host, 'easy', 5);
    $service->setReady($room['code'], $host, true);
    expect($service->getRoom($room['code'])['start_countdown_at'])->toBeNull();
    $this->withSession(['player_uuid' => $host])->post(route('rooms.start', ['code' => $room['code']]))->assertSessionHasErrors('room');
    [$service, $code, $uuids] = prepareReadyLobby();
    foreach (array_slice($uuids, 0, 4) as $uuid) {
        $service->setReady($code, $uuid, true);
    }
    expect($service->getRoom($code)['start_countdown_at'])->toBeNull();
    $service->setReady($code, $uuids[4], true);
    $deadline = $service->getRoom($code)['start_countdown_at'];
    $service->setReady($code, $uuids[4], true);
    expect($service->getRoom($code)['start_countdown_at'])->toBe($deadline);
    $this->withSession(['player_uuid' => $uuids[0]])->post(route('rooms.start', ['code' => $code]))->assertSessionHasErrors('room');
});

it('cancels countdown when readiness or membership changes', function () {
    [$service, $code, $uuids] = prepareReadyLobby();
    foreach ($uuids as $uuid) {
        $service->setReady($code, $uuid, true);
    }
    $service->setReady($code, $uuids[4], false);
    expect($service->getRoom($code)['start_countdown_at'])->toBeNull();
    $service->setReady($code, $uuids[4], true);
    $service->leave($code, $uuids[4]);
    expect($service->getRoom($code)['start_countdown_at'])->toBeNull();
    $service->advanceLobby($code);
    expect($service->getRoom($code)['game_uuid'])->toBeNull();
    $newcomer = (string) Str::uuid();
    $service->join($code, 'Newcomer', $newcomer);
    expect($service->getRoom($code)['start_countdown_at'])->toBeNull();
});

it('checks readiness access, capacity, and starts only once through polling', function () {
    [$service, $code, $uuids] = prepareReadyLobby();
    $this->withSession(['player_uuid' => (string) Str::uuid()])->postJson(route('rooms.ready', ['code' => $code]), ['ready' => true])->assertForbidden();
    $this->postJson(route('rooms.advance-start', ['code' => $code]))->assertForbidden();
    expect(fn () => $service->join($code, 'Extra', (string) Str::uuid()))->toThrow(ValidationException::class);
    foreach ($uuids as $uuid) {
        $service->setReady($code, $uuid, true);
    }
    $this->withoutVite()->withSession(['player_uuid' => $uuids[0]])->get(route('rooms.show', ['code' => $code]))
        ->assertOk()->assertSee('ยกเลิกพร้อม')->assertSee('lobby-countdown');
    Room::where('room_code', $code)->update(['start_countdown_at' => now()->subSecond()]);
    $this->postJson(route('rooms.advance-start', ['code' => $code]))->assertOk()->assertJsonPath('game_url', route('games.show', ['code' => $code]));
    $firstGame = Room::where('room_code', $code)->firstOrFail()->game_uuid;
    $this->postJson(route('rooms.advance-start', ['code' => $code]))->assertOk();
    expect(Room::where('room_code', $code)->firstOrFail()->game_uuid)->toBe($firstGame);
    $this->postJson(route('rooms.ready', ['code' => $code]), ['ready' => false])->assertUnprocessable();
});

it('creates a room with its requested player limit', function () {
    $this->post(route('rooms.store'), ['host_name' => 'Host', 'difficulty' => 'easy', 'player_limit' => 5])->assertRedirect();
    expect(Room::firstOrFail()->player_limit)->toBe(5);
    $this->post(route('rooms.store'), ['host_name' => 'Host', 'difficulty' => 'easy', 'player_limit' => 11])->assertSessionHasErrors('player_limit');
});

it('uses the configurable server countdown duration in the deadline and page', function () {
    config(['game.lobby_countdown_seconds' => 12]);
    $this->withoutVite()->get(route('rooms.index'))->assertOk()->assertSee('นับถอยหลัง 12 วินาที');
    [$service, $code, $uuids] = prepareReadyLobby();
    foreach ($uuids as $uuid) {
        $service->setReady($code, $uuid, true);
    }
    $record = Room::where('room_code', $code)->firstOrFail();
    expect($record->start_countdown_at->getTimestamp() - now()->getTimestamp())->toBe(12);
});

it('requires unanimous discussion skip and counts each player once', function () {
    [$service, $code, $uuids, $deadline] = prepareSkipGame('day_discussion');
    $this->withoutVite()->withSession(['player_uuid' => $uuids[0]])->get(route('games.show', ['code' => $code]))->assertOk()->assertSee('Skip Discussion');
    $service->skipDiscussion($code, $uuids[0], $deadline);
    $service->skipDiscussion($code, $uuids[0], $deadline);
    expect($service->getGameView($code, $uuids[0])['discussion_skip_count'])->toBe(1);
    foreach (array_slice($uuids, 1, 3) as $uuid) {
        $service->skipDiscussion($code, $uuid, $deadline);
    }
    expect($service->getGameView($code, $uuids[0])['current_phase'])->toBe('day_discussion');
    $this->withSession(['player_uuid' => $uuids[4]])->post(route('games.skip-discussion', ['code' => $code]), ['expected_end_time' => $deadline])->assertRedirect();
    $game = Room::where('room_code', $code)->firstOrFail()->game_snapshot;
    expect($game['current_phase'])->toBe('day_voting')->and($game['discussion_skip_votes'])->toBe([]);
    $this->postJson(route('games.skip-discussion', ['code' => $code]), ['expected_end_time' => $deadline])->assertUnprocessable();
});

it('rejects dead and nonmember discussion skips and ignores dead players in consensus', function () {
    [$service, $code, $uuids, $deadline] = prepareSkipGame('day_discussion');
    $record = Room::where('room_code', $code)->firstOrFail();
    $game = $record->game_snapshot;
    $game['players'][4]['is_alive'] = false;
    $record->update(['game_snapshot' => $game]);
    $this->withSession(['player_uuid' => $uuids[4]])->postJson(route('games.skip-discussion', ['code' => $code]), ['expected_end_time' => $deadline])->assertForbidden();
    $this->withSession(['player_uuid' => (string) Str::uuid()])->postJson(route('games.skip-discussion', ['code' => $code]), ['expected_end_time' => $deadline])->assertForbidden();
    foreach (array_slice($uuids, 0, 4) as $uuid) {
        $service->skipDiscussion($code, $uuid, $deadline);
    }
    expect($record->fresh()->game_snapshot['current_phase'])->toBe('day_voting');
});

it('rechecks discussion consensus when the last unconsenting player leaves', function () {
    [$service, $code, $uuids, $deadline] = prepareSkipGame('day_discussion');
    foreach (array_slice($uuids, 0, 4) as $uuid) {
        $service->skipDiscussion($code, $uuid, $deadline);
    }
    $service->leaveGame($code, $uuids[4]);
    expect($service->getGameView($code, $uuids[0])['current_phase'])->toBe('day_voting');
});

it('persists skip ballots and permits switching between skip and a target', function () {
    [$service, $code, $uuids, $deadline] = prepareSkipGame();
    $service->vote($code, $uuids[2], $uuids[0], $deadline);
    expect(VoteAction::count())->toBe(1);
    $this->withSession(['player_uuid' => $uuids[2]])->post(route('games.vote', ['code' => $code]), ['expected_end_time' => $deadline, 'target_uuid' => 'skip'])->assertRedirect();
    expect(VoteAction::count())->toBe(0)->and($service->getGameView($code, $uuids[2])['my_vote'])->toBe('skip');
    $this->withoutVite()->get(route('games.show', ['code' => $code]))->assertOk()->assertSee('คุณโหวต Skip แล้ว');
    $service->vote($code, $uuids[2], $uuids[0], $deadline);
    expect(VoteAction::count())->toBe(1)->and($service->getGameView($code, $uuids[2])['my_vote'])->toBe($uuids[0]);
    $this->postJson(route('games.vote', ['code' => $code]), ['expected_end_time' => $deadline, 'target_uuid' => 'bogus'])->assertUnprocessable();
    $this->postJson(route('games.vote', ['code' => $code]), ['expected_end_time' => now()->addHour()->toIso8601String(), 'target_uuid' => 'skip'])->assertUnprocessable();
});

it('does not eliminate a player when skip leads or ties even under random ties', function (array $ballots) {
    [$service, $code, $uuids, $deadline] = prepareSkipGame();
    $record = Room::where('room_code', $code)->firstOrFail();
    $game = $record->game_snapshot;
    $game['day_config']['tie_breaking'] = 'random';
    $record->update(['game_snapshot' => $game]);
    foreach ($ballots as $voterIndex => $targetIndex) {
        $service->vote($code, $uuids[$voterIndex], $targetIndex === 'skip' ? 'skip' : $uuids[$targetIndex], $deadline);
    }
    expireSkipPhase($code);
    $game = $record->fresh()->game_snapshot;
    expect($game['vote_result']['eliminated_uuid'])->toBeNull()
        ->and($game['current_phase'])->toBe('night')
        ->and(count(array_filter($game['players'], fn ($player) => $player['is_alive'])))->toBe(5);
})->with([
    'all skip' => [['skip', 'skip', 'skip', 'skip', 'skip']],
    'skip leads' => [['skip', 'skip', 'skip', 0, 0]],
    'skip ties' => [['skip', 'skip', 0, 0]],
]);

it('still eliminates the leading player when skip has fewer votes', function () {
    [$service, $code, $uuids, $deadline] = prepareSkipGame();
    $service->vote($code, $uuids[0], 'skip', $deadline);
    foreach (array_slice($uuids, 1) as $uuid) {
        $service->vote($code, $uuid, $uuids[0], $deadline);
    }
    expireSkipPhase($code);
    $game = Room::where('room_code', $code)->firstOrFail()->game_snapshot;
    expect($game['winner'])->toBe('villager')->and($game['vote_result']['eliminated_uuid'])->toBe($uuids[0]);
});

it('clears skip ballots before a revote', function () {
    [$service, $code, $uuids, $deadline] = prepareSkipGame();
    $record = Room::where('room_code', $code)->firstOrFail();
    $game = $record->game_snapshot;
    $game['day_config']['tie_breaking'] = 'revote_once';
    $record->update(['game_snapshot' => $game]);
    foreach ([0, 0, 1, 1, 'skip'] as $index => $target) {
        $service->vote($code, $uuids[$index], $target === 'skip' ? 'skip' : $uuids[$target], $deadline);
    }
    expireSkipPhase($code);
    expect($record->fresh()->game_snapshot['ballot_number'])->toBe(2)
        ->and($record->fresh()->game_snapshot['day_skip_votes'])->toBe([])
        ->and($service->getGameView($code, $uuids[4])['my_vote'])->toBeNull();
});

it('rejects skip voting outside voting or by a dead player', function () {
    [$service, $code, $uuids, $deadline] = prepareSkipGame('day_discussion');
    $this->withSession(['player_uuid' => $uuids[0]])->postJson(route('games.vote', ['code' => $code]), ['expected_end_time' => $deadline, 'target_uuid' => 'skip'])->assertUnprocessable();
    $record = Room::where('room_code', $code)->firstOrFail();
    $game = $record->game_snapshot;
    $game['current_phase'] = 'day_voting';
    $game['players'][0]['is_alive'] = false;
    $record->update(['game_snapshot' => $game]);
    $this->postJson(route('games.vote', ['code' => $code]), ['expected_end_time' => $deadline, 'target_uuid' => 'skip'])->assertUnprocessable();
});

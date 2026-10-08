<?php

use App\Models\Room;
use Illuminate\Validation\ValidationException;

require_once __DIR__.'/../Support/MatchFixtures.php';

function prepareTrial(): array
{
    [$service, $code, $uuids, $deadline] = prepareSkipGame();
    $record = Room::where('room_code', $code)->firstOrFail();
    $game = $record->game_snapshot;
    $game['defense_enabled'] = true;
    $record->update(['game_snapshot' => $game]);
    $service->vote($code, $uuids[1], $uuids[2], $deadline);
    expect($service->getGameView($code, $uuids[0])['vote_history'])->toBeEmpty();
    expireSkipPhase($code);

    return [$service, $code, $uuids, $record->fresh()->game_snapshot['phase_end_time']];
}

it('opens defense without killing the accused and reveals the completed ballot', function () {
    [$service, $code, $uuids, $deadline] = prepareTrial();
    $view = $service->getGameView($code, $uuids[2]);
    expect($view['voting_stage'])->toBe('defense')->and($view['can_vote'])->toBeFalse()
        ->and($view['trial']['is_defendant'])->toBeTrue()->and($view['vote_history'][0]['ballots'][1]['target'])->toBe('Player 3');
    expect(collect($view['players'])->firstWhere('player_uuid', $uuids[2])['is_alive'])->toBeTrue();
    expect(fn () => $service->vote($code, $uuids[1], $uuids[2], $deadline))->toThrow(ValidationException::class);
    $this->withoutVite()->withSession(['player_uuid' => $uuids[2]])->get(route('games.show', ['code' => $code]))
        ->assertOk()->assertSee('defense-message')->assertSee('ใครโหวตใคร')->assertSee('chat-toggle')->assertSee('sound-toggle')
        ->assertSee('ผู้โหวตให้: Player 2')->assertSee('id="player-roster"', false)->assertDontSee('id="vote-target-list"', false);
});

it('rejects other players defense and stale deadlines and blank defense', function () {
    [$service, $code, $uuids, $deadline] = prepareTrial();
    $this->withSession(['player_uuid' => $uuids[1]])->postJson(route('games.trial', ['code' => $code]), [
        'kind' => 'defense', 'value' => 'I am innocent', 'expected_end_time' => $deadline,
    ])->assertForbidden();
    expect(fn () => $service->trialAction($code, $uuids[2], now()->addHour()->toIso8601String(), 'defense', 'innocent'))->toThrow(ValidationException::class);
    expect(fn () => $service->trialAction($code, $uuids[2], $deadline, 'defense', '   '))->toThrow(ValidationException::class);
});

it('eliminates an afk accused at the server deadline only once', function () {
    [$service, $code, $uuids] = prepareTrial();
    expireSkipPhase($code);
    $game = Room::where('room_code', $code)->firstOrFail()->game_snapshot;
    expect($game['current_phase'])->toBe('night')->and($game['trial_result']['afk'])->toBeTrue()
        ->and(collect($game['players'])->firstWhere('player_uuid', $uuids[2])['is_alive'])->toBeFalse();
    $service->advanceExpiredPhase($code);
    expect(Room::where('room_code', $code)->firstOrFail()->game_snapshot)->toBe($game);
});

it('accepts a defense once and requires a timed verdict', function () {
    [$service, $code, $uuids, $deadline] = prepareTrial();
    $service->trialAction($code, $uuids[2], $deadline, 'defense', '<script>innocent</script>');
    expect(fn () => $service->trialAction($code, $uuids[2], $deadline, 'defense', 'again'))->toThrow(ValidationException::class);
    expireSkipPhase($code);
    $record = Room::where('room_code', $code)->firstOrFail();
    $game = $record->game_snapshot;
    expect($game['voting_stage'])->toBe('verdict');
    $this->withoutVite()->withSession(['player_uuid' => $uuids[1]])->get(route('games.show', ['code' => $code]))
        ->assertOk()->assertSee('&lt;script&gt;innocent&lt;/script&gt;', false)->assertDontSee('<script>innocent</script>', false);
    $this->withSession(['player_uuid' => $uuids[2]])->postJson(route('games.trial', ['code' => $code]), [
        'kind' => 'verdict', 'value' => 'spare', 'expected_end_time' => $game['phase_end_time'],
    ])->assertForbidden();
    $service->trialAction($code, $uuids[1], $game['phase_end_time'], 'verdict', 'eliminate');
    expireSkipPhase($code);
    expect($record->fresh()->game_snapshot['trial_result']['eliminated'])->toBeTrue();
});

it('spares the accused for a tied or empty verdict and triggers sickness', function (bool $tie) {
    [$service, $code, $uuids, $deadline] = prepareTrial();
    $service->trialAction($code, $uuids[2], $deadline, 'defense', 'Please spare me');
    expireSkipPhase($code);
    $record = Room::where('room_code', $code)->firstOrFail();
    $deadline = $record->game_snapshot['phase_end_time'];
    if ($tie) {
        $service->trialAction($code, $uuids[0], $deadline, 'verdict', 'eliminate');
        $service->trialAction($code, $uuids[1], $deadline, 'verdict', 'spare');
    }
    expireSkipPhase($code);
    $game = $record->fresh()->game_snapshot;
    expect($game['trial_result']['eliminated'])->toBeFalse()
        ->and(collect($game['players'])->firstWhere('player_uuid', $uuids[2])['is_alive'])->toBeTrue()
        ->and(array_filter($game['players'], fn ($p) => $p['is_sick'] ?? false))->toHaveCount(1);
})->with([true, false]);

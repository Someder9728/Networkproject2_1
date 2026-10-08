<?php

use App\GameLogic\DifficultyConfig;
use App\Models\Room;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

require_once __DIR__.'/../Support/MatchFixtures.php';

it('starts larger rooms only when full and ready and assigns server roles', function (int $limit, string $difficulty, int $wolves) {
    [$service, $code, $uuids] = prepareReadyLobby($limit, $difficulty);
    expect(fn () => $service->join($code, 'Extra', (string) Str::uuid()))->toThrow(ValidationException::class);
    foreach (array_slice($uuids, 0, -1) as $uuid) {
        $service->setReady($code, $uuid, true);
    }
    expect($service->getRoom($code)['start_countdown_at'])->toBeNull();
    $service->setReady($code, $uuids[$limit - 1], true);
    expect($service->getRoom($code)['start_countdown_at'])->not->toBeNull();
    Room::where('room_code', $code)->update(['start_countdown_at' => now()->subSecond()]);
    $service->advanceLobby($code);
    $game = Room::where('room_code', $code)->firstOrFail()->game_snapshot;
    expect($game['status'])->toBe('in_progress')
        ->and($game['players'])->toHaveCount($limit)
        ->and(array_count_values(array_column($game['players'], 'role')))->toEqual([
            'werewolf' => $wolves, 'seer' => 1, 'guardian' => 1, 'villager' => $limit - $wolves - 2,
        ]);
    $this->withoutVite()->withSession(['player_uuid' => $uuids[0]])
        ->get(route('games.show', ['code' => $code]))->assertOk();
})->with([
    [7, 'easy', 2], [7, 'hard', 2], [8, 'easy', 2], [8, 'hard', 2],
    [9, 'easy', 2], [9, 'hard', 3], [10, 'easy', 2], [10, 'hard', 3],
]);

it('accepts larger room sizes through the creation form', function (int $limit) {
    $this->post(route('rooms.store'), [
        'host_name' => 'Host', 'difficulty' => 'easy', 'player_limit' => $limit,
    ])->assertRedirect()->assertSessionHasNoErrors();
    expect(Room::firstOrFail()->player_limit)->toBe($limit);
})->with([7, 8, 9, 10]);

it('shows every supported room size in the creation page', function () {
    $response = $this->withoutVite()->get(route('rooms.index'))->assertOk();
    foreach (DifficultyConfig::supportedPlayerCounts() as $limit) {
        $response->assertSee('value="'.$limit.'"', false);
    }
});

it('requires all larger-room players to skip discussion', function (int $limit) {
    [$service, $code, $uuids, $deadline] = prepareSkipGame('day_discussion', $limit);
    foreach (array_slice($uuids, 0, -1) as $uuid) {
        $service->skipDiscussion($code, $uuid, $deadline);
    }
    expect(Room::where('room_code', $code)->firstOrFail()->game_snapshot['current_phase'])->toBe('day_discussion');
    $service->skipDiscussion($code, $uuids[$limit - 1], $deadline);
    expect(Room::where('room_code', $code)->firstOrFail()->game_snapshot['current_phase'])->toBe('day_voting');
})->with([7, 8, 9, 10]);

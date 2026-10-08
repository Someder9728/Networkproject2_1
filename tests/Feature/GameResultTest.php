<?php

use App\Models\Room;
use App\Services\GameService;
use App\Services\RoomService;
use Illuminate\Support\Str;

function prepareResultGame(string $phase = 'day_voting'): array
{
    $service = app(RoomService::class);
    $uuids = array_map(fn () => (string) Str::uuid(), range(1, 4));
    $names = ['Wolf <script>alert(1)</script>', 'Oracle', 'Citizen A', 'Citizen B'];
    $room = $service->create($names[0], $uuids[0], 'hard');
    foreach (array_slice($uuids, 1, null, true) as $index => $uuid) {
        $service->join($room['code'], $names[$index], $uuid);
    }
    foreach ($uuids as $uuid) {
        $service->setReady($room['code'], $uuid, true);
    }
    Room::where('room_code', $room['code'])->update(['start_countdown_at' => now()->subSecond()]);
    $room = $service->start($room['code'], $uuids[0], app(GameService::class));
    $game = $room['game'];
    foreach (['werewolf', 'seer', 'villager', 'villager'] as $index => $role) {
        $game['players'][$index]['role'] = $role;
    }
    $game['status'] = 'in_progress';
    $game['current_phase'] = $phase;
    $game['current_round'] = 1;
    $game['phase_end_time'] = now()->addSeconds(60)->toIso8601String();
    Room::where('room_code', $room['code'])->update([
        'game_snapshot' => $game,
        'room_status' => $phase,
    ]);

    return [$service, $room['code'], $uuids, $game];
}

it('keeps the result and other player roles private before game over', function () {
    [$service, $code, $uuids] = prepareResultGame('day_discussion');
    $view = $service->getGameView($code, $uuids[2]);
    expect($view['game_result'])->toBeNull();
    foreach ($view['players'] as $player) {
        expect($player)->not->toHaveKey('role');
    }
    $this->withoutVite()->withSession(['player_uuid' => $uuids[2]])
        ->get(route('games.show', ['code' => $code]))
        ->assertOk()->assertDontSee('id="game-result-title"', false);
});

it('renders the server winner and every role after the final action', function (string $phase, string $winner) {
    [$service, $code, $uuids, $game] = prepareResultGame($phase);
    if ($winner === 'werewolf') {
        $game['players'][3]['is_alive'] = false;
        Room::where('room_code', $code)->update(['game_snapshot' => $game]);
    }
    $deadline = $game['phase_end_time'];
    if ($phase === 'day_voting') {
        $target = $winner === 'villager' ? $uuids[0] : $uuids[1];
        foreach ($game['players'] as $player) {
            if ($player['is_alive']) {
                $service->vote($code, $player['player_uuid'], $target, $deadline);
            }
        }
    } else {
        $service->werewolfAction($code, $uuids[0], $uuids[1], $deadline);
    }
    $game = Room::where('room_code', $code)->firstOrFail()->game_snapshot;
    $game['phase_end_time'] = now()->subSeconds(1)->toIso8601String();
    Room::where('room_code', $code)->update(['game_snapshot' => $game]);
    $service->advanceExpiredPhase($code);
    $snapshot = Room::where('room_code', $code)->firstOrFail()->game_snapshot;
    if (($snapshot['voting_stage'] ?? 'accusation') === 'defense') {
        expect($snapshot['status'])->toBe('in_progress');
        $snapshot['phase_end_time'] = now()->subSecond()->toIso8601String();
        Room::where('room_code', $code)->update(['game_snapshot' => $snapshot]);
        $service->advanceExpiredPhase($code);
        $snapshot = Room::where('room_code', $code)->firstOrFail()->game_snapshot;
    }
    expect($snapshot['status'])->toBe('finished')
        ->and($snapshot['current_phase'])->toBe('game_over')
        ->and($snapshot['winner'])->toBe($winner);
    foreach ([$uuids[0], $uuids[2]] as $viewer) {
        $result = $service->getGameView($code, $viewer)['game_result'];
        expect($result['winner'])->toBe($snapshot['winner'])
            ->and(array_column($result['players'], 'role'))->toBe(array_column($snapshot['players'], 'role'));
        $this->withoutVite()->withSession(['player_uuid' => $viewer])
            ->get(route('games.show', ['code' => $code]))
            ->assertOk()
            ->assertSee($winner === 'werewolf' ? 'ทีมหมาป่าชนะ' : 'ทีมชาวบ้านชนะ')
            ->assertSeeInOrder(['บทบาทของผู้เล่นทุกคน', 'Wolf &lt;script&gt;alert(1)&lt;/script&gt;', 'หมาป่า', 'Oracle', 'ผู้หยั่งรู้', 'Citizen A', 'ชาวบ้าน', 'Citizen B', 'ชาวบ้าน'], false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }
    $this->withSession(['player_uuid' => (string) Str::uuid()])
        ->get(route('games.show', ['code' => $code]))->assertForbidden();
})->with([
    'village wins by voting out wolf' => ['day_voting', 'villager'],
    'wolves win by daytime parity' => ['day_voting', 'werewolf'],
    'wolves win by night kill' => ['night', 'werewolf'],
]);

it('includes players who left in the final snapshot roster', function () {
    [$service, $code, $uuids, $game] = prepareResultGame();
    $game['players'][0]['is_alive'] = false;
    $game['players'][0]['has_left'] = true;
    $game['status'] = 'finished';
    $game['winner'] = 'villager';
    $game['current_phase'] = 'game_over';
    $game['phase_end_time'] = null;
    $record = Room::where('room_code', $code)->firstOrFail();
    $record->update(['game_snapshot' => $game, 'room_status' => 'ended']);
    $record->players()->where('player_uuid', $uuids[0])->update(['has_left' => true]);
    expect($service->getGameView($code, $uuids[2])['game_result']['players'])->toHaveCount(4);
    $this->withoutVite()->withSession(['player_uuid' => $uuids[2]])
        ->get(route('games.show', ['code' => $code]))
        ->assertOk()->assertSee('ออกจากเกม')->assertSee('Wolf &lt;script&gt;alert(1)&lt;/script&gt;', false);
});

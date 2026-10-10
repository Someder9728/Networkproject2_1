<?php

use App\GameLogic\MatchRules;
use App\Models\Room;

require_once __DIR__.'/../Support/MatchFixtures.php';

it('infects one non wolf after no elimination and delays death until the next rounds night', function () {
    [$service, $code] = prepareSkipGame();
    expireSkipPhase($code);
    $game = Room::where('room_code', $code)->firstOrFail()->game_snapshot;
    $sick = array_values(array_filter($game['players'], fn ($p) => $p['is_sick'] ?? false));
    expect($sick)->toHaveCount(1)->and($sick[0]['role'])->not->toBe('werewolf')
        ->and($sick[0]['sickness_death_round'])->toBe($game['current_round'] + 1);
    $uuid = $sick[0]['player_uuid'];
    expireSkipPhase($code);
    $game = Room::where('room_code', $code)->firstOrFail()->game_snapshot;
    expect(collect($game['players'])->firstWhere('player_uuid', $uuid)['is_alive'])->toBeTrue();
    expireSkipPhase($code);
    expireSkipPhase($code);
    expireSkipPhase($code);
    $game = Room::where('room_code', $code)->firstOrFail()->game_snapshot;
    expect(collect($game['players'])->firstWhere('player_uuid', $uuid)['is_alive'])->toBeFalse()
        ->and(collect($game['players'])->firstWhere('player_uuid', $uuid)['death_reason'])->toBe('sickness');
});

it('does not infect anyone when voting eliminates a player', function () {
    [$service, $code, $uuids, $deadline] = prepareSkipGame();
    $service->vote($code, $uuids[1], $uuids[2], $deadline);
    expireSkipPhase($code);
    $game = Room::where('room_code', $code)->firstOrFail()->game_snapshot;
    expect(array_filter($game['players'], fn ($p) => $p['is_sick'] ?? false))->toBeEmpty();
});

it('does not infect dead, departed, already sick players or wolves', function () {
    $game = ['current_round' => 4, 'players' => [
        ['player_uuid' => 'wolf', 'role' => 'werewolf', 'is_alive' => true],
        ['player_uuid' => 'dead', 'role' => 'villager', 'is_alive' => false],
        ['player_uuid' => 'left', 'role' => 'villager', 'is_alive' => true, 'has_left' => true],
        ['player_uuid' => 'sick', 'role' => 'seer', 'is_alive' => true, 'is_sick' => true, 'sickness_death_round' => 6],
    ]];
    expect(MatchRules::infectAfterNoElimination($game))->toBe($game);
});

it('infects after all skip ballots and shows public sickness without exposing roles', function () {
    [$service, $code, $uuids, $deadline] = prepareSkipGame();
    foreach ($uuids as $uuid) {
        $service->vote($code, $uuid, 'skip', $deadline);
    }
    expireSkipPhase($code);
    $view = $service->getGameView($code, $uuids[0]);
    expect(array_filter($view['players'], fn ($p) => $p['is_sick']))->toHaveCount(1);
    foreach ($view['players'] as $player) {
        expect($player)->not->toHaveKey('role');
    }
    $this->withoutVite()->withSession(['player_uuid' => $uuids[0]])
        ->get(route('games.show', ['code' => $code]))->assertOk()->assertSee('ป่วย — จะเสียชีวิตเมื่อจบคืนรอบ');
});

it('renders a matching image and objective for each private role', function (string $role) {
    [$service, $code, $uuids] = prepareSkipGame();
    $record = Room::where('room_code', $code)->firstOrFail();
    $game = $record->game_snapshot;
    $game['players'][1]['role'] = $role;
    $record->update(['game_snapshot' => $game]);
    $this->withoutVite()->withSession(['player_uuid' => $uuids[1]])
        ->get(route('games.show', ['code' => $code]))->assertOk()
        ->assertSee('images/roles/'.$role.'.png')
        ->assertSee('<h4>เป้าหมายเพื่อชนะ</h4>', false)
        ->assertSee(match ($role) {
            'werewolf' => 'ทำให้จำนวนหมาป่าที่ยังมีชีวิตมากกว่าหรือเท่ากับฝ่ายชาวบ้าน',
            'seer' => 'ร่วมกับฝ่ายชาวบ้านกำจัดหมาป่าทั้งหมด',
            'guardian' => 'รักษาชีวิตคนสำคัญและช่วยฝ่ายชาวบ้านกำจัดหมาป่าทั้งหมด',
            'villager' => 'ร่วมกับผู้หยั่งรู้และผู้คุ้มกันถ้ามี กำจัดหมาป่าทั้งหมด',
        });
    expect(is_file(public_path('images/roles/'.$role.'.png')))->toBeTrue();
})->with(['werewolf', 'seer', 'guardian', 'villager']);

<?php

use App\Models\Room;

require_once __DIR__.'/../Support/MatchFixtures.php';

it('shows inspected players only on the seers roster without exposing exact town roles', function () {
    [$service, $code, $uuids] = prepareSkipGame();
    $record = Room::where('room_code', $code)->firstOrFail();
    $game = $record->game_snapshot;
    $game['seer_results'][$uuids[1]] = [
        ['round' => 1, 'target_uuid' => $uuids[0], 'target_name' => 'Player 1', 'is_werewolf' => true],
        ['round' => 1, 'target_uuid' => $uuids[3], 'target_name' => 'Player 4', 'is_werewolf' => false],
    ];
    $record->update(['game_snapshot' => $game]);
    $this->withoutVite()->withSession(['player_uuid' => $uuids[1]])->get(route('games.show', ['code' => $code]))
        ->assertOk()->assertSee('ตรวจแล้ว: หมาป่า')->assertSee('ตรวจแล้ว: ไม่ใช่หมาป่า')
        ->assertSee('data-seer-result="'.$uuids[0].'"', false)->assertSee('data-seer-result="'.$uuids[3].'"', false)
        ->assertSee('🔮 ผลตรวจล่าสุด:</strong> Player 4', false);
    $this->withSession(['player_uuid' => $uuids[2]])->get(route('games.show', ['code' => $code]))
        ->assertOk()->assertDontSee('data-seer-result=', false)->assertDontSee('🔮 ผลตรวจล่าสุด:');
    expect($service->getGameView($code, $uuids[2])['my_seer_results'])->toBeEmpty();
});

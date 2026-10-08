<?php

use App\GameLogic\MatchTimeline;

it('keeps chronological public results without duplicating unchanged results or exposing seer checks', function () {
    $game = ['status' => 'in_progress', 'current_round' => 1, 'players' => [
        ['player_uuid' => 'a', 'name' => 'Alice'], ['player_uuid' => 'b', 'name' => 'Bob'],
    ], 'vote_result' => ['eliminated_uuid' => 'b', 'round' => 1], 'seer_results' => ['private' => ['secret']]];
    $first = MatchTimeline::update($game, null);
    expect($first['event_history'])->toHaveCount(1)->and($first['event_history'][0]['message'])->toContain('Bob');
    $same = MatchTimeline::update($first, $first);
    expect($same['event_history'])->toBe($first['event_history']);
    $night = $same;
    $night['night_result'] = ['round' => 1, 'killed_uuid' => 'a'];
    $night = MatchTimeline::update($night, $same);
    expect($night['event_history'])->toHaveCount(2)->and($night['event_history'][1]['message'])->toContain('Alice')
        ->and(json_encode($night['event_history']))->not->toContain('secret');
});

<?php

use App\Models\Room;
use App\Services\RoomService;
use Illuminate\Support\Str;

function prepareReadyLobby(int $limit = 5, string $difficulty = 'hard'): array
{
    $service = app(RoomService::class);
    $uuids = array_map(fn () => (string) Str::uuid(), range(1, $limit));
    $room = $service->create('Player 1', $uuids[0], $difficulty, $limit);
    foreach (array_slice($uuids, 1, null, true) as $index => $uuid) {
        $service->join($room['code'], 'Player '.($index + 1), $uuid);
    }

    return [$service, $room['code'], $uuids];
}

function prepareSkipGame(string $phase = 'day_voting', int $limit = 5): array
{
    [$service, $code, $uuids] = prepareReadyLobby($limit);
    foreach ($uuids as $uuid) {
        $service->setReady($code, $uuid, true);
    }
    Room::where('room_code', $code)->update(['start_countdown_at' => now()->subSecond()]);
    $service->advanceLobby($code);
    $game = Room::where('room_code', $code)->firstOrFail()->game_snapshot;
    foreach ($game['players'] as $index => &$player) {
        $player['role'] = match ($index) {
            0 => 'werewolf', 1 => 'seer', default => 'villager'
        };
    }
    unset($player);
    $game['defense_enabled'] = false; // Existing fixture covers direct elimination without a trial.
    $game['current_phase'] = $phase;
    $game['phase_end_time'] = now()->addMinutes(2)->toIso8601String();
    Room::where('room_code', $code)->update(['game_snapshot' => $game, 'room_status' => $phase]);

    return [$service, $code, $uuids, $game['phase_end_time']];
}

function expireSkipPhase(string $code): void
{
    $record = Room::where('room_code', $code)->firstOrFail();
    $game = $record->game_snapshot;
    $game['phase_end_time'] = now()->subSecond()->toIso8601String();
    $record->update(['game_snapshot' => $game]);
    app(RoomService::class)->advanceExpiredPhase($code);
}

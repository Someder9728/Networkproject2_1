<?php

namespace App\Services;

use App\GameLogic\GameEngine;
use App\GameLogic\MatchRules;
use App\GameLogic\PhaseManager;
use App\GameLogic\RoleAbility;
use App\Support\RoomLock;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

trait MatchPresence
{
    public function guardianAction(string $code, string $uuid, string $target, string $deadline): void
    {
        RoomLock::make($code, 10)->block(3, function () use ($code, $uuid, $target, $deadline) {
            $room = $this->getRoom($code);
            abort_unless(collect($room['players'])->contains('player_uuid', $uuid), 403);
            $game = $room['game'] ?? null;
            abort_if($game === null, 404);
            if ($game['phase_end_time'] !== $deadline) {
                throw ValidationException::withMessages(['action' => 'ช่วงเวลาเกมเปลี่ยนแล้ว']);
            }
            $engine = new GameEngine($game);
            $result = $engine->handlePlayerAction($uuid, 'guardian_protect', $target);
            if ($result['status'] !== 'success') {
                throw ValidationException::withMessages(['action' => $result['message']]);
            }
            $room['game'] = $engine->getGameState();
            $this->saveGameRoom($room);
        });
    }

    public function maintainMatch(string $code, ?string $viewer = null): void
    {
        RoomLock::make($code, 10)->block(3, function () use ($code, $viewer) {
            $room = $this->getRoom($code);
            if ($viewer !== null) {
                abort_unless(collect($room['players'])->contains('player_uuid', $viewer), 403);
            }
            $game = $room['game'] ?? null;
            if ($game === null || $game['status'] === 'finished') {
                return;
            }
            $changed = false;
            $timeout = max(10, (int) config('game.heartbeat_timeout_seconds', 25));
            $grace = max(1, (int) config('game.reconnect_timeout_seconds', 60));
            foreach ($game['players'] as &$player) {
                if ($player['has_left'] ?? false) {
                    continue;
                }
                $player['last_seen_at'] ??= now()->toIso8601String();
                $last = CarbonImmutable::parse($player['last_seen_at']);
                $expired = $player['is_alive'] && now()->gte($last->addSeconds($timeout + $grace));
                if ($expired) {
                    $player['has_left'] = true;
                    $player['is_alive'] = false;
                    $player['is_connected'] = false;
                    unset($game['night_actions'][$player['player_uuid']], $game['day_skip_votes'][$player['player_uuid']], $game['day_votes'][$player['player_uuid']]);
                    $changed = true;

                    continue;
                }
                if ($player['player_uuid'] === $viewer) {
                    $changed = $changed || ! ($player['is_connected'] ?? true);
                    $player['last_seen_at'] = now()->toIso8601String();
                    $player['is_connected'] = true;
                    $player['disconnected_at'] = null;
                    $player['reconnect_deadline'] = null;
                } elseif (now()->gte($last->addSeconds($timeout))) {
                    $changed = $changed || ($player['is_connected'] ?? true);
                    $player['is_connected'] = false;
                    $player['disconnected_at'] = $last->addSeconds($timeout)->toIso8601String();
                    $player['reconnect_deadline'] = $last->addSeconds($timeout + $grace)->toIso8601String();
                }
            }
            unset($player);
            $remaining = array_column(array_filter($game['players'], fn ($p) => ! ($p['has_left'] ?? false)), 'player_uuid');
            $room['players'] = array_values(array_filter($room['players'], fn ($p) => in_array($p['player_uuid'], $remaining, true)));
            if (! in_array($room['host_uuid'], $remaining, true)) {
                $room['host_uuid'] = $remaining[0] ?? null;
            }
            // Keep the role-reveal preparation phase compatible with existing games.
            if ($game['status'] === 'in_progress') {
                $game['winner'] = RoleAbility::checkWinCondition($game['players']);
                if ($game['winner'] !== null) {
                    $game['status'] = 'finished';
                    $game['current_phase'] = PhaseManager::PHASE_GAME_OVER;
                    $game['phase_end_time'] = null;
                }
                $game = MatchRules::applyTimeLimit($game);
            }
            $room['status'] = $game['status'] === 'finished' ? 'ended' : ($game['current_phase'] ?? 'initializing');
            $room['game'] = $game;
            if ($game['status'] === 'in_progress' && $game['current_phase'] === 'day_discussion' && $this->discussionSkippedByEveryone($game)) {
                $this->enterVoting($room, $game);
            } else {
                $this->saveGameRoom($room);
            }
            if ($changed) {
                $this->broadcastRoomUpdated($code);
            }
            if ($viewer !== null) {
                abort_unless(in_array($viewer, $remaining, true), 403, 'หมดเวลาสำหรับกลับเข้าเกมแล้ว');
            }
        });
    }
}

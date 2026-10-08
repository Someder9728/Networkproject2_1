<?php

namespace App\Services;

use App\Support\RoomLock;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

trait VotingTrial
{
    public function trialAction(string $code, string $uuid, string $deadline, string $kind, string $value): void
    {
        RoomLock::make($code, 10)->block(3, function () use ($code, $uuid, $deadline, $kind, $value) {
            $room = $this->getRoom($code);
            $game = $room['game'] ?? null;
            $me = collect($game['players'] ?? [])->firstWhere('player_uuid', $uuid);
            abort_unless($me && $me['is_alive'] && ! ($me['has_left'] ?? false), 403);
            $stage = $kind === 'defense' ? 'defense' : 'verdict';
            if ($game['status'] !== 'in_progress' || $game['current_phase'] !== 'day_voting' || ($game['voting_stage'] ?? 'accusation') !== $stage || $game['phase_end_time'] !== $deadline || now()->gte(CarbonImmutable::parse($deadline))) {
                throw ValidationException::withMessages(['trial' => 'ช่วงแก้ต่างหรือยืนยันเปลี่ยนแล้ว']);
            }
            $defendant = $game['trial']['target_uuid'];
            if ($kind === 'defense') {
                abort_unless($uuid === $defendant, 403);
                $value = trim($value);
                if (mb_strlen($value) < 3 || mb_strlen($value) > 500 || $game['trial']['defense'] !== null) {
                    throw ValidationException::withMessages(['trial' => 'ส่งคำแก้ต่าง 3–500 ตัวอักษรได้หนึ่งครั้ง']);
                }
                $game['trial']['defense'] = $value;
            } else {
                abort_if($uuid === $defendant, 403);
                if (! in_array($value, ['eliminate', 'spare'], true)) {
                    throw ValidationException::withMessages(['trial' => 'เลือกให้ออกหรือให้รอด']);
                }
                $game['trial']['votes'][$uuid] = $value;
            }
            $room['game'] = $game;
            $this->saveGameRoom($room);
        });
    }

    private function recordBallot(array $game): array
    {
        $ballots = [];
        foreach ($game['players'] as $player) {
            if (! $player['is_alive'] || ($player['has_left'] ?? false)) {
                continue;
            }
            $target = $game['day_votes'][$player['player_uuid']] ?? null;
            $ballots[] = [
                'voter' => $player['name'],
                'voter_uuid' => $player['player_uuid'],
                'target_uuid' => $target,
                'target' => $target === 'skip' ? 'Skip' : ($target === null ? 'ไม่ได้โหวต' : (collect($game['players'])->firstWhere('player_uuid', $target)['name'] ?? 'ผู้เล่น')),
            ];
        }
        $game['vote_history'][] = ['round' => $game['current_round'], 'ballot' => $game['ballot_number'] ?? 1, 'ballots' => $ballots];

        return $game;
    }

    private function trialOutcome(array &$game): ?array
    {
        $trial = $game['trial'];
        if ($game['voting_stage'] === 'defense' && $trial['defense'] !== null) {
            $game['voting_stage'] = 'verdict';
            $game['phase_end_time'] = now()->addSeconds(max(1, (int) config('game.verdict_seconds', 20)))->toIso8601String();

            return null;
        }
        $afk = $game['voting_stage'] === 'defense';
        $eligible = array_column(array_filter($game['players'], fn ($p) => $p['is_alive'] && ! ($p['has_left'] ?? false) && $p['player_uuid'] !== $trial['target_uuid']), 'player_uuid');
        $votes = array_intersect_key($trial['votes'], array_flip($eligible));
        $counts = array_count_values(array_values($votes));
        $out = $afk || (($counts['eliminate'] ?? 0) > ($counts['spare'] ?? 0));
        $target = collect($game['players'])->firstWhere('player_uuid', $trial['target_uuid']);
        $out = $out && ($target['is_alive'] ?? false) && ! ($target['has_left'] ?? false);
        $game['trial_result'] = ['round' => $game['current_round'], 'name' => $target['name'] ?? 'ผู้เล่น', 'defense' => $trial['defense'], 'afk' => $afk, 'eliminated' => $out, 'counts' => $counts];
        $game['voting_stage'] = 'accusation';

        return ['requires_revote' => false, 'eliminated_uuid' => $out ? $trial['target_uuid'] : null];
    }
}

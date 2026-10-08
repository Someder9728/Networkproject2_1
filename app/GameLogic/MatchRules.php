<?php

namespace App\GameLogic;

use Carbon\CarbonImmutable;

class MatchRules
{
    public static function nightEvent(array $players = []): array
    {
        $event = random_int(1, 100) <= (int) config('game.peaceful_night_chance', 20)
            ? ['id' => 'peaceful_night', 'name' => 'คืนสงบ']
            : ['id' => 'normal_night', 'name' => 'คืนปกติ'];
        $event['description'] = self::nightDescription($event, $players);

        return $event;
    }

    public static function nightDescription(array $event, array $players): string
    {
        $hasGuardian = collect($players)->contains('role', RoleAssignment::ROLE_GUARDIAN);
        if ($event['id'] === 'peaceful_night') {
            return $hasGuardian
                ? 'คืนนี้หมาป่าโจมตีไม่ได้ ผู้หยั่งรู้และผู้คุ้มกันยังใช้ความสามารถได้'
                : 'คืนนี้หมาป่าโจมตีไม่ได้ ผู้หยั่งรู้ยังใช้ความสามารถได้';
        }

        return $hasGuardian
            ? 'หมาป่าโจมตีได้ ผู้คุ้มกันป้องกันได้หนึ่งคน ห้ามป้องกันคนเดิมติดกัน'
            : 'หมาป่าโจมตีได้ ผู้หยั่งรู้ใช้ความสามารถได้';
    }

    public static function nightOutcome(array $snapshot, ?string $target): array
    {
        $players = collect($snapshot['players'])->keyBy('player_uuid');
        $protected = [];
        $previous = $snapshot['guardian_last_targets'] ?? [];
        $last = [];
        foreach ($snapshot['night_actions'] ?? [] as $uuid => $action) {
            $actor = $players->get($uuid);
            $recipient = $players->get($action['target_id'] ?? null);
            if (($action['role'] ?? null) === 'guardian' && ($actor['role'] ?? null) === 'guardian'
                && ($actor['is_alive'] ?? false) && ! ($actor['has_left'] ?? false)
                && ($recipient['is_alive'] ?? false) && ! ($recipient['has_left'] ?? false)
                && ($previous[$uuid] ?? null) !== $recipient['player_uuid']) {
                $protected[] = $recipient['player_uuid'];
                $last[$uuid] = $recipient['player_uuid'];
            }
        }
        $blocked = ($snapshot['night_rule']['id'] ?? null) === 'peaceful_night' ? 'peaceful_night'
            : ($target !== null && in_array($target, $protected, true) ? 'guardian' : null);

        return ['killed_uuid' => $blocked === null ? $target : null, 'blocked_by' => $blocked, 'guardian_last_targets' => $last];
    }

    public static function addEvidence(array $game): array
    {
        $round = $game['current_round'] + 1;
        if (collect($game['evidence'] ?? [])->contains('round', $round)) {
            return $game;
        }
        $reason = $game['night_result']['blocked_by'] ?? null;
        $message = match ($reason) {
            'peaceful_night' => 'คืนที่ผ่านมาเป็นคืนสงบ หมาป่าโจมตีไม่ได้',
            'guardian' => 'เมื่อคืนมีการโจมตี แต่เป้าหมายได้รับการป้องกัน',
            default => ($game['night_result']['killed_uuid'] ?? null) !== null ? 'พบร่องรอยการโจมตีเมื่อคืน' : 'ไม่พบผู้เสียชีวิตจากการโจมตีเมื่อคืน',
        };
        $game['evidence'][] = ['round' => $round, 'type' => 'night_report', 'message' => $message, 'players' => []];
        $alreadyClue = collect($game['evidence'])->contains('type', 'suspect_group');
        $alive = array_values(array_filter($game['players'], fn ($p) => $p['is_alive'] && ! ($p['has_left'] ?? false)));
        $wolves = array_values(array_filter($alive, fn ($p) => $p['role'] === 'werewolf'));
        $villagers = array_values(array_filter($alive, fn ($p) => $p['role'] !== 'werewolf'));
        if (! $alreadyClue && count($alive) >= 4 && count($wolves) > 0 && count($villagers) >= 2) {
            shuffle($wolves);
            shuffle($villagers);
            $group = [$wolves[0], $villagers[0], $villagers[1]];
            shuffle($group);
            $game['evidence'][] = [
                'round' => $round, 'type' => 'suspect_group',
                'message' => 'อย่างน้อยหนึ่งคนในกลุ่มนี้เป็นหมาป่า ณ ตอนออกหลักฐาน',
                'players' => array_map(fn ($p) => ['player_uuid' => $p['player_uuid'], 'name' => $p['name']], $group),
            ];
        }

        return $game;
    }

    public static function infectAfterNoElimination(array $game): array
    {
        $eligible = array_keys(array_filter($game['players'], fn ($p) => $p['is_alive'] && ! ($p['has_left'] ?? false) && $p['role'] !== 'werewolf' && ! ($p['is_sick'] ?? false)));
        if ($eligible !== []) {
            $index = $eligible[random_int(0, count($eligible) - 1)];
            $game['players'][$index]['is_sick'] = true;
            $game['players'][$index]['sickness_death_round'] = $game['current_round'] + 1;
        }

        return $game;
    }

    public static function resolveSickness(array $game): array
    {
        $deaths = [];
        foreach ($game['players'] as &$player) {
            if ($player['is_alive'] && ! ($player['has_left'] ?? false) && ($player['is_sick'] ?? false) && ($player['sickness_death_round'] ?? PHP_INT_MAX) <= $game['current_round']) {
                $player['is_alive'] = false;
                $player['death_reason'] = 'sickness';
                $deaths[] = $player['player_uuid'];
            }
        }
        unset($player);
        $game['night_result']['sickness_deaths'] = $deaths;

        return $game;
    }

    public static function applyTimeLimit(array $game, bool $roundEnded = false): array
    {
        if ($game['status'] !== 'in_progress' || ($game['game_mode'] ?? 'normal') !== 'short') {
            return $game;
        }
        $timeUp = now()->gte(CarbonImmutable::parse($game['match_end_time']));
        $roundUp = $roundEnded && $game['current_round'] >= $game['max_rounds'];
        if ($timeUp || $roundUp) {
            $game['winner'] = RoleAbility::checkWinCondition($game['players']) ?? RoleAssignment::TEAM_VILLAGER;
            $game['finish_reason'] = $timeUp ? 'time_limit' : 'round_limit';
            $game['status'] = 'finished';
            $game['current_phase'] = PhaseManager::PHASE_GAME_OVER;
            $game['phase_end_time'] = null;
        }

        return $game;
    }
}

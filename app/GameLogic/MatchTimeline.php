<?php

namespace App\GameLogic;

class MatchTimeline
{
    public static function update(array $game, ?array $previous): array
    {
        $messages = [];
        $name = fn ($uuid) => collect($game['players'])->firstWhere('player_uuid', $uuid)['name'] ?? 'ผู้เล่น';
        if (($game['vote_history'] ?? []) !== ($previous['vote_history'] ?? []) && ($game['voting_stage'] ?? null) === 'defense') {
            $messages[] = $name($game['trial']['target_uuid']).' ได้คะแนนสูงสุด กำลังแก้ต่าง';
        }
        if (($game['vote_result'] ?? null) !== ($previous['vote_result'] ?? null) && isset($game['vote_result'])) {
            $target = $game['vote_result']['eliminated_uuid'];
            $messages[] = $target === null ? 'ผลโหวต: ไม่มีผู้ถูกโหวตออก' : 'ผลโหวต: '.$name($target).' ถูกออกจากเกม';
            $newSick = array_filter($game['players'], fn ($p) => ($p['is_sick'] ?? false) && ! (collect($previous['players'] ?? [])->firstWhere('player_uuid', $p['player_uuid'])['is_sick'] ?? false));
            if ($newSick) {
                $last = array_key_last($messages);
                $messages[$last] .= ' · '.implode(', ', array_column($newSick, 'name')).' เริ่มป่วย';
            }
        }
        if (($game['night_result'] ?? null) !== ($previous['night_result'] ?? null) && isset($game['night_result'])) {
            $result = $game['night_result'];
            $text = ($result['killed_uuid'] ?? null) ? $name($result['killed_uuid']).' เสียชีวิตจากการโจมตี' : 'ไม่มีผู้เสียชีวิตจากการโจมตี';
            foreach ($result['sickness_deaths'] ?? [] as $uuid) {
                $text .= ' · '.$name($uuid).' เสียชีวิตจากโรค';
            }
            $messages[] = 'ผลกลางคืน: '.$text;
        }
        if ($game['status'] === 'finished' && ($previous['status'] ?? null) !== 'finished') {
            $messages[] = $game['winner'] === 'werewolf' ? 'จบเกม: ทีมหมาป่าชนะ' : 'จบเกม: ทีมชาวบ้านชนะ';
        }
        foreach ($messages as $message) {
            $game['event_history'][] = ['round' => $game['current_round'], 'message' => $message];
        }

        return $game;
    }
}

@php
    $latestBallot = count($game['vote_history']) > 0 ? $game['vote_history'][array_key_last($game['vote_history'])] : null;
    $latestVotes = collect($latestBallot['ballots'] ?? []);
@endphp
<div class="section-heading">
    <h2 class="section-title">ผู้เล่น</h2>
    <div class="section-count">{{ count($game['players']) }} Players</div>
</div>
@if ($latestBallot)
<p class="action-description">ใครโหวตใคร · ผลรอบ {{ $latestBallot['round'] }} ครั้งที่ {{ $latestBallot['ballot'] }} (เปิดเผยหลังหมดเวลาโหวต)</p>
@endif
@if ($game['can_vote'])
<p class="action-description">กดโหวตข้างชื่อผู้เล่นที่ต้องการให้ออก เปลี่ยนโหวตได้ก่อนหมดเวลา</p>
@endif
@if ($game['status'] === 'in_progress' && $game['current_phase'] === 'day_discussion')
                    <div class="section-card">
                        <div class="action-description">โหวตข้ามช่วงพูดคุย {{ $game['discussion_skip_count'] }} / {{ $game['discussion_skip_required'] }} คน</div>
                        @if ($game['can_skip_discussion'])
                        <form method="POST" action="{{ route('games.skip-discussion', ['code' => $game['room_code']]) }}">
                            @csrf
                            <input type="hidden" name="expected_end_time" value="{{ $game['phase_end_time'] }}">
                            <button type="submit" class="btn-game" @disabled($game['my_discussion_skip'])>
                                {{ $game['my_discussion_skip'] ? 'โหวตข้ามแล้ว — รอทุกคน' : 'Skip Discussion · โหวตข้ามพูดคุย' }}
                            </button>
                        </form>
                        @endif
                    </div>
                    @endif

<div class="players-grid" id="player-roster">
    @foreach ($game['players'] as $player)
    @php
        $isCurrentPlayer = $player['player_uuid'] === session('player_uuid');
        $playerClass = $player['has_left'] ? 'left-player' : (! $player['is_alive'] ? 'dead' : '');
        $voters = $latestVotes->filter(fn ($vote) => array_key_exists('target_uuid', $vote)
            ? $vote['target_uuid'] === $player['player_uuid']
            : $vote['target'] === $player['name']);
        $selected = $game['can_vote'] && $game['my_vote'] === $player['player_uuid'];
        $seerKnowledge = $game['my_role'] === 'seer'
            ? collect($game['my_seer_results'])->last(fn ($result) => isset($result['target_uuid'])
                ? $result['target_uuid'] === $player['player_uuid']
                : ($result['target_name'] === $player['name'] && collect($game['players'])->where('name', $player['name'])->count() === 1))
            : null;

    @endphp
    <div class="player-card {{ $playerClass }} {{ $isCurrentPlayer ? 'you' : '' }} {{ $selected ? 'roster-selected' : '' }}" data-roster-player="{{ $player['player_uuid'] }}" style="flex-wrap:wrap">
        <div class="player-avatar">{{ mb_substr($player['name'], 0, 1) }}</div>
        <div class="player-details">
            <div class="player-name">
                {{ $player['name'] }}
                @if ($isCurrentPlayer)<span class="player-you purple">(คุณ)</span>@endif
            </div>
            <div class="player-status player-state-{{ $player['has_left'] ? 'left' : ($player['is_alive'] ? 'alive' : 'dead') }}">{{ $player['has_left'] ? '↪ ออกจากเกมแล้ว' : ($player['is_alive'] ? '● มีชีวิต' : '☠ เสียชีวิต') }}</div>
            @if ($seerKnowledge !== null)
            <div class="roster-seer-result {{ $seerKnowledge['is_werewolf'] ? 'role-werewolf' : 'role-seer' }}" data-seer-result="{{ $player['player_uuid'] }}">
                🔮 ตรวจแล้ว: {{ $seerKnowledge['is_werewolf'] ? 'หมาป่า' : 'ไม่ใช่หมาป่า' }}
                <small>· รอบ {{ $seerKnowledge['round'] }} · เห็นเฉพาะคุณ</small>
            </div>
            @endif
            @if (($player['is_sick'] ?? false) && $player['is_alive'])
            <div class="roster-voters" style="color:#fbbf24">ป่วย · เสียชีวิตจบคืนรอบ {{ $player['sickness_death_round'] }}</div>
            @endif
            @if ($latestBallot)
            <div class="roster-voters">ผู้โหวตให้: {{ $voters->isEmpty() ? 'ไม่มี' : $voters->pluck('voter')->implode(', ') }}</div>
            @endif
        </div>
        @if ($game['can_vote'] && ! $isCurrentPlayer && $player['is_alive'] && ! $player['has_left'])
        <form method="POST" action="{{ route('games.vote', ['code' => $game['room_code']]) }}" class="roster-vote-form">
            @csrf
            <input type="hidden" name="expected_end_time" value="{{ $game['phase_end_time'] }}">
            <input type="hidden" name="target_uuid" value="{{ $player['player_uuid'] }}">
            <button type="submit" class="btn-game" aria-label="โหวต {{ $player['name'] }}">{{ $selected ? '✓ โหวตแล้ว' : 'โหวต' }}</button>
        </form>
        @endif
    </div>
    @endforeach
</div>
@if ($game['can_vote'])
<form method="POST" action="{{ route('games.vote', ['code' => $game['room_code']]) }}" class="action-area">
    @csrf
    <input type="hidden" name="expected_end_time" value="{{ $game['phase_end_time'] }}">
    <input type="hidden" name="target_uuid" value="skip">
    <button type="submit" class="btn-secondary-game">{{ $game['my_vote'] === 'skip' ? '✓ คุณโหวต Skip แล้ว' : 'Skip · ไม่โหวตใคร' }}</button>
</form>
<p class="action-description">Skip ได้คะแนนสูงสุดหรือเสมอสูงสุด จะไม่มีผู้ถูกโหวตออก</p>
@endif
@if ($latestBallot)
<div class="action-description">
    <div>Skip: {{ $latestVotes->where('target', 'Skip')->pluck('voter')->implode(', ') ?: 'ไม่มี' }}</div>
    <div>ไม่ได้โหวต: {{ $latestVotes->where('target', 'ไม่ได้โหวต')->pluck('voter')->implode(', ') ?: 'ไม่มี' }}</div>
</div>
@endif
<style>
.roster-seer-result {font-size:12px;font-weight:600;line-height:1.5;margin-top:5px}
.roster-seer-result small {display:block;font-size:10px;color:#94a3b8;font-weight:400}
.roster-voters {font-size:12px;color:#c4b5fd;line-height:1.6;overflow-wrap:anywhere;margin-top:6px}
.roster-selected {border:2px solid #a78bfa!important;background:#312e8155!important}
.roster-vote-form {margin-left:auto}
.roster-vote-form .btn-game {padding:8px 12px;font-size:13px}
</style>

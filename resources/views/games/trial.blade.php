@php($trialMe = collect($game['players'])->firstWhere('player_uuid', session('player_uuid')))
@if ($game['trial'] !== null)
<section class="section-card" aria-labelledby="trial-title">
    <h2 class="action-title" id="trial-title">{{ $game['voting_stage'] === 'defense' ? 'ช่วงแก้ต่าง' : 'โหวตยืนยัน' }} · {{ $game['trial']['name'] }}</h2>
    <p>เหลือเวลา <span id="trial-timer" data-deadline="{{ $game['phase_end_time'] }}" data-server-time="{{ $game['server_time'] }}">…</span></p>
    @if ($game['trial']['defense'] !== null)
        <p><strong>คำแก้ต่าง:</strong> {{ $game['trial']['defense'] }}</p>
    @endif
    @if ($game['voting_stage'] === 'defense')
        <p>ผู้ถูกกล่าวหาต้องส่งคำแก้ต่างภายใน {{ config('game.defense_seconds', 30) }} วินาที หากไม่ส่งจะถูกออกอัตโนมัติเมื่อหมดเวลา</p>
        @if ($game['trial']['is_defendant'] && $game['trial']['defense'] === null && ($trialMe['is_alive'] ?? false))
        <form method="POST" action="{{ route('games.trial', ['code' => $game['room_code']]) }}">
            @csrf
            <input type="hidden" name="kind" value="defense">
            <input type="hidden" name="expected_end_time" value="{{ $game['phase_end_time'] }}">
            <label for="defense-message">พิมพ์คำแก้ต่างของคุณ (ส่งได้ครั้งเดียว)</label>
            <textarea id="defense-message" name="value" required minlength="3" maxlength="500" class="chat-input" rows="3">{{ old('value') }}</textarea>
            <button type="submit" class="btn-game">ส่งคำแก้ต่าง</button>
        </form>
        @endif
    @else
        <p>ผู้เล่นที่ยังมีชีวิตยกเว้นผู้ถูกกล่าวหาโหวตยืนยันได้ ให้ออกต้องมีคะแนนมากกว่าให้รอด หากเสมอหรือไม่มีคนโหวต ผู้ถูกกล่าวหารอด</p>
        @if (($trialMe['is_alive'] ?? false) && ! $game['trial']['is_defendant'])
        <form method="POST" action="{{ route('games.trial', ['code' => $game['room_code']]) }}">
            @csrf
            <input type="hidden" name="kind" value="verdict">
            <input type="hidden" name="expected_end_time" value="{{ $game['phase_end_time'] }}">
            <button type="submit" name="value" value="eliminate" class="btn-game">ให้ออก</button>
            <button type="submit" name="value" value="spare" class="btn-secondary-game">ให้รอด</button>
        </form>
        @if ($game['trial']['my_verdict'])<p>คุณเลือก: {{ $game['trial']['my_verdict'] === 'eliminate' ? 'ให้ออก' : 'ให้รอด' }} · เปลี่ยนได้ก่อนหมดเวลา</p>@endif
        @endif
    @endif
</section>
@endif
<div data-dash-zone="results">
@if ($game['trial_result'] !== null)
<div class="section-card">
    <h3 class="action-title">ผลยืนยันรอบ {{ $game['trial_result']['round'] }} · {{ $game['trial_result']['name'] }}</h3>
    <p>{{ $game['trial_result']['eliminated'] ? 'ถูกโหวตออก' : 'รอดจากการโหวต' }}{{ $game['trial_result']['afk'] ? ' — ไม่ส่งคำแก้ต่างภายในเวลา' : '' }}</p>
    @if ($game['trial_result']['defense'])<p>คำแก้ต่าง: {{ $game['trial_result']['defense'] }}</p>@endif
    <p>ให้ออก {{ $game['trial_result']['counts']['eliminate'] ?? 0 }} · ให้รอด {{ $game['trial_result']['counts']['spare'] ?? 0 }}</p>
</div>
@endif
</div>

@if (count($game['event_history']) > 0)
<section class="section-card" data-dash-zone="results">
    <h3 class="action-title">ประวัติเหตุการณ์ทั้งหมด</h3>
    @foreach (array_reverse($game['event_history']) as $event)
    <p><small>รอบ {{ $event['round'] }}</small> · {{ $event['message'] }}</p>
    @endforeach
</section>
@endif

@if (count($game['vote_history']) > 0)
<section class="section-card" data-dash-zone="results">
    <h3 class="action-title">ประวัติโหวตทั้งหมด</h3>
    @foreach (array_reverse($game['vote_history']) as $ballot)
    <details>
        <summary>รอบ {{ $ballot['round'] }} · ครั้งที่ {{ $ballot['ballot'] }}</summary>
        @foreach ($ballot['ballots'] as $vote)<p>{{ $vote['voter'] }} → {{ $vote['target'] }}</p>@endforeach
    </details>
    @endforeach
</section>
@endif

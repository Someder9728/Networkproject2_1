<div class="section-card">
    <div class="action-title">โรคในหมู่บ้าน</div>
    <p>ถ้าผลโหวตไม่มีใครถูกออก จะสุ่มคนที่ไม่ใช่หมาป่า 1 คนให้ป่วย ผู้ป่วยจะเสียชีวิตเมื่อจบคืนของรอบถัดไป @if (count($game['players']) >= 5) ผู้คุ้มกันไม่สามารถป้องกันโรคได้ @endif</p>
    @foreach ($game['players'] as $patient)
        @if ($patient['is_sick'] ?? false)
        <p><strong>{{ $patient['name'] }}</strong> · {{ $patient['is_alive'] ? 'ป่วย — จะเสียชีวิตเมื่อจบคืนรอบ '.$patient['sickness_death_round'] : (($patient['death_reason'] ?? null) === 'sickness' ? 'เสียชีวิตจากโรค' : 'เสียชีวิต') }}</p>
        @endif
    @endforeach
    @foreach ($game['sickness_deaths'] ?? [] as $death)
    <p>ผลคืนล่าสุด: {{ $death['name'] }} เสียชีวิตจากโรค</p>
    @endforeach
</div>

@if ($game['game_mode'] === 'short')
<div class="event-panel">
    <div class="event-title">โหมดเกมสั้น · สูงสุด {{ $game['max_rounds'] }} รอบ</div>
    <div class="event-description">ถ้าหมาป่ายังชนะไม่ได้เมื่อครบเวลาหรือรอบ ฝ่ายชาวบ้านชนะ</div>
    @if ($game['match_end_time'] !== null && $game['status'] !== 'finished')
    <div id="match-timer" class="phase-round" aria-live="polite"></div>
    @endif
</div>
@endif

@if ($game['finish_reason'] !== null)
<div class="alert-game">จบเกมเพราะ {{ $game['finish_reason'] === 'time_limit' ? 'ครบเวลาที่กำหนด' : 'ครบจำนวนรอบที่กำหนด' }}</div>
@endif

@if ($game['current_phase'] === 'night' && $game['night_rule'] !== null)
<div class="event-panel">
    <div class="event-title">{{ $game['night_rule']['name'] }}</div>
    <div class="event-description">{{ $game['night_rule']['description'] }}</div>
</div>
@endif

<div data-dash-zone="actions">
@if ($game['can_guardian_act'])
<div class="section-card">
    <div class="result-title role-guardian">ผู้คุ้มกัน · เลือกป้องกันหนึ่งคน</div>
    <p class="action-description">ป้องกันตัวเองได้ แต่ห้ามป้องกันคนเดิมติดกัน เปลี่ยนเป้าหมายได้ก่อนหมดเวลา</p>
    <form method="POST" action="{{ route('games.guardian-action', ['code' => $game['room_code']]) }}">
        @csrf
        <input type="hidden" name="expected_end_time" value="{{ $game['phase_end_time'] }}">
        <label for="guardian-target">ผู้เล่นที่จะป้องกัน</label>
        <select id="guardian-target" name="target_uuid" required class="form-control">
            <option value="">เลือกผู้เล่น</option>
            @foreach ($game['guardian_targets'] as $target)
            <option value="{{ $target['player_uuid'] }}" @selected($game['my_guardian_target'] === $target['player_uuid'])>{{ $target['name'] }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn-game mt-2">ยืนยันการป้องกัน</button>
    </form>
</div>
@endif


</div>
@if ($game['current_phase'] === 'day_voting')
<div class="section-card">
    @if ($game['secret_ballot'])
    <div class="event-title">วันลงคะแนนลับ</div>
    <p class="action-description">ซ่อนคะแนนระหว่างโหวต เปิดผลรวมเมื่อจบช่วงโหวต คุณยังเปลี่ยนคะแนนได้ก่อนหมดเวลา</p>
    @else
    <div class="result-title">คะแนนรวมระหว่างโหวต</div>
    @forelse ($game['vote_totals'] as $uuid => $count)
    <div class="result-role"><span>{{ $uuid === 'skip' ? 'Skip' : (collect($game['players'])->firstWhere('player_uuid', $uuid)['name'] ?? 'ผู้เล่น') }}</span><span>{{ $count }} เสียง</span></div>
    @empty
    <p class="action-description">ยังไม่มีคะแนน</p>
    @endforelse
    @endif
</div>
@endif

<div data-dash-zone="results">
@if (! empty($game['vote_result']['totals']))
<div class="section-card">
    <div class="result-title">สรุปคะแนนรอบ {{ $game['vote_result']['round'] }}</div>
    @foreach ($game['vote_result']['totals'] as $uuid => $count)
    <div class="result-role"><span>{{ $uuid === 'skip' ? 'Skip' : (collect($game['players'])->firstWhere('player_uuid', $uuid)['name'] ?? 'ผู้เล่น') }}</span><span>{{ $count }} เสียง</span></div>
    @endforeach
</div>
@endif


<section class="section-card evidence-floating-panel" id="evidence-panel" aria-labelledby="evidence-title">
    <h2 class="result-title" id="evidence-title">กระดานหลักฐาน</h2>
    <p class="action-description">หลักฐานจริงจากระบบ อ้างอิงสถานะตอนออกหลักฐาน คำอธิบายของผู้เล่นอาจเป็นคำโกหก</p>
    @if (count($game['evidence']) === 0)<p class="action-description">ยังไม่มีหลักฐาน ระบบจะเพิ่มรายงานเมื่อจบคืน</p>@endif
    @foreach ($game['evidence'] as $clue)
    <div class="event-panel">
        <div class="event-title">เช้าวันที่ {{ $clue['round'] }}</div>
        <p class="event-description">{{ $clue['message'] }}</p>
        @if (count($clue['players']) > 0)
        <div>{{ implode(' · ', array_column($clue['players'], 'name')) }}</div>
        @if ($game['status'] === 'finished')
        @foreach ($clue['players'] as $suspect)
        @php($actualRole = collect($game['game_result']['players'])->firstWhere('player_uuid', $suspect['player_uuid'])['role'] ?? null)
        <div>{{ $suspect['name'] }}: <span class="role-{{ $actualRole }}">{{ $roleLabels[$actualRole] ?? 'ไม่ทราบบทบาท' }}</span></div>
        @endforeach
        @endif
        @endif
    </div>
    @endforeach
</section>


</div>
@foreach ($game['players'] as $player)
@if (! $player['is_connected'] && ! $player['has_left'] && $player['reconnect_deadline'] !== null)
<div class="alert-game">{{ $player['name'] }} หลุดจากเกม · กลับได้ก่อน {{ \Carbon\CarbonImmutable::parse($player['reconnect_deadline'])->timezone('Asia/Bangkok')->format('H:i:s') }}</div>
@endif
@endforeach

<script>
(() => {
    const endpoint = @json(route('games.presence', ['code' => $game['room_code']]));
    const version = @json($game['sync_version']);
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const deadline = @json($game['match_end_time']);
    let serverTime = Date.parse(@json($game['server_time']));
    let syncedAt = performance.now();
    let busy = false;
    let stopped = @json($game['status'] === 'finished');
    async function heartbeat() {
        if (busy || stopped) return;
        busy = true;
        try {
            const response = await fetch(endpoint, {method: 'POST', credentials: 'same-origin', headers: {'Accept': 'application/json', 'X-CSRF-TOKEN': csrf}});
            if ([403, 404].includes(response.status)) {
                stopped = true;
                window.location.replace(@json(route('rooms.index')));
                return;
            }
            if (!response.ok) return;
            const result = await response.json();
            if (result.sync_version !== version) {
                stopped = true;
                window.location.reload();
                return;
            }
            serverTime = Date.parse(result.server_time);
            syncedAt = performance.now();
        } catch (error) {
            // Retry on the next interval; the server grants a reconnect grace period.
        } finally {
            busy = false;
        }
    }
    setInterval(heartbeat, 5000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) heartbeat(); });
    window.addEventListener('online', heartbeat);
    heartbeat();
    const timer = document.getElementById('match-timer');
    if (timer && deadline) {
        const update = () => {
            const remaining = Math.max(0, Math.ceil((Date.parse(deadline) - serverTime - (performance.now() - syncedAt)) / 1000));
            timer.textContent = `เวลาเกมเหลือ ${Math.floor(remaining / 60)}:${String(remaining % 60).padStart(2, '0')}`;
            if (remaining === 0) heartbeat();
        };
        setInterval(update, 1000);
        update();
    }
})();
</script>

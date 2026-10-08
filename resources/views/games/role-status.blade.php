@php
    $roleGoal = $game['my_role'] === 'werewolf'
        ? 'เป้าหมาย: หมาป่าที่มีชีวิต ≥ ฝ่ายชาวบ้าน'
        : 'เป้าหมาย: กำจัดหมาป่าทั้งหมด';
    $latestSeerCheck = $game['my_role'] === 'seer' ? collect($game['my_seer_results'])->last() : null;
@endphp
<aside class="persistent-role role-{{ $game['my_role'] }}" aria-label="บทบาทและเป้าหมายของคุณ">
    @if ($game['my_role'])
    <img src="{{ asset('images/roles/'.$game['my_role'].'.png') }}" alt="ภาพบทบาท{{ $roleLabels[$game['my_role']] ?? $game['my_role'] }}" width="72" height="72">
    <div>
        <div class="persistent-role-label">ผู้เล่น: <strong>{{ $viewerState['name'] ?? 'ผู้เล่น' }}</strong></div>
        <div class="persistent-role-label player-state-{{ ($viewerState['has_left'] ?? false) ? 'left' : (($viewerState['is_alive'] ?? true) ? 'alive' : 'dead') }}">{{ ($viewerState['has_left'] ?? false) ? '↪ ออกจากเกมแล้ว' : (($viewerState['is_alive'] ?? true) ? '● มีชีวิต' : '☠ เสียชีวิต') }}</div>
        <strong class="persistent-role-name">{{ $roleLabels[$game['my_role']] ?? $game['my_role'] }}</strong>
        <div class="persistent-role-goal">{{ $roleGoal }}</div>
        @if ($latestSeerCheck !== null)
        <div class="persistent-seer-check" aria-live="polite">
            <strong>🔮 ผลตรวจล่าสุด:</strong> {{ $latestSeerCheck['target_name'] }}
            <span class="{{ $latestSeerCheck['is_werewolf'] ? 'role-werewolf' : 'role-seer' }}">{{ $latestSeerCheck['is_werewolf'] ? 'หมาป่า' : 'ไม่ใช่หมาป่า' }}</span>
            <small>· รอบ {{ $latestSeerCheck['round'] }} · เฉพาะคุณ</small>
        </div>
        @endif

        @if ($game['game_mode'] === 'short' && $game['my_role'] !== 'werewolf')
        <div class="persistent-role-goal">หรืออยู่รอดจนเกมครบเวลา/รอบ</div>
        @endif
    </div>
    @else
    <span>กำลังแจก Role</span>
    @endif
</aside>

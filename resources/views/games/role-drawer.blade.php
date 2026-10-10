<button type="button" id="role-drawer-toggle" class="role-edge-tab" aria-controls="role-drawer" aria-expanded="false" aria-haspopup="dialog"><span>ดูบทบาท</span></button>
<dialog id="role-drawer" class="role-drawer" aria-labelledby="role-drawer-title"
    data-intro-key="{{ $game['game_uuid'] }}:{{ session('player_uuid') }}"
    data-intro-enabled="{{ $game['status'] === 'in_progress' && $game['my_role'] !== null && ($viewerState['is_alive'] ?? true) && ! ($viewerState['has_left'] ?? false) ? 'true' : 'false' }}">
    <header class="role-drawer-header">
        <div><span class="role-drawer-eyebrow">เฉพาะคุณ</span><h2 id="role-drawer-title">บทบาทของฉัน</h2></div>
        <button type="button" id="role-drawer-close" class="role-drawer-close" aria-label="ปิดรายละเอียดบทบาท">✕</button>
    </header>
    <div class="role-drawer-content">
        @include('games.role-guide')
        <div class="role-drawer-teammates">
{{-- werewolf teammates --}}
                    @if ($game['my_role'] === 'werewolf')

                    <div class="action-panel mb-3">

                        <div class="action-title">
                            หมาป่าร่วมทีม
                        </div>

                        @forelse ($game['werewolf_teammates'] as $teammate)

                        <div class="player-card mb-2">

                            <div class="player-avatar">
                                {{ mb_substr($teammate['name'], 0, 1) }}
                            </div>

                            <div class="player-details">

                                <div class="player-name">
                                    {{ $teammate['name'] }}
                                </div>

                                <div class="player-status">

                                    @if ($teammate['has_left'])
                                    ออกจากเกมแล้ว
                                    @elseif (!$teammate['is_alive'])
                                    <span style="color: red;">เสียชีวิต</span>
                                    @else
                                    มีชีวิต
                                    @endif

                                </div>

                            </div>

                        </div>

                        @empty

                        <div class="alert-game">
                            คุณเป็นหมาป่าเพียงคนเดียว
                        </div>

                        @endforelse

                    </div>

                    @endif
        </div>
    </div>
    <footer class="role-intro-footer" hidden>
        <p>เปิดดูซ้ำได้ที่ปุ่ม “ดูบทบาท” ด้านข้าง</p>
        <button type="button" id="role-intro-confirm">เข้าใจแล้ว · เริ่มเล่น</button>
    </footer>
</dialog>

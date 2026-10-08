                    {{-- Final result comes from the server snapshot. --}}
                    @if ($game['game_result'] !== null)
                    <section class="section-card" aria-labelledby="game-result-title">
                        <div class="winner-panel">
                            <div class="winner-label">MATCH SUMMARY</div>
                            <h2 class="winner-title" id="game-result-title">
                                {{ $game['game_result']['winner'] === 'werewolf'
                                    ? 'ทีมหมาป่าชนะ'
                                    : 'ทีมชาวบ้านชนะ' }}
                            </h2>
                        </div>
                        <div class="result-card">
                            <div class="result-title">บทบาทของผู้เล่นทุกคน</div>
                            @foreach ($game['game_result']['players'] as $player)
                            <div class="result-role">
                                <span>{{ $player['name'] }}</span>
                                <span class="result-role-value role-{{ $player['role'] }}">
                                    {{ $roleLabels[$player['role']] ?? $player['role'] }}
                                    <small>· {{ $player['has_left'] ? 'ออกจากเกม' : ($player['is_alive'] ? 'มีชีวิต' : 'เสียชีวิต') }}</small>
                                </span>
                            </div>
                            @endforeach
                        </div>
                    </section>
                    @endif



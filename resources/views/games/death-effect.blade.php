@if (! ($viewerState['is_alive'] ?? true) && ! ($viewerState['has_left'] ?? false))
<div id="death-overlay" class="death-overlay" role="dialog" aria-modal="true" aria-labelledby="death-title" hidden>
    <div class="death-card">
        <div class="death-skull" aria-hidden="true">☠</div>
        <h2 id="death-title">คุณเสียชีวิตแล้ว</h2>
        <p>{{ $viewerState['name'] ?? 'ผู้เล่น' }}</p>
        <p>{{ ($viewerState['death_reason'] ?? null) === 'sickness' ? 'เสียชีวิตจากโรคในหมู่บ้าน' : 'บทบาทของคุณในเกมจบลงแล้ว' }}</p>
        <p>ติดตามเกมต่อและพูดคุยในแชทผู้เสียชีวิตได้</p>
        <button type="button" id="dismiss-death" class="btn-secondary-game">ดูเกมต่อ</button>
    </div>
</div>
<script>
(() => {
    const overlay = document.getElementById('death-overlay');
    const button = document.getElementById('dismiss-death');
    const key = 'werewolf-death-seen:' + @json($game['game_uuid']) + ':' + @json(session('player_uuid'));
    let seen = false;
    try { seen = sessionStorage.getItem(key) === '1'; } catch {}
    if (seen) return;
    const previousFocus = document.activeElement;
    const regions = Array.from(document.body.children).filter(node => node !== overlay && !['SCRIPT', 'STYLE'].includes(node.tagName));
    const inertStates = regions.map(node => node.inert);
    regions.forEach(node => { node.inert = true; });
    overlay.hidden = false;
    button.focus();
    function dismiss() {
        overlay.hidden = true;
        regions.forEach((node, index) => { node.inert = inertStates[index]; });
        try { sessionStorage.setItem(key, '1'); } catch {}
        if (previousFocus && previousFocus !== document.body) previousFocus.focus();
    }
    button.addEventListener('click', dismiss);
    overlay.addEventListener('keydown', event => {
        if (event.key === 'Escape') dismiss();
        if (event.key === 'Tab') { event.preventDefault(); button.focus(); }
    });
})();
</script>
@endif
<style>
body[data-game-phase="day_discussion"],body[data-game-phase="day_voting"] {background:radial-gradient(ellipse at top,#54422a 0,#272534 50%,#141725 100%);color:#fff4dc}
body[data-game-phase="night"] {background:radial-gradient(ellipse at top,#121d3d 0,#080d1c 65%,#030611 100%);color:#dbeafe}
body[data-game-phase="day_discussion"] .main-panel,body[data-game-phase="day_voting"] .main-panel {background:linear-gradient(145deg,#342f2d,#1b2030);border-color:#d6a64c55}
body[data-game-phase="night"] .main-panel {background:linear-gradient(145deg,#0d1429,#070d19);border-color:#818cf855}
body[data-game-phase="day_discussion"] .dashboard-tab[aria-selected="true"],body[data-game-phase="day_voting"] .dashboard-tab[aria-selected="true"] {background:#775026;border-color:#fbbf24;color:#fff4dc}
body[data-game-phase="night"] .dashboard-tab[aria-selected="true"] {background:#312e81;border-color:#a5b4fc;color:#e0e7ff}
.player-state-alive {color:#6ee7b7!important;font-weight:600}
.player-state-dead {color:#fda4af!important;font-weight:600}
.player-state-left {color:#94a3b8!important;font-weight:600;font-style:italic}
body[data-player-state="dead"]::after {content:"";position:fixed;inset:0;backdrop-filter:grayscale(1);-webkit-backdrop-filter:grayscale(1);pointer-events:none;z-index:1005}
body[data-player-state="dead"] #game-notice {filter:grayscale(1)}
.death-overlay {position:fixed;inset:0;z-index:1200;display:grid;place-items:center;background:#000c;backdrop-filter:grayscale(1) blur(4px);padding:20px}
.death-overlay[hidden] {display:none}
.death-card {max-width:440px;text-align:center;background:#171717;color:#f5f5f5;border:1px solid #777;border-radius:20px;padding:24px 32px;box-shadow:0 0 80px #ffffff15;animation:death-enter .65s ease-out}
.death-card p {color:#d4d4d4;font-size:14px;line-height:1.5}
.death-skull {font-size:100px;line-height:1.2;color:#eee;text-shadow:0 0 25px #ffffff33}
.death-card button {background:#333;color:#fff;border-color:#777}
@keyframes death-enter {from {opacity:0;transform:scale(.85) translateY(15px)} to {opacity:1;transform:scale(1) translateY(0)}}
@media(prefers-reduced-motion:reduce) {.death-card {animation:none}}
</style>

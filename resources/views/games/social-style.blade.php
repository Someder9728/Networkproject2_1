<style>
.cute-avatar {display:block;width:60px;height:60px;flex-shrink:0;filter:drop-shadow(0 4px 8px #0002)}
.round-table {position:relative;width:100%;height:430px;max-width:650px;margin:12px auto 20px;isolation:isolate}
.table-surface {position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);width:56%;height:56%;border-radius:50%;background:radial-gradient(ellipse at 35% 25%,#574669,#292b42 75%);border:10px solid #76677e;outline:1px solid #bdb0c055;box-shadow:0 16px 25px #0004,inset 0 0 0 3px #cab9c033;display:flex;align-items:center;justify-content:center;flex-direction:column;text-align:center;color:#f5e9d4;gap:8px;padding:12px}
.table-surface>span {font-size:30px}.table-surface strong {font-size:17px}.table-surface small {font-size:12px;line-height:1.7;color:#e0d6dd}
.table-seats,.game-dashboard .players-grid.table-seats {position:absolute;inset:0;display:block}
.round-table .player-card,.game-dashboard .round-table .player-card {position:absolute;left:var(--seat-x);top:var(--seat-y);transform:translate(-50%,-50%);width:86px;min-height:0;border:0!important;border-radius:0;background:transparent!important;box-shadow:none;padding:0;display:block;text-align:center}
.seat-open {border:0;background:transparent;color:#e9e3f3;display:flex;flex-direction:column;align-items:center;width:100%;padding:0;gap:2px;border-radius:12px;text-decoration:none;cursor:pointer}
.seat-open .cute-avatar {width:56px;height:56px;border:2px solid transparent;border-radius:50%;transition:transform .15s,border-color .15s}
button.seat-open:hover .cute-avatar,button.seat-open:focus-visible .cute-avatar {transform:translateY(-4px);border-color:#e9d5ff}
.round-table .you .seat-open .cute-avatar {border:3px solid #a78bfa;box-shadow:0 0 0 3px #a78bfa33}
.round-table .roster-selected .seat-open .cute-avatar {border:3px solid #fbbf24;box-shadow:0 0 0 3px #fbbf2444}
.round-table .dead .seat-open .cute-avatar,.round-table .left-player .seat-open .cute-avatar {filter:grayscale(1);opacity:.65}
.seat-name {font-size:12px;font-weight:700;display:block;max-width:100%;overflow:hidden;white-space:nowrap;text-overflow:ellipsis;background:#1d223cdd;border-radius:7px;padding:2px 6px}
.seat-state {font-size:10px;color:#a7f3d0;line-height:1.2}
.round-table .empty-seat {width:58px;height:64px;border:2px dashed #b8a6c666!important;border-radius:16px;background:#ffffff08!important;display:flex;align-items:center;justify-content:center;flex-direction:column;color:#c4b5c8}.empty-seat>span {font-size:24px}.empty-seat small {font-size:10px}
.seat-dialog,.avatar-picker {color:#f1eaf8;background:#1c2035;border:1px solid #9f8cc6;border-radius:24px;padding:24px;box-shadow:0 20px 80px #0007;max-width:min(520px,calc(100vw - 24px));max-height:calc(100dvh - 24px);overflow:auto;text-align:left}
.seat-dialog:not([open]),.avatar-picker:not([open]) {display:none}
dialog::backdrop {background:#0c1024bb;backdrop-filter:blur(5px)}
.seat-dialog {width:340px;text-align:center}.seat-dialog>.cute-avatar {width:110px;height:110px;margin:0 auto 16px}.seat-dialog .player-details {text-align:center}.seat-dialog .player-name {font-size:20px!important}.seat-dialog .player-status {margin:10px 0}.seat-dialog form {margin:18px 0 0}.seat-dialog .seat-whisper {margin-top:10px;width:100%}.seat-close {position:absolute;right:12px;top:10px;border:0;background:transparent;color:#d9cdef;font-size:20px;padding:8px}
.look-button,.picker-footer button {border:1px solid #a78bfa;background:#534375;color:#fff;border-radius:12px;padding:10px 16px;font-size:14px;font-weight:600;cursor:pointer;line-height:1.4}.look-button:hover {background:#6d549c}.look-button:disabled {opacity:.45;cursor:not-allowed}.look-hint {display:inline-block;color:#ac9eba;margin-left:8px;font-size:12px}
.picker-heading {display:flex;justify-content:space-between;gap:14px}.picker-heading h2 {font-size:22px;margin:0 0 6px}.picker-heading p {font-size:12px;color:#c5bad8;margin:0}.picker-heading button {border:0;background:transparent;color:#e9e3f3;font-size:20px;align-self:start}.avatar-preview>.cute-avatar {width:100px;height:100px;margin:12px auto}
.look-options {border:0;padding:0;margin:12px 0}.look-options legend {font-size:13px;font-weight:600;margin-bottom:6px}.look-options>div {display:flex;gap:6px;flex-wrap:wrap}.look-option {cursor:pointer;position:relative}.look-option input {position:absolute;opacity:0;width:1px;height:1px}.look-option>span {border:1px solid #746786;border-radius:12px;padding:7px 10px;display:flex;align-items:center;gap:4px;font-size:12px;flex-direction:column;min-width:44px}.look-option .cute-avatar {width:42px;height:42px}.look-option input:checked+span {border-color:#d9b5ff;box-shadow:0 0 0 2px #a78bfa77;background:#504364}.look-option input:focus-visible+span {outline:2px solid #fff;outline-offset:3px}.look-option i {width:24px;height:24px;border-radius:50%;display:block}.picker-footer {display:flex;justify-content:flex-end;gap:8px;margin-top:16px}
.play-guide {background:linear-gradient(120deg,#363053,#252b44);border:1px solid #a78bfa55;border-radius:16px;padding:18px;margin-bottom:12px}.guide-eyebrow {font-size:11px;color:#c4b5fd;letter-spacing:.5px}.play-guide h2 {font-size:21px;margin:6px 0 10px;color:#fff}.play-guide p {font-size:13px;line-height:1.8;color:#ddd4e9;margin-bottom:12px}.play-guide .look-button {font-size:12px;padding:8px 12px;margin:3px}
.game-dashboard .dashboard-board {grid-template-columns:minmax(380px,1.25fr) minmax(340px,1fr)}
.game-dashboard .dashboard-roster {background:linear-gradient(160deg,#272137aa,#151d3099)}
.game-dashboard .round-table {height:clamp(320px,48dvh,460px);margin:8px auto}.game-dashboard .dashboard-tab[aria-selected=true] {background:#655085;border-color:#d2baf4}.game-dashboard .dashboard-tab {border-radius:12px}
.chat-recipient {display:flex;flex-direction:column;gap:5px;font-size:12px;padding:7px 0;color:#c4b5fd}.chat-recipient select {max-width:100%;background:#20243c;color:#f5f0ff;border:1px solid #77608b;border-radius:9px;padding:8px;font-size:13px}.chat-recipient.private {color:#f9a8d4}.chat-private-note {font-size:11px;color:#ddbcde;line-height:1.5;padding-bottom:5px}.chat-message-bubble.private {border:1px solid #d58dba!important;background:#44344b!important}.chat-private-label {font-size:10px;display:block;color:#f9b9df;margin-bottom:4px}.voice-controls button {border:1px solid #786686;border-radius:8px;background:#34334f;color:#fff;padding:6px 10px;font-size:12px}
@media(max-width:1000px) and (min-width:761px) {.game-dashboard .dashboard-board {grid-template-columns:minmax(330px,1fr) minmax(285px,1fr)}.game-dashboard .round-table {height:350px}.round-table .player-card,.game-dashboard .round-table .player-card {width:66px}.seat-open .cute-avatar {width:46px;height:46px}.seat-name {font-size:11px}}
@media(max-width:760px) {.round-table {height:340px;max-width:440px}.round-table .player-card,.game-dashboard .round-table .player-card {width:60px}.seat-open .cute-avatar {width:44px;height:44px}.seat-name {font-size:10px;padding:1px 4px}.seat-state {font-size:9px}.table-surface {border-width:7px;gap:5px;padding:7px}.table-surface>span {font-size:22px}.table-surface strong {font-size:13px}.table-surface small {font-size:10px}.game-dashboard .round-table {height:clamp(260px,43dvh,360px)}.play-guide {padding:12px}.play-guide h2 {font-size:17px}.look-options>div {gap:5px}.look-option>span {padding:6px 7px}.avatar-picker {padding:16px}.round-table .empty-seat {width:46px;height:52px}}
@media(max-width:380px) {.round-table .player-card,.game-dashboard .round-table .player-card {width:49px}.seat-open .cute-avatar {width:35px;height:35px}.seat-name {font-size:9px}.seat-state {font-size:8px}}

.game-dashboard .table-zone {display:flex;flex-direction:column;flex:1;min-height:0}
.game-dashboard .table-zone>.section-heading,.game-dashboard .table-zone>.section-card,.game-dashboard .table-zone>.action-description,.game-dashboard .table-zone>.action-area {flex-shrink:0}
.game-dashboard .table-zone>.action-description {font-size:12px;margin:4px 0}
.game-dashboard .table-zone>.round-table {flex:1;min-height:260px;max-height:430px;height:auto;margin:4px auto 8px}
@media(max-width:760px) {
 .game-dashboard .game-page {height:calc(100dvh - 108px)}
 .game-dashboard .phase-row {display:grid;grid-template-columns:minmax(0,1fr) auto;gap:3px}
 .game-dashboard .phase-banner .dashboard-overview {grid-column:1/-1;font-size:10px}
 .game-dashboard .phase-label {font-size:8px;letter-spacing:.3px}
 .game-dashboard .phase-round {font-size:10px}
 .game-dashboard .timer-label {display:none}
 .game-dashboard .timer-box {min-width:0;padding:4px 5px}
 .game-dashboard .timer {font-size:15px}
 .game-dashboard .phase-title {font-size:12px}
 .game-dashboard .persistent-role-name {font-size:13px}
 .game-dashboard .persistent-role-goal {font-size:9px}
 .game-dashboard .dashboard-tabs {gap:4px}.game-dashboard .dashboard-tab {font-size:11px;padding:7px 8px}
 .game-dashboard .table-zone>.round-table {min-height:245px;max-height:380px}
 .game-dashboard .table-zone>.section-card {padding:8px;margin-bottom:3px}
 .game-dashboard .table-zone>.section-card .btn-game {font-size:11px;padding:7px 9px}
 .game-dashboard .table-zone>.action-area {margin:4px 0}
 .game-dashboard .table-zone>.action-area button {font-size:11px;padding:8px 10px}
 .game-dashboard .section-title {font-size:15px}
 .game-dashboard .section-count {font-size:10px}
 .game-dashboard .chat-floating-toggle {bottom:7px;right:8px;padding:9px 12px;font-size:12px}
 .game-dashboard .evidence-floating-toggle {bottom:7px;left:8px;right:auto;padding:9px 12px;font-size:11px}
 .game-dashboard #sound-toggle {bottom:7px;right:auto;left:50%;transform:translateX(-50%);font-size:10px;padding:9px 11px}
 .game-dashboard #chat-panel {bottom:56px;max-height:calc(100dvh - 80px)}
 .game-dashboard #evidence-panel {bottom:56px;max-height:calc(100dvh - 80px)}
}
</style>

<style>
.header-leave {margin:0;padding:0;flex-shrink:0}
.header-leave form {margin:0}
.header-leave-button {cursor:pointer;color:#fecaca!important;border-color:#f8717155!important;background:#7f1d1d44!important;white-space:nowrap}
@media(max-width:760px) {.game-dashboard .brand-icon {display:none}.game-dashboard .header-actions {gap:5px}.game-dashboard .header-leave-button {font-size:11px;padding:6px}}
body.game-dashboard {height:100dvh;overflow:hidden}
.game-dashboard .topbar {height:58px;min-height:58px;padding:8px 20px}
.game-dashboard .brand-subtitle,.game-dashboard .room-header {display:none}
.game-dashboard .game-page {padding:10px 16px;height:calc(100dvh - 58px);max-width:1600px;margin:auto}
.game-dashboard .game-layout {display:block;height:100%}
.game-dashboard .main-panel {height:100%;overflow:hidden}
.game-dashboard .content {height:100%;padding:12px;display:flex;flex-direction:column;gap:10px;overflow:hidden}
.game-dashboard .phase-banner {margin:0;padding:10px 16px;flex-shrink:0}
.game-dashboard .phase-info {gap:10px}
.game-dashboard .phase-subtitle,.game-dashboard .phase-desc {display:none}
.game-dashboard .phase-title {font-size:20px}
.dashboard-overview {font-size:12px;color:#e2e8f0;margin-top:4px}
.dashboard-board {flex:1;min-height:0;display:grid;grid-template-columns:minmax(320px,1fr) minmax(360px,1.1fr);gap:14px}
.dashboard-roster,.dashboard-right {min-height:0;display:flex;flex-direction:column;border:1px solid #ffffff20;border-radius:14px;background:#0f172a88;padding:12px}
.dashboard-roster {overflow:auto}
.dashboard-tabs {display:flex;gap:6px;flex-shrink:0;flex-wrap:wrap;margin-bottom:10px}
.dashboard-tab {border:1px solid #64748b;border-radius:9px;padding:8px 12px;background:#172033;color:#cbd5e1;cursor:pointer;font-size:14px}
.dashboard-tab[aria-selected="true"] {background:#5b21b6;border-color:#c4b5fd;color:#fff}
.dashboard-pane {overflow:auto;flex:1;min-height:0;padding:2px 4px}
.dashboard-pane[hidden] {display:none!important}
.dashboard-empty {color:#94a3b8;padding:20px;text-align:center}
.game-dashboard .players-grid {grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}
.game-dashboard .player-card {padding:10px;gap:8px;min-height:75px}
.game-dashboard .player-avatar {width:32px;height:32px;font-size:15px;flex-shrink:0}
.game-dashboard .player-name {font-size:14px;overflow-wrap:anywhere}
.game-dashboard .player-status {font-size:12px}
.game-dashboard .section-heading {margin:0 0 8px}
.game-dashboard .section-card,.game-dashboard .action-panel,.game-dashboard .event-panel {padding:12px;margin:0 0 10px}
.game-dashboard .vote-target-list {display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}
.game-dashboard .vote-target-card {padding:10px;min-height:60px}
.game-dashboard .action-area:has(#finish-discussion-form),.game-dashboard .action-area:has(#finish-voting-form),.game-dashboard .action-area:has(#finish-night-form) {display:none}
.game-dashboard #game-result-title {font-size:22px}
.game-dashboard section[aria-labelledby="game-result-title"] {flex-shrink:0;margin:0;padding:10px;max-height:40%;overflow:auto}
.game-dashboard section[aria-labelledby="game-result-title"] .result-card {display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:2px 14px;padding:8px}
.game-dashboard section[aria-labelledby="game-result-title"] .result-title {grid-column:1/-1}
.game-dashboard .winner-panel {padding:6px;margin:0}
.game-dashboard .winner-label {font-size:11px}
.dashboard-mobile-players {display:none}
@media(max-width:760px) {
 .game-dashboard .topbar {padding:6px 10px}
 .game-dashboard .brand-title {font-size:14px}
 .game-dashboard .top-pill {font-size:11px;padding:6px}
 .game-dashboard .game-page {padding:6px}
 .game-dashboard .content {padding:8px}
 .dashboard-board {display:flex;flex-direction:column;gap:6px}
 .dashboard-right {display:contents}
 .dashboard-tabs {order:-1;margin:0}
 .dashboard-tab {padding:7px 9px;font-size:12px}
 .dashboard-mobile-players {display:block}
 .dashboard-roster {flex:1}
 .dashboard-pane {border:1px solid #ffffff20;border-radius:12px;background:#0f172a88;padding:10px}
 .dashboard-board[data-mobile-view="players"] .dashboard-pane {display:none!important}
 .dashboard-board:not([data-mobile-view="players"]) .dashboard-roster {display:none}
 .game-dashboard .phase-title {font-size:16px}
 .game-dashboard section[aria-labelledby="game-result-title"] .result-card {grid-template-columns:1fr}
}

.phase-banner {display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:16px;align-items:center}
.phase-banner .phase-row {margin:0;min-width:0;flex-wrap:wrap;gap:8px}
.phase-banner .dashboard-overview,.phase-banner #match-timer {flex-basis:100%;margin:0}
.persistent-role {display:flex;align-items:center;gap:14px;border-left:1px solid #ffffff30;padding-left:16px;min-width:0}
.persistent-role img {width:72px;height:72px;object-fit:cover;border-radius:12px;flex-shrink:0;border:1px solid currentColor}
.persistent-role-label {font-size:10px;color:#cbd5e1}
.persistent-role-name {font-size:19px}
.persistent-role-goal {font-size:12px;line-height:1.5;color:#e2e8f0;overflow-wrap:anywhere}
@media(max-width:760px) {
 .game-dashboard .phase-banner {padding:10px;gap:8px}
 .persistent-role {padding-left:8px;gap:7px}
 .persistent-role img {width:44px;height:54px;border-radius:8px}
 .persistent-role-name {font-size:14px}
 .persistent-role-goal {font-size:10px}
 .game-dashboard .phase-icon {display:none}
 .game-dashboard .timer-box {padding:6px;min-width:50px}
 .game-dashboard .timer {font-size:17px}
 .game-dashboard .phase-title {font-size:14px}
}

.evidence-floating-toggle {position:fixed;bottom:76px;right:18px;z-index:1001;border:1px solid #fbbf24;border-radius:24px;background:#453013;color:#fef3c7;padding:10px 16px;cursor:pointer;font-size:13px}
.evidence-floating-toggle.active {background:#92400e}
#evidence-panel {position:fixed;right:18px;bottom:125px;width:min(390px,calc(100vw - 36px));max-height:calc(100dvh - 180px);overflow:auto;z-index:1000;background:#111827;color:#f1f5f9;border:1px solid #d6a64c88;border-radius:16px;box-shadow:0 12px 50px #0009;padding:16px;margin:0}
#evidence-panel[hidden] {display:none!important}

.phase-banner > .persistent-role {grid-column:2;grid-row:1}
.latest-game-event {grid-column:1/-1;grid-row:2;font-size:12px;line-height:1.5;background:#0003;padding:7px 10px;border-radius:8px;color:#e2e8f0}
.latest-game-event strong {color:#fde68a;margin-right:8px}

.persistent-seer-check {font-size:12px;line-height:1.5;margin-top:5px;padding:5px 8px;background:#0003;border:1px solid #a78bfa55;border-radius:8px;color:#e2e8f0;overflow-wrap:anywhere}
.persistent-seer-check small {font-size:10px;color:#cbd5e1}
@media(max-width:760px) {.persistent-seer-check {font-size:10px;padding:4px}.persistent-seer-check small {font-size:9px}}
</style>

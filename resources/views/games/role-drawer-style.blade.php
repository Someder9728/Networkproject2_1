<style>
.role-edge-tab {position:fixed;right:0;top:38%;width:34px;height:116px;z-index:1010;display:flex;align-items:center;justify-content:center;border:1px solid #d9bb80;border-right:0;border-radius:13px 0 0 13px;background:linear-gradient(160deg,#4d3f59,#242139);color:#fff0cb;box-shadow:-4px 4px 18px #0005;cursor:pointer}
.role-edge-tab span {display:block;white-space:nowrap;transform:rotate(-90deg);font-size:13px;font-weight:600;letter-spacing:.4px}
.role-edge-tab:hover {background:#5d4b70}.role-edge-tab:focus-visible {outline:2px solid #fff;outline-offset:-4px}
.role-drawer {position:fixed;inset:0 0 0 auto;margin:0;width:min(420px,100vw);max-width:none;height:100dvh;max-height:none;padding:0;border:0;border-left:1px solid #8b729e;background:linear-gradient(165deg,#211c30,#0f1323);color:#f6ecda;box-shadow:-20px 0 70px #0007;overflow:hidden}
.role-drawer:not([open]) {display:none}
.role-drawer[open] {display:flex;flex-direction:column;animation:role-slide-in 260ms ease-out both}
.role-drawer[open].is-closing {animation:role-slide-out 220ms ease-in both}
.role-drawer::backdrop {background:#080b18a6;backdrop-filter:blur(3px)}
.role-drawer-header {display:flex;align-items:center;justify-content:space-between;gap:12px;padding:18px 22px;border-bottom:1px solid #bca47b30;flex-shrink:0;background:#151324}
.role-drawer-eyebrow {font-size:10px;letter-spacing:.4px;color:#c8b697}
.role-drawer-header h2 {font-size:19px;margin:3px 0 0;color:#f7ecdb}
.role-drawer-close {display:grid;place-items:center;width:38px;height:38px;border:1px solid #7b688e;border-radius:12px;background:#2c253b;color:#f7ecdb;font-size:18px;cursor:pointer}
.role-drawer-close:hover {background:#51415f}
.role-drawer-content {padding:22px 24px 28px;overflow:auto;overscroll-behavior:contain;flex:1;min-height:0;scrollbar-width:thin;scrollbar-color:#756281 transparent}
.werewolf-role-card {--card-accent:#e2c286;--card-glow:#bb9146;--card-ink:#251f37;position:relative;border:2px solid var(--card-accent);outline:1px solid #dac29255;outline-offset:5px;border-radius:18px;padding:9px;background:linear-gradient(135deg,#fff3d933,#fff3d906 35%,#9f816d22),#171321;box-shadow:0 16px 36px #0005,0 0 24px color-mix(in srgb,var(--card-glow) 18%,transparent);margin:4px 0 24px;color:#f5e9d5;overflow:hidden}
.role-card-werewolf {--card-accent:#ce9690;--card-glow:#b34e57;--card-ink:#3b2028}
.role-card-seer {--card-accent:#c6aff3;--card-glow:#8e65c7;--card-ink:#272039}
.role-card-guardian {--card-accent:#9dcebb;--card-glow:#459780;--card-ink:#17302d}
.role-card-topline {display:flex;justify-content:space-between;align-items:center;gap:8px;padding:6px 7px 12px;color:var(--card-accent);font-size:10px;letter-spacing:1.4px}
.role-card-topline span:first-child {font-family:'Cinzel',serif;font-weight:700;letter-spacing:2px}
.role-card-art {position:relative;border:1px solid var(--card-accent);border-radius:9px 9px 0 0;overflow:visible;background:#141326}
.role-card-art img {display:block;width:100%;height:248px;object-fit:cover;object-position:center 35%;border-radius:8px 8px 0 0}
.role-card-art:after {content:'';position:absolute;inset:0;pointer-events:none;background:linear-gradient(transparent 65%,var(--card-ink));border-radius:8px 8px 0 0}
.role-card-seal {position:absolute;bottom:-19px;left:50%;transform:translateX(-50%);z-index:1;width:38px;height:38px;display:grid;place-items:center;border:2px solid var(--card-accent);border-radius:50%;background:var(--card-ink);color:var(--card-accent);font-size:23px;box-shadow:0 3px 10px #0006}
.role-card-body {padding:25px 18px 10px;border:1px solid var(--card-accent);border-top:0;border-radius:0 0 9px 9px;background:linear-gradient(var(--card-ink),#151322)}
.role-card-caption {display:block;text-align:center;color:#ccbcab;font-size:10px}
.role-card-title {text-align:center;font-size:28px;letter-spacing:1px;color:var(--card-accent);margin:3px 0 5px;font-weight:700}
.role-card-rule {display:flex;align-items:center;gap:8px;justify-content:center;color:var(--card-accent);margin:4px 0 16px;font-size:12px}
.role-card-rule:before,.role-card-rule:after {content:'';height:1px;flex:1;background:linear-gradient(90deg,transparent,var(--card-accent),transparent)}
.role-card-body h4 {font-size:12px;color:var(--card-accent);margin:0 0 7px;font-weight:600}
.role-card-body p {font-size:13px;line-height:1.85;color:#ece1d5;margin:0}
.role-card-description {margin-bottom:15px}
.role-card-win {padding:12px;border:1px solid #f5e9d52a;border-radius:9px;background:#ffffff06}
.role-card-limit {border-left:2px solid var(--card-accent);padding-left:9px;margin:0 0 15px!important;font-size:12px!important}
.role-card-short {margin-top:13px!important;font-size:11px!important;color:#c8baad!important}
.role-card-bottomline {font-family:'Cinzel',serif;font-size:9px;color:var(--card-accent);letter-spacing:1.2px;text-align:center;margin-top:18px;opacity:.8}
.role-drawer-teammates .action-panel {padding:15px;border:1px solid #b8767655;border-radius:14px;background:#211b2a}
.role-drawer-teammates .action-title {font-size:16px}
.role-drawer-teammates .player-card {min-height:0;padding:10px;background:#ffffff06}
body[data-player-state="dead"] .role-drawer {filter:grayscale(1)}
@keyframes role-slide-in {from {transform:translateX(100%)}to {transform:translateX(0)}}
@keyframes role-slide-out {from {transform:translateX(0)}to {transform:translateX(100%)}}
@media(max-width:760px) {.role-edge-tab {width:28px;height:100px;top:40%}.role-edge-tab span {font-size:12px}.role-drawer {width:min(380px,100vw)}.role-drawer-content {padding:17px 20px 24px}.role-drawer-header {padding:13px 18px}.role-card-art img {height:228px}.role-card-title {font-size:25px}.role-card-body {padding-left:14px;padding-right:14px}}
@media(prefers-reduced-motion:reduce) {.role-drawer[open],.role-drawer[open].is-closing {animation:none}}

.role-intro-footer {padding:10px 20px 16px;background:#151324;border-top:1px solid #bca47b30;text-align:center;flex-shrink:0}
.role-intro-footer[hidden] {display:none}
.role-intro-footer p {font-size:11px;color:#cbbcaf;margin:0 0 8px}
.role-intro-footer button {width:100%;border:1px solid #e0bd7f;border-radius:11px;background:linear-gradient(120deg,#8b6740,#69517b);color:#fff3dd;font-size:14px;font-weight:600;padding:11px 16px;cursor:pointer}
.role-drawer.role-drawer-intro {inset:0;margin:auto;width:min(460px,calc(100vw - 24px));height:fit-content;max-height:94dvh;border:1px solid #ba9f79;border-radius:20px;animation:role-intro-in 220ms ease-out both;box-shadow:0 20px 100px #0009}
.role-drawer.role-drawer-intro.is-closing {animation:role-intro-out 220ms ease-in both}
.role-drawer-intro .role-drawer-header {padding:11px 18px}
.role-drawer-intro .role-drawer-content {padding:15px 20px 14px}
.role-drawer-intro .werewolf-role-card {margin-bottom:8px}
.role-drawer-intro .role-card-art img {height:140px}
.role-drawer-intro .role-card-body {padding:22px 15px 9px}
.role-drawer-intro .role-card-title {font-size:25px}
.role-drawer-intro .role-card-body p {font-size:12px;line-height:1.65}
.role-drawer-intro .role-card-rule {margin:4px 0 10px}
.role-drawer-intro .role-card-description {margin-bottom:10px}
.role-drawer-intro .role-card-bottomline {margin-top:11px}
.role-drawer-intro .role-drawer-teammates:not(:empty) {margin-top:12px}
@keyframes role-intro-in {from {opacity:0;transform:translateY(16px) scale(.96)}to {opacity:1;transform:translateY(0) scale(1)}}
@keyframes role-intro-out {from {opacity:1;transform:scale(1)}to {opacity:0;transform:scale(.96)}}
@media(max-width:760px) {.role-drawer-intro .role-card-art img {height:125px}.role-drawer-intro .role-drawer-content {padding:13px 15px}.role-drawer-intro .role-card-body {padding-left:12px;padding-right:12px}}
@media(prefers-reduced-motion:reduce) {.role-drawer.role-drawer-intro,.role-drawer.role-drawer-intro.is-closing {animation:none}}
</style>

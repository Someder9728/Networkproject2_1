function initDashboard() {
    if (!document.body.dataset.gameId) return;
    const content = document.querySelector('.main-panel .content');
    const zones = Array.from(content.querySelectorAll('[data-dash-zone]'));
    const board = document.createElement('div');
    board.className = 'dashboard-board';
    board.dataset.mobileView = 'players';
    board.innerHTML = `<section class="dashboard-roster" aria-label="ผู้เล่นและการโหวต"></section><section class="dashboard-right"><nav class="dashboard-tabs" role="tablist" aria-label="ข้อมูลเกม"><button type="button" class="dashboard-tab dashboard-mobile-players" data-tab="players" role="tab" aria-controls="dashboard-players">ผู้เล่น</button>${[['actions','เล่น / แก้ต่าง'],['role','Role ของคุณ'],['results','ผล / หลักฐาน'],['rules','กติกา']].map(([id,label]) => `<button type="button" class="dashboard-tab" data-tab="${id}" id="tab-${id}" role="tab" aria-controls="dashboard-${id}">${label}</button>`).join('')}</nav>${['actions','role','results','rules'].map(id => `<section class="dashboard-pane" id="dashboard-${id}" role="tabpanel" aria-labelledby="tab-${id}" tabindex="0" hidden></section>`).join('')}</section>`;
    const roster = board.querySelector('.dashboard-roster');
    roster.id = 'dashboard-players';
    for (const zone of zones) {
        const pane = zone.dataset.dashZone === 'players' ? roster : board.querySelector(`#dashboard-${zone.dataset.dashZone}`);
        pane?.appendChild(zone);
    }
    const phase = content.querySelector('.phase-banner');
    phase.after(board);
    const overview = document.createElement('div');
    overview.className = 'dashboard-overview';
    const roomCode = document.querySelector('meta[name="room-code"]')?.content ?? '';
    overview.textContent = `ห้อง ${roomCode}`;
    phase.querySelector('.phase-row').appendChild(overview);

    const matchTimer = board.querySelector('#match-timer');
    if (matchTimer) phase.querySelector('.phase-row').appendChild(matchTimer);
    for (const pane of board.querySelectorAll('.dashboard-pane')) {
        if (!pane.textContent.trim()) {
            const empty = document.createElement('p'); empty.className = 'dashboard-empty';
            empty.textContent = pane.id === 'dashboard-actions' ? 'ช่วงนี้ไม่มี Action ที่ต้องทำ ดูรายชื่อหรือพูดคุยผ่านแชทได้' : 'ยังไม่มีข้อมูลในช่วงนี้';
            pane.appendChild(empty);
        }
    }
    function activate(id) {
        board.dataset.mobileView = id;
        board.querySelectorAll('[data-tab]').forEach(button => { const selected = button.dataset.tab === id; button.setAttribute('aria-selected', String(selected)); button.tabIndex = selected ? 0 : -1; });
        board.querySelectorAll('.dashboard-pane').forEach(pane => { pane.hidden = pane.id !== `dashboard-${id}`; });
    }
    const phaseKey = `${document.body.dataset.gameRound}:${document.body.dataset.gamePhase}:${document.body.dataset.gameStage}`;
    const key = `dashboard-tab:${document.body.dataset.gameId}:${phaseKey}`;
    let initial = 'actions';
    try { initial = sessionStorage.getItem(key) || 'actions'; } catch {}
    if (!['actions','role','results','rules','players'].includes(initial)) initial = 'actions';
    if (initial === 'players' && window.innerWidth > 760) initial = 'actions';
    board.querySelectorAll('[data-tab]').forEach(button => {
        button.addEventListener('click', () => { activate(button.dataset.tab); try { sessionStorage.setItem(key, button.dataset.tab); } catch {} });
        button.addEventListener('keydown', event => {
            if (!['ArrowLeft','ArrowRight','Home','End'].includes(event.key)) return;
            const buttons = Array.from(board.querySelectorAll('[data-tab]')).filter(b => getComputedStyle(b).display !== 'none');
            let index = buttons.indexOf(button);
            index = event.key === 'Home' ? 0 : event.key === 'End' ? buttons.length - 1 : (index + (event.key === 'ArrowRight' ? 1 : -1) + buttons.length) % buttons.length;
            event.preventDefault(); buttons[index].click(); buttons[index].focus();
        });
    });
    activate(initial);
    window.addEventListener('resize', () => { if (window.innerWidth > 760 && board.dataset.mobileView === 'players') activate('actions'); });
    document.body.classList.add('game-dashboard');
}
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initDashboard);
else initDashboard();

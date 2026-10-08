function initGameNotifications() {
    const body = document.body;
    if (!body.dataset.gameId) return;
    const panel = document.getElementById('chat-panel');
    const toggle = document.getElementById('chat-toggle');
    const badge = document.getElementById('chat-unread');
    const sound = document.getElementById('sound-toggle');
    const toast = document.getElementById('game-notice');
    const scope = `werewolf-ui:${body.dataset.gameId}:`;
    const read = (key, fallback) => { try { return JSON.parse(sessionStorage.getItem(scope + key)) ?? fallback; } catch { return fallback; } };
    const write = (key, value) => { try { sessionStorage.setItem(scope + key, JSON.stringify(value)); } catch {} };
    let opened = read('chat-open', false);
    let active = 'all';
    let unread = read('unread', {});
    let soundOn = read('sound', false);
    let context;
    let queuedVoice = null;
    let lastMessageChannel = null;
    let hideToast;
    function notice(message, channel = null) {
        toast.textContent = message;
        toast.hidden = false;
        lastMessageChannel = channel;
        clearTimeout(hideToast);
        hideToast = setTimeout(() => { toast.hidden = true; }, 6000);
    }
    function beep(message = null) {
        if (!soundOn) return;
        if (!context || context.state !== 'running') { queuedVoice = message; return; }
        const osc = context.createOscillator(), gain = context.createGain();
        osc.frequency.value = message ? 660 : 880;
        gain.gain.setValueAtTime(0.12, context.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.001, context.currentTime + 0.22);
        osc.connect(gain); gain.connect(context.destination); osc.start(); osc.stop(context.currentTime + 0.22);
        if (message && 'speechSynthesis' in window) {
            const speech = new SpeechSynthesisUtterance(message);
            speech.lang = 'th-TH';
            speech.rate = 1;
            window.speechSynthesis.cancel();
            window.speechSynthesis.speak(speech);
        }
    }
    async function unlock() {
        if (!soundOn) return;
        try {
            const Audio = window.AudioContext || window.webkitAudioContext;
            if (!Audio) return;
            context ??= new Audio();
            await context.resume();
            if (queuedVoice) { const text = queuedVoice; queuedVoice = null; beep(text); }
        } catch {}
    }
    document.addEventListener('pointerdown', unlock);
    document.addEventListener('keydown', unlock);
    function render() {
        panel.hidden = !opened;
        window.dispatchEvent(new CustomEvent('game:chat-visibility', {detail: {opened}}));
        toggle.setAttribute('aria-expanded', String(opened));
        if (opened && !document.hidden) unread[active] = 0;
        const count = Object.values(unread).reduce((sum, n) => sum + n, 0);
        badge.textContent = count ? `(${count})` : '';
        toggle.classList.toggle('unread', count > 0);
        document.querySelectorAll('[data-chat-channel]').forEach(button => button.classList.toggle('unread', (unread[button.dataset.chatChannel] ?? 0) > 0));
        write('unread', unread); write('chat-open', opened);
        sound.textContent = soundOn ? 'ปิดเสียงแจ้งเตือน' : 'เปิดเสียงแจ้งเตือน';
        sound.setAttribute('aria-pressed', String(soundOn));
    }
    toggle.addEventListener('click', () => { opened = !opened; render(); });
    sound.addEventListener('click', async () => {
        soundOn = !soundOn; write('sound', soundOn); render();
        if (soundOn) { await unlock(); beep('เปิดเสียงแจ้งเตือนแล้ว'); }
        else if ('speechSynthesis' in window) window.speechSynthesis.cancel();
    });
    toast.addEventListener('click', () => {
        if (lastMessageChannel) {
            opened = true;
            const button = Array.from(document.querySelectorAll('[data-chat-channel]')).find(b => b.dataset.chatChannel === lastMessageChannel);
            button?.click(); render();
        }
        toast.hidden = true;
    });
    window.addEventListener('game:chat-channel', event => { active = event.detail.channel; render(); });
    window.addEventListener('game:chat-update', event => {
        active = event.detail.currentChannel;
        const highs = read('chat-highs', {});
        let newest;
        for (const channel of event.detail.channels) {
            if (highs[channel.name] !== undefined) {
                const incoming = channel.messages.filter(m => Number(m.id) > highs[channel.name] && !m.is_mine);
                if (incoming.length) {
                    if (!opened || active !== channel.name || document.hidden) unread[channel.name] = (unread[channel.name] ?? 0) + incoming.length;
                    const candidate = incoming.at(-1);
                    if (!newest || candidate.id > newest.message.id) newest = {message: candidate, channel: channel.name};
                }
            }
            highs[channel.name] = Math.max(highs[channel.name] ?? 0, ...channel.messages.map(m => Number(m.id)));
        }
        // Channels come only from the authorized chat endpoint; removed channels lose their badges.
        unread = Object.fromEntries(event.detail.channels.map(c => [c.name, unread[c.name] ?? 0]));
        write('chat-highs', highs); render();
        if (newest) { notice(`${newest.message.sender_name}: ${newest.message.content}`, newest.channel); beep(); }
    });
    document.addEventListener('visibilitychange', render);
    const labels = {day_discussion: 'เช้า ช่วงพูดคุย', day_voting: 'กลางวัน ช่วงลงคะแนน', night: 'กลางคืน', game_over: 'จบเกม'};
    const stageLabels = {defense: 'ช่วงแก้ต่าง', verdict: 'ช่วงโหวตยืนยัน'};
    const phaseLabel = stageLabels[body.dataset.gameStage] && body.dataset.gamePhase === 'day_voting' ? stageLabels[body.dataset.gameStage] : (labels[body.dataset.gamePhase] ?? 'เตรียมเริ่มเกม');
    const phaseKey = `${body.dataset.gameRound}:${body.dataset.gamePhase}:${body.dataset.gameStage}`;
    const previous = read('phase', null);
    write('phase', phaseKey);
    if (previous && previous !== phaseKey) { notice(`ตอนนี้เป็น${phaseLabel}`); beep(`ตอนนี้เป็น${phaseLabel}`); }
    const timer = document.getElementById('trial-timer');
    if (timer) {
        const server = Date.parse(timer.dataset.serverTime), loaded = performance.now(), end = Date.parse(timer.dataset.deadline);
        const tick = () => { timer.textContent = `${Math.max(0, Math.ceil((end - server - performance.now() + loaded) / 1000))} วินาที`; };
        tick(); setInterval(tick, 250);
        if (body.dataset.gameStage === 'defense' && document.getElementById('defense-message')) {
            notice('ถึงตาคุณแก้ต่าง! พิมพ์และส่งภายในเวลาที่กำหนด'); beep('ถึงตาคุณแก้ต่าง กรุณาพิมพ์และส่งก่อนหมดเวลา');
            const draft = document.getElementById('defense-message');
            const draftKey = `defense-draft:${body.dataset.gameRound}:${timer.dataset.deadline}`;
            if (!draft.value) draft.value = read(draftKey, '');
            draft.addEventListener('input', () => write(draftKey, draft.value));
            draft.focus();
        }
    }
    render();
    if (soundOn) unlock();
}
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initGameNotifications);
else initGameNotifications();

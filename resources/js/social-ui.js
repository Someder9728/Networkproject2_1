function initSocialUI() {
    const picker = document.getElementById('avatar-picker');
    document.querySelector('[data-open-avatar]')?.addEventListener('click', () => picker.showModal());
    document.querySelectorAll('[data-close-avatar]').forEach(button => button.addEventListener('click', () => picker.close()));
    document.getElementById('avatar-form')?.addEventListener('change', event => {
        const {name, value} = event.target;
        const preview = picker.querySelector('.avatar-preview svg');
        if (name === 'color') {
            const color = {lavender:'#ddd6fe',mint:'#a7f3d0',peach:'#fed7aa',sky:'#bae6fd'}[value];
            if (color) preview.querySelector('[data-avatar-bg]').setAttribute('fill', color);
        } else if (['character','face','accessory'].includes(name)) {
            const use = preview.querySelector(`[data-avatar-${name}]`);
            const base = use.getAttribute('href').split('#')[0];
            use.setAttribute('href', `${base}#${value}`);
        }
    });
    document.querySelectorAll('[data-seat-open]').forEach(button => button.addEventListener('click', () => document.getElementById(button.dataset.seatOpen)?.showModal()));
    document.querySelectorAll('[data-close-seat]').forEach(button => button.addEventListener('click', () => button.closest('dialog').close()));
    document.querySelectorAll('[data-open-chat]').forEach(button => button.addEventListener('click', () => openChat()));
    document.querySelectorAll('[data-show-table]').forEach(button => button.addEventListener('click', () => document.querySelector('[data-tab="players"]')?.click()));
    document.querySelectorAll('[data-whisper-to]').forEach(button => button.addEventListener('click', () => {
        button.closest('dialog')?.close();
        openChat();
        window.dispatchEvent(new CustomEvent('game:whisper-select', {detail:{uuid:button.dataset.whisperTo}}));
    }));
    function openChat() {
        const toggle = document.getElementById('chat-toggle');
        if (toggle?.getAttribute('aria-expanded') !== 'true') toggle?.click();
        document.getElementById('chat-message')?.focus();
    }
}
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initSocialUI);
else initSocialUI();

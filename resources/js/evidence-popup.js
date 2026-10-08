function initEvidencePopup() {
    const panel = document.getElementById('evidence-panel');
    const button = document.getElementById('evidence-toggle');
    const chat = document.getElementById('chat-toggle');
    if (!panel || !button) return;
    document.body.appendChild(panel);
    panel.hidden = true;
    let opened = false;
    function setOpen(value) {
        opened = value;
        panel.hidden = !opened;
        button.setAttribute('aria-expanded', String(opened));
        button.classList.toggle('active', opened);
    }
    button.addEventListener('click', () => {
        const next = !opened;
        if (next && chat?.getAttribute('aria-expanded') === 'true') chat.click();
        setOpen(next);
    });
    window.addEventListener('game:chat-visibility', event => { if (event.detail.opened) setOpen(false); });
    document.addEventListener('keydown', event => { if (event.key === 'Escape' && opened) { setOpen(false); button.focus(); } });
}
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initEvidencePopup);
else initEvidencePopup();

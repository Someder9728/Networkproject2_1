function initRoleDrawer() {
    const drawer = document.getElementById('role-drawer');
    const toggle = document.getElementById('role-drawer-toggle');
    const close = document.getElementById('role-drawer-close');
    if (!drawer || !toggle || !close) return;
    const confirm = document.getElementById('role-intro-confirm');
    const footer = drawer.querySelector('.role-intro-footer');
    const introKey = `werewolf-role-intro:${drawer.dataset.introKey}`;
    let intro = false;
    let closing = false;
    let previousFocus;

    function finishClose() {
        if (intro) {
            try { sessionStorage.setItem(introKey, '1'); } catch {}
        }
        drawer.close();
        drawer.classList.remove('role-drawer-intro');
        footer.hidden = true;
        intro = false;
        drawer.classList.remove('is-closing');
        toggle.setAttribute('aria-expanded', 'false');
        closing = false;
        if (previousFocus?.isConnected) previousFocus.focus();
        else toggle.focus();
    }

    function hide() {
        if (!drawer.open || closing) return;
        closing = true;
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            finishClose();
            return;
        }
        drawer.classList.add('is-closing');
        setTimeout(finishClose, 220);
    }

    toggle.addEventListener('click', () => {
        if (drawer.open) { hide(); return; }
        previousFocus = document.activeElement;
        drawer.classList.remove('is-closing');
        drawer.showModal();
        toggle.setAttribute('aria-expanded', 'true');
        close.focus();
    });
    close.addEventListener('click', hide);
    confirm.addEventListener('click', hide);
    drawer.addEventListener('cancel', event => { event.preventDefault(); hide(); });
    drawer.addEventListener('click', event => {
        if (event.target !== drawer) return;
        const bounds = drawer.getBoundingClientRect();
        if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom) hide();
    });
    let seen = false;
    try { seen = sessionStorage.getItem(introKey) === '1'; } catch {}
    if (!seen && drawer.dataset.introEnabled === 'true') {
        previousFocus = document.activeElement;
        intro = true;
        footer.hidden = false;
        drawer.classList.add('role-drawer-intro');
        drawer.showModal();
        toggle.setAttribute('aria-expanded', 'true');
        confirm.focus();
    }
}
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initRoleDrawer);
else initRoleDrawer();

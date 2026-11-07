document.addEventListener('DOMContentLoaded', () => {
    const body = document.body;

    // Mobile sidebar toggle
    const menuOpen = () => body.classList.add('menu-open');
    const menuClose = () => body.classList.remove('menu-open');
    document.querySelectorAll('[data-open-menu]').forEach(btn => btn.addEventListener('click', menuOpen));
    document.querySelectorAll('[data-close-menu]').forEach(el => el.addEventListener('click', menuClose));
    document.addEventListener('keydown', event => { if (event.key === 'Escape') menuClose(); });

    // User profile dropdown toggle
    const profileBtn = document.getElementById('user-profile-btn');
    const profilePanel = document.getElementById('user-profile-panel');
    if (profileBtn && profilePanel) {
        profileBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            profilePanel.classList.toggle('open');
        });
        document.addEventListener('click', (e) => {
            if (!profilePanel.contains(e.target) && e.target !== profileBtn) {
                profilePanel.classList.remove('open');
            }
        });
    }

    // Password visibility toggle (Screen 1)
    document.querySelectorAll('[data-toggle-password]').forEach(btn => {
        btn.addEventListener('click', () => {
            const input = document.getElementById(btn.dataset.togglePassword);
            if (input) {
                if (input.type === 'password') {
                    input.type = 'text';
                    btn.textContent = 'Hide';
                } else {
                    input.type = 'password';
                    btn.textContent = 'Show';
                }
            }
        });
    });

    // Native dialog show/close
    document.querySelectorAll('[data-show-dialog]').forEach(btn => btn.addEventListener('click', () => {
        const modal = document.getElementById(btn.dataset.showDialog);
        if (modal && typeof modal.showModal === 'function') modal.showModal();
    }));
    document.querySelectorAll('[data-close-dialog]').forEach(btn => btn.addEventListener('click', () => {
        btn.closest('dialog')?.close();
    }));
    document.querySelectorAll('dialog').forEach(dialog => dialog.addEventListener('click', event => {
        if (event.target === dialog) dialog.close();
    }));

    // Project creation template mode toggle
    document.querySelectorAll('[data-mode]').forEach(input => input.addEventListener('change', () => {
        const field = document.getElementById('template-select');
        if (field) field.hidden = document.querySelector('[data-mode]:checked')?.value !== 'template';
    }));

    // Copy to clipboard
    document.querySelectorAll('[data-copy]').forEach(btn => btn.addEventListener('click', async () => {
        const source = document.getElementById(btn.dataset.copy);
        if (!source) return;
        try {
            await navigator.clipboard.writeText(source.textContent.trim());
            btn.textContent = 'Copied';
        } catch {
            btn.textContent = 'Select & copy';
        }
    }));

    // Progress slider
    document.querySelectorAll('[data-progress-slider]').forEach(input => input.addEventListener('input', () => {
        const value = document.getElementById('progress-value');
        if (value) value.textContent = input.value + '%';
    }));

    // Auto submit selects (e.g. status inline update)
    document.querySelectorAll('[data-auto-submit]').forEach(select => select.addEventListener('change', () => {
        if (select.value) select.form?.requestSubmit();
    }));

    // Team picker dynamic row
    let teamIndex = 1;
    document.querySelectorAll('[data-add-team-row]').forEach(btn => btn.addEventListener('click', () => {
        const target = document.getElementById('project-team-rows');
        const template = document.getElementById('team-row-template');
        if (!target || !template || teamIndex >= 30) return;
        const html = template.innerHTML.replaceAll('INDEX', String(teamIndex++));
        target.insertAdjacentHTML('beforeend', html);
    }));
    document.addEventListener('click', event => {
        const btn = event.target.closest('[data-remove-team-row]');
        if (btn) btn.closest('.team-picker-row')?.remove();
    });
});

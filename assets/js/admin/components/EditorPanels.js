const PANELS = { files: '(max-width: 1023px)', aside: '(max-width: 1279px)' };

export default function EditorPanels(element) {
    const root = element.closest('.studio-editor');
    const body = root.querySelector('.studio-editor__body');
    const buttons = [...element.querySelectorAll('[data-panel]')];

    const open = (panel) => {
        buttons.forEach((button) => {
            const active = button.dataset.panel === panel;
            root.classList.toggle(`studio-editor--${button.dataset.panel}`, active);
            button.setAttribute('aria-expanded', String(active));
        });
    };

    buttons.forEach((button) => button.addEventListener('click', () => {
        open(root.classList.contains(`studio-editor--${button.dataset.panel}`) ? null : button.dataset.panel);
    }));

    body.addEventListener('click', (event) => {
        if (event.target === body) {
            open(null);
        }
    });

    root.querySelector('.studio-editor__tree')?.addEventListener('click', (event) => {
        if (event.target.closest('.studio-editor__file') && window.matchMedia(PANELS.files).matches) {
            open(null);
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            open(null);
        }
    });

    Object.values(PANELS).forEach((query) => window.matchMedia(query).addEventListener('change', () => open(null)));
}

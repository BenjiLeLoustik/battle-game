export default function AdminNav(element) {
    const backdrop = document.querySelector('[data-admin-nav-backdrop]');
    const openers = [...document.querySelectorAll('[data-admin-nav-open]')];

    const open = (state) => {
        element.classList.toggle('admin-layout__aside--open', state);
        backdrop?.toggleAttribute('hidden', !state);
        openers.forEach((opener) => opener.setAttribute('aria-expanded', String(state)));
    };

    openers.forEach((opener) => opener.addEventListener('click', () => open(!element.classList.contains('admin-layout__aside--open'))));
    backdrop?.addEventListener('click', () => open(false));
    element.addEventListener('click', (event) => {
        if (event.target.closest('a')) {
            open(false);
        }
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            open(false);
        }
    });
}

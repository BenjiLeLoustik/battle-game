const DESKTOP = window.matchMedia('(min-width: 1024px)');

export default function Sidebar(element, data) {
    const toggle = element.querySelector('.site-sidebar__toggle');
    const backdrop = document.querySelector('[data-sidebar-backdrop]');
    const openers = [...document.querySelectorAll('[data-sidebar-open]')];
    let collapsed = false;

    try {
        collapsed = localStorage.getItem(data.storageKey) === '1';
    } catch {}

    const label = (text) => {
        toggle.setAttribute('aria-label', text);
        toggle.title = text;
    };

    const apply = () => {
        const desktop = DESKTOP.matches;
        element.classList.toggle('site-sidebar--collapsed', desktop && collapsed);
        toggle.setAttribute('aria-expanded', String(desktop ? !collapsed : element.classList.contains('site-sidebar--open')));
        label(desktop ? (collapsed ? data.labelExpand : data.labelCollapse) : data.labelClose);
    };

    const open = (state) => {
        element.classList.toggle('site-sidebar--open', state);
        backdrop?.toggleAttribute('hidden', !state);
        document.body.classList.toggle('site-layout--locked', state);
        openers.forEach((opener) => opener.setAttribute('aria-expanded', String(state)));
        apply();
    };

    toggle.addEventListener('click', () => {
        if (!DESKTOP.matches) {
            open(false);

            return;
        }

        collapsed = !collapsed;
        apply();

        try {
            localStorage.setItem(data.storageKey, collapsed ? '1' : '0');
        } catch {}
    });

    openers.forEach((opener) => opener.addEventListener('click', () => open(!element.classList.contains('site-sidebar--open'))));
    backdrop?.addEventListener('click', () => open(false));
    element.addEventListener('click', (event) => {
        if (!DESKTOP.matches && event.target.closest('a')) {
            open(false);
        }
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && element.classList.contains('site-sidebar--open')) {
            open(false);
        }
    });
    DESKTOP.addEventListener('change', () => open(false));

    apply();
}

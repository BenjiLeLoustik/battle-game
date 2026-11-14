export default function Tabs(element) {
    const tabs = [...element.querySelectorAll('[role="tab"]')];

    const select = (current) => tabs.forEach((tab) => {
        const active = tab === current;
        tab.setAttribute('aria-selected', String(active));
        tab.classList.toggle('admin-tabs__tab--active', active);
        document.getElementById(tab.getAttribute('aria-controls')).hidden = !active;
    });

    tabs.forEach((tab) => tab.addEventListener('click', () => select(tab)));
}
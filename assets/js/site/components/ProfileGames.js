export default function ProfileGames(element) {
    const tabs = [...element.querySelectorAll('[role="tab"]')];

    tabs.forEach((tab) => {
        tab.addEventListener('click', () => {
            tabs.forEach((other) => {
                const selected = other === tab;
                other.setAttribute('aria-selected', String(selected));
                document.getElementById(other.getAttribute('aria-controls')).hidden = !selected;
            });
        });
    });
}

export default function Toggle(element, data) {
    const target = document.querySelector(data.target);
    let closed = false;

    const apply = () => {
        target.classList.toggle(data.class, closed);
        element.setAttribute('aria-expanded', String(!closed));
    };

    try {
        closed = localStorage.getItem(data.storageKey) === '1';
    } catch {}

    apply();

    element.addEventListener('click', () => {
        closed = !closed;
        apply();

        try {
            localStorage.setItem(data.storageKey, closed ? '1' : '0');
        } catch {}
    });
}
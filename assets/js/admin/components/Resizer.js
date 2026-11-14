export default function Resizer(element, data) {
    const target = document.querySelector(data.target);
    const min = Number(data.min);
    const max = () => Math.min(Number(data.max), window.innerWidth * 0.6);

    const apply = (width) => {
        const value = Math.round(Math.max(min, Math.min(max(), width)));
        target.style.width = `${value}px`;
        element.setAttribute('aria-valuenow', String(value));

        return value;
    };

    const store = (value) => {
        try {
            localStorage.setItem(data.storageKey, String(Math.round(value)));
        } catch {}
    };

    try {
        const saved = Number(localStorage.getItem(data.storageKey));

        if (saved > 0) {
            apply(saved);
        }
    } catch {}

    element.addEventListener('pointerdown', (event) => {
        event.preventDefault();
        element.setPointerCapture(event.pointerId);
        target.classList.add(data.activeClass);

        const right = target.getBoundingClientRect().right;
        const move = (moveEvent) => apply(right - moveEvent.clientX);

        element.addEventListener('pointermove', move);
        element.addEventListener('pointerup', () => {
            element.removeEventListener('pointermove', move);
            target.classList.remove(data.activeClass);
            store(target.getBoundingClientRect().width);
        }, { once: true });
    });

    element.addEventListener('keydown', (event) => {
        const step = { ArrowLeft: 20, ArrowRight: -20 }[event.key];

        if (step) {
            event.preventDefault();
            store(apply(target.getBoundingClientRect().width + step));
        }
    });
}
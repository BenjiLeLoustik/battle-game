export default function CheckAll(element) {
    const all = element.querySelector('[data-all]');
    const boxes = [...element.querySelectorAll('input[type="checkbox"]:not([data-all])')];

    const count = element.querySelector('[data-count]');

    const sync = () => {
        const checked = boxes.filter((box) => box.checked).length;
        all.checked = checked === boxes.length;

        if (count) {
            count.textContent = `${checked} / ${boxes.length}`;
            count.classList.toggle('admin-pill--ok', checked === boxes.length);
            count.classList.toggle('admin-pill--accent', checked > 0 && checked < boxes.length);
        }
    };

    all.addEventListener('change', () => {
        boxes.forEach((box) => {
            box.checked = all.checked;
        });
        sync();
    });

    boxes.forEach((box) => box.addEventListener('change', sync));
    sync();
}

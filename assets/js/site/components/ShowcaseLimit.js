export default function ShowcaseLimit(form, data) {
    const boxes = [...form.querySelectorAll('input[type="checkbox"]')];
    const counter = form.querySelector('[data-showcase-count]');
    const max = Number(data.max ?? 3);

    const sync = () => {
        const count = boxes.filter((box) => box.checked).length;
        boxes.forEach((box) => {
            box.disabled = count >= max && !box.checked;
        });

        if (counter) {
            counter.textContent = String(count);
        }
    };

    boxes.forEach((box) => box.addEventListener('change', sync));
    sync();
}

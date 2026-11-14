export default function ClanForm(form) {
    const preview = form.querySelector('[data-preview]');
    const name = form.querySelector('[data-input-name]');
    const tag = form.querySelector('[data-input-tag]');
    const radio = form.querySelector('[data-custom-radio]');
    const picker = form.querySelector('[data-custom-color]');

    const paint = (color) => {
        preview?.style.setProperty('--clan-color', color);
        preview?.querySelectorAll('[style*="--clan-color"]').forEach((item) => item.style.setProperty('--clan-color', color));
    };

    form.querySelectorAll('.clan-colors__input').forEach((input) => input.addEventListener('change', () => paint(input.value)));

    picker?.addEventListener('input', () => {
        const color = picker.value.toUpperCase();
        radio.value = color;
        radio.checked = true;
        radio.closest('.clan-colors__item').style.setProperty('--swatch', color);
        paint(color);
    });

    picker?.addEventListener('click', () => {
        radio.checked = true;
    });

    name?.addEventListener('input', () => {
        const target = preview?.querySelector('[data-preview-name]');

        if (target) {
            target.textContent = name.value.trim() || target.dataset.fallback;
        }
    });

    tag?.addEventListener('input', () => {
        tag.value = tag.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 5);
        const value = tag.value || 'TAG';
        preview?.querySelectorAll('.clan-emblem__tag').forEach((item) => {
            item.textContent = value;
        });
        const label = preview?.querySelector('[data-preview-tag]');

        if (label) {
            label.textContent = `[${value}]`;
        }
    });
}

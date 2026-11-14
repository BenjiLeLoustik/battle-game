import badge from 'shared/badge';

const value = (form, name) => form.elements[`design[${name}]`];

export default function BadgeEditor(element, data) {
    const form = element.closest('form');
    const icons = JSON.parse(data.icons);
    const previews = [...form.querySelectorAll('[data-badge-preview]')];

    const render = () => {
        const rarity = value(form, 'rarity').value;
        const frame = value(form, 'frame').value;
        const view = {
            name: form.elements.name.value,
            path: icons[value(form, 'icon').value],
            design: {
                shape: value(form, 'shape').value,
                rarity,
                icon: value(form, 'icon').value,
                color: value(form, 'color_enabled').checked ? value(form, 'color').value : null,
                glow: Number(value(form, 'glow').value),
                label: value(form, 'label').value,
                double: frame === 'double' || (frame === 'auto' && ['epic', 'legendary'].includes(rarity)),
            },
        };

        previews.forEach((preview) => preview.replaceChildren(badge(view, Number(preview.dataset.badgePreview))));
    };

    form.addEventListener('input', render);
    form.addEventListener('change', render);
    render();
}

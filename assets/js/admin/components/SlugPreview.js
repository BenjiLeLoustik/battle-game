const slugify = (value) => value
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-|-$/g, '');

export default function SlugPreview(element, data) {
    const target = document.querySelector(data.target);
    const update = () => {
        target.textContent = slugify(element.value) || data.placeholder;
    };

    element.addEventListener('input', update);
    update();
}
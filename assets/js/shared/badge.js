const SVG = 'http://www.w3.org/2000/svg';

export default function badge(view, size) {
    const design = view.design;
    const element = document.createElement('span');
    const icon = document.createElementNS(SVG, 'svg');
    const path = document.createElementNS(SVG, 'path');
    const labelled = Boolean(design.label) && size >= 56;

    element.className = `badge-shape badge-shape--${design.shape} badge-shape--${design.rarity}` + (design.double && size >= 40 ? ' badge-shape--double' : '');
    element.style.setProperty('--badge-size', `${size}px`);
    element.style.setProperty('--badge-glow', String(design.glow));
    element.setAttribute('role', 'img');
    element.setAttribute('aria-label', view.name);

    if (design.color) {
        element.style.setProperty('--badge-color', design.color);
    }

    icon.setAttribute('class', 'badge-shape__icon' + (labelled ? ' badge-shape__icon--raised' : ''));
    icon.setAttribute('viewBox', '0 0 24 24');
    icon.setAttribute('fill', 'none');
    icon.setAttribute('stroke', 'currentColor');
    icon.setAttribute('stroke-width', '1.9');
    icon.setAttribute('stroke-linecap', 'round');
    icon.setAttribute('stroke-linejoin', 'round');
    icon.setAttribute('aria-hidden', 'true');
    path.setAttribute('d', view.path);
    icon.append(path);

    ['frame', 'inner'].forEach((part) => {
        const layer = document.createElement('span');
        layer.className = `badge-shape__${part}`;
        element.append(layer);
    });

    element.append(icon);

    if (labelled) {
        const label = document.createElement('span');
        label.className = 'badge-shape__label';
        label.textContent = design.label;
        element.append(label);
    }

    return element;
}

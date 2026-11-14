const ALLOWED = ['path', 'circle', 'rect', 'line', 'polyline', 'polygon', 'ellipse'];

const clean = (text) => {
    const doc = new DOMParser().parseFromString(text, 'image/svg+xml');
    const svg = doc.querySelector('svg');

    if (!svg || doc.querySelector('parsererror')) {
        return '';
    }

    return [...svg.children].filter((child) => ALLOWED.includes(child.tagName)).map((child) => {
        const attributes = [...child.attributes].filter((attribute) => !['class', 'style'].includes(attribute.name) && !attribute.name.startsWith('on')).map((attribute) => `${attribute.name}="${attribute.value}"`).join(' ');

        return `<${child.tagName} ${attributes}/>`;
    }).join('');
};

export default function LucidePicker(element, data) {
    const form = element.closest('form');
    const name = element.querySelector('[data-lucide-name]');
    const hidden = element.querySelector('[data-lucide-svg]');
    const preview = element.querySelector('[data-lucide-preview]');
    const status = element.querySelector('[data-lucide-status]');
    const cache = new Map();
    let timer = null;
    let token = 0;

    const draw = (markup) => {
        const icon = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${markup}</svg>`;
        preview.innerHTML = icon;
        form.querySelectorAll('.shop-badge-editor .plan-badge').forEach((badge) => {
            badge.innerHTML = icon.replace('stroke-width="2"', 'stroke-width="2.4"').replace('<svg ', '<svg class="plan-icon plan-icon--line" ');
        });
    };

    const say = (state) => {
        status.textContent = state ? status.dataset[state] : '';
        status.dataset.state = state;
    };

    const load = async () => {
        const value = name.value.trim().toLowerCase();
        const current = token += 1;

        if (!/^[a-z0-9-]{1,60}$/.test(value)) {
            say('missing');

            return;
        }

        say('loading');

        try {
            if (!cache.has(value)) {
                const response = await fetch(`${data.cdn}${value}.svg`);

                if (!response.ok) {
                    throw new Error('missing');
                }

                cache.set(value, clean(await response.text()));
            }

            const markup = cache.get(value);

            if (current !== token) {
                return;
            }

            if (!markup) {
                throw new Error('missing');
            }

            hidden.value = markup;
            draw(markup);
            say('ok');
        } catch {
            if (current === token) {
                say('missing');
            }
        }
    };

    name.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(load, 350);
    });

    element.querySelectorAll('[data-lucide-pick]').forEach((chip) => chip.addEventListener('click', () => {
        name.value = chip.dataset.lucidePick;
        load();
    }));

    if (!hidden.value && name.value) {
        load();
    }
}

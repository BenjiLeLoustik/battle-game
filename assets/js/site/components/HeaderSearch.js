import dom from 'shared/dom';

const { node, avatar } = dom;

const MIN_LENGTH = 2;
const DELAY = 180;

export default function HeaderSearch(element, data) {
    const labels = JSON.parse(data.labels);
    const input = element.querySelector('input[name=q]');
    const panel = element.querySelector('[role=listbox]');
    let timer = null;
    let controller = null;
    let active = -1;

    const links = () => [...panel.querySelectorAll('.search-result')];

    const toggle = (open) => {
        panel.hidden = !open;
        input.setAttribute('aria-expanded', String(open));
    };

    const highlight = (index) => {
        const items = links();
        active = items.length === 0 ? -1 : (index + items.length) % items.length;
        items.forEach((item, position) => {
            item.classList.toggle('search-result--active', position === active);
            item.setAttribute('aria-selected', String(position === active));
        });
        items[active]?.scrollIntoView({ block: 'nearest' });
    };

    const item = (result, section) => {
        const link = node('a', 'search-result');
        link.href = result.url;
        link.setAttribute('role', 'option');

        if (section === 'players') {
            link.append(avatar(result, ''));
        } else {
            const swatch = node('span', 'search-result__swatch');
            swatch.style.setProperty('--swatch', result.color);
            link.append(swatch);
        }

        const text = node('span', 'search-result__text');
        text.append(node('span', 'search-result__name', result.name), node('span', 'search-result__meta', result.meta));
        link.append(text);

        return link;
    };

    const render = (results) => {
        const children = [];

        ['games', 'players'].forEach((section) => {
            if (results[section].length > 0) {
                children.push(node('span', 'search-suggest__caption', labels[section]));
                results[section].forEach((result) => children.push(item(result, section)));
            }
        });

        if (children.length === 0) {
            children.push(node('p', 'search-suggest__empty', labels.empty));
        }

        const all = node('a', 'search-suggest__all', labels.all + ' →');
        all.href = element.action + '?q=' + encodeURIComponent(input.value.trim());
        children.push(all);

        panel.replaceChildren(...children);
        active = -1;
        toggle(true);
    };

    const search = async () => {
        const query = input.value.trim();
        controller?.abort();

        if (query.length < MIN_LENGTH) {
            toggle(false);

            return;
        }

        controller = new AbortController();

        try {
            const response = await fetch(element.action + '?q=' + encodeURIComponent(query), { headers: { Accept: 'application/json' }, signal: controller.signal });

            if (response.ok && input.value.trim() === query) {
                render(await response.json());
            }
        } catch {}
    };

    input.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(search, DELAY);
    });

    input.addEventListener('focus', () => {
        if (panel.children.length > 0 && input.value.trim().length >= MIN_LENGTH) {
            toggle(true);
        }
    });

    input.addEventListener('keydown', (event) => {
        if (panel.hidden) {
            return;
        }

        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            highlight(active + (event.key === 'ArrowDown' ? 1 : -1));
        } else if (event.key === 'Enter' && active >= 0) {
            event.preventDefault();
            links()[active].click();
        } else if (event.key === 'Escape') {
            toggle(false);
        }
    });

    element.addEventListener('submit', (event) => {
        if (input.value.trim().length < MIN_LENGTH) {
            event.preventDefault();
        }
    });

    document.addEventListener('click', (event) => {
        if (!element.contains(event.target)) {
            toggle(false);
        }
    });
}

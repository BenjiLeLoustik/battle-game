import dom from 'shared/dom';

const { node } = dom;

const SPEED = 70;

const parse = (raw) => {
    try {
        const value = JSON.parse(raw);

        return typeof value === 'object' && value !== null ? value : { title: String(value) };
    } catch {
        return { title: raw };
    }
};

export default function LiveBanner(element, data) {
    const list = element.querySelector('.site-banner__list');
    const track = element.querySelector('[data-track]');
    const items = [...track.children];

    const copy = (item) => {
        const clone = item.cloneNode(true);
        clone.setAttribute('aria-hidden', 'true');
        clone.querySelectorAll('a').forEach((link) => link.setAttribute('tabindex', '-1'));

        return clone;
    };

    const build = () => {
        track.classList.remove('site-banner__track--scrolling');
        track.replaceChildren(...items);

        if (items.length < 2 || list.clientWidth === 0 || track.scrollWidth <= list.clientWidth) {
            return;
        }

        track.append(...items.map(copy));

        const width = track.scrollWidth / 2;
        track.style.setProperty('--duration', `${width / SPEED}s`);
        track.classList.add('site-banner__track--scrolling');
    };

    const source = new EventSource(data.url);

    source.onmessage = (message) => {
        const event = parse(message.data);
        const item = node('span', 'site-banner__item');
        item.append(node('span', 'site-banner__dot'), node('strong', 'site-banner__title', event.title ?? ''));

        if (event.meta) {
            item.append(node('span', 'site-banner__meta', event.meta));
        }

        if (event.url) {
            const link = node('a', 'site-banner__link', `${event.cta ?? data.cta} →`);
            link.href = event.url;
            item.append(link);
        }

        items.unshift(item);
        element.classList.remove('site-banner--hidden');
        build();
    };

    element.querySelector('.site-banner__close')?.addEventListener('click', () => {
        element.classList.add('site-banner--hidden');
    });

    let width = list.clientWidth;

    new ResizeObserver(() => {
        if (list.clientWidth !== width) {
            width = list.clientWidth;
            build();
        }
    }).observe(list);

    build();
}

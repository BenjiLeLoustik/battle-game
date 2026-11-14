import dom from 'shared/dom';

const { node, avatar } = dom;

const PREFETCH_DELAY = 1200;
const SKELETON_ROWS = 3;

const when = (value) => {
    const date = new Date(value);

    return date.toDateString() === new Date().toDateString()
        ? date.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })
        : date.toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit' });
};

export default function NotificationBell(element, data) {
    const button = element.querySelector('.site-header__notify');
    const badge = element.querySelector('.notify__badge');
    const panel = element.querySelector('.notify__panel');
    const list = element.querySelector('.notify__list');
    const storageKey = `battlegame.notifications:${data.url}`;
    let items = null;
    let request = null;

    const remember = () => {
        try {
            sessionStorage.setItem(storageKey, JSON.stringify(items));
        } catch {}
    };

    try {
        items = JSON.parse(sessionStorage.getItem(storageKey) ?? 'null');
    } catch {}

    const setUnread = (count) => {
        badge.hidden = count === 0;
        badge.textContent = String(Math.min(99, count));
    };

    const item = (notification) => {
        const link = node('a', 'notify__item' + (notification.read ? '' : ' notify__item--unread'));
        const text = node('span', 'notify__text');

        link.href = notification.url;
        text.append(node('span', 'notify__message', notification.text));

        if (notification.preview) {
            text.append(node('span', 'notify__preview', notification.preview));
        }

        text.append(node('span', 'notify__date', when(notification.date)));
        link.append(avatar(notification, 'avatar--sm'), text);

        const row = node('li', 'notify__row');
        row.append(link);

        return row;
    };

    const skeleton = () => Array.from({ length: SKELETON_ROWS }, () => {
        const row = node('li', 'notify__skeleton');
        row.append(node('span', 'notify__skeleton-avatar'), node('span', 'notify__skeleton-lines'));

        return row;
    });

    const render = () => {
        if (items === null) {
            list.replaceChildren(...skeleton());

            return;
        }

        list.replaceChildren(...(items.length > 0 ? items.map(item) : [node('li', 'notify__empty', data.empty)]));
    };

    const load = () => {
        request ??= fetch(data.list, { headers: { Accept: 'application/json' } })
            .then((response) => response.json())
            .then((payload) => {
                items = payload.items;
                remember();

                if (!panel.hidden) {
                    render();
                }

                return items;
            })
            .catch(() => items)
            .finally(() => {
                request = null;
            });

        return request;
    };

    const open = async () => {
        panel.hidden = false;
        button.setAttribute('aria-expanded', 'true');
        render();

        const fresh = await load();

        if (fresh?.some((notification) => !notification.read)) {
            dom.post(data.read, data.token);
            setUnread(0);
            items = fresh.map((notification) => ({ ...notification, read: true }));
            remember();
        }
    };

    const close = () => {
        panel.hidden = true;
        button.setAttribute('aria-expanded', 'false');
    };

    button.addEventListener('click', () => (panel.hidden ? open() : close()));
    button.addEventListener('pointerenter', () => items === null && load(), { once: true });
    button.addEventListener('focus', () => items === null && load(), { once: true });

    document.addEventListener('click', (event) => {
        if (!element.contains(event.target)) {
            close();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            close();
        }
    });

    setTimeout(load, PREFETCH_DELAY);

    new EventSource(data.url).onmessage = (event) => {
        const payload = JSON.parse(event.data);

        if (payload.unread !== undefined) {
            setUnread(payload.unread);
        }

        if (payload.type === 'notification') {
            items = [payload.notification, ...(items ?? []).filter((other) => other.id !== payload.notification.id)];
            remember();

            if (!panel.hidden) {
                render();
            }
        }
    };
}

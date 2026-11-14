import dom from 'shared/dom';

const { node } = dom;

const LIMIT = 300;
const WINDOW = 60000;
const TYPING = 240;

export default function MercureFeed(element, data) {
    const labels = JSON.parse(data.labels);
    const feed = element.querySelector('[data-feed]');
    const filter = element.querySelector('[data-filter]');
    const pause = element.querySelector('[data-pause]');
    const state = element.querySelector('[data-state]');
    const top = element.querySelector('[data-top]');
    const form = element.querySelector('[data-publish]');
    const counters = { count: element.querySelector('[data-count]'), rate: element.querySelector('[data-rate]'), topics: element.querySelector('[data-topics]') };
    const cursor = node('li', 'mercure-terminal__line mercure-terminal__line--cursor');
    const topics = {};
    const times = [];
    let paused = false;
    let total = 0;

    const time = () => new Date().toLocaleTimeString('fr-FR');
    const visible = (row) => filter.value === '' || row.dataset.search.includes(filter.value);

    const type = (target, text) => {
        const start = performance.now();
        const duration = Math.min(TYPING, text.length * 8);

        const step = (now) => {
            const ratio = duration === 0 ? 1 : Math.min(1, (now - start) / duration);
            target.textContent = text.slice(0, Math.ceil(text.length * ratio));

            if (ratio < 1) {
                requestAnimationFrame(step);
            }
        };

        requestAnimationFrame(step);
    };

    const print = (parts, modifier = '') => {
        const near = feed.scrollHeight - feed.scrollTop - feed.clientHeight < 40;
        const row = node('li', 'mercure-terminal__line' + (modifier ? ' mercure-terminal__line--' + modifier : ''));
        row.dataset.search = parts.map((part) => part[1]).join(' ');

        parts.forEach(([name, text], index) => {
            const span = node('span', 'mercure-terminal__' + name);
            row.append(span);

            if (index === parts.length - 1) {
                type(span, text);
            } else {
                span.textContent = text;
            }
        });

        row.hidden = !visible(row);
        feed.insertBefore(row, cursor);

        while (feed.children.length > LIMIT) {
            feed.firstElementChild.remove();
        }

        if (near) {
            feed.scrollTop = feed.scrollHeight;
        }
    };

    const system = (text, modifier = 'system') => print([['time', time()], ['text', text]], modifier);

    const refresh = () => {
        const now = Date.now();

        while (times.length > 0 && now - times[0] > WINDOW) {
            times.shift();
        }

        counters.count.textContent = String(total);
        counters.rate.textContent = String(times.length);
        counters.topics.textContent = String(Object.keys(topics).length);
        top.replaceChildren(...Object.entries(topics).sort((a, b) => b[1] - a[1]).slice(0, 8).map(([topic, count]) => {
            const row = node('tr', 'admin-table__row');
            row.append(node('td', 'admin-table__cell admin-table__cell--mono', topic), node('td', 'admin-table__cell', String(count)));

            return row;
        }));
    };

    feed.append(cursor);
    print([['prompt', '$'], ['text', 'mercure subscribe "*"']], 'command');
    system(labels.connecting);

    const source = new EventSource(data.url, { withCredentials: true });

    source.onopen = () => system(labels.connected, 'success');
    source.onerror = () => system(labels.lost, 'error');

    source.onmessage = (event) => {
        let payload = {};

        try {
            payload = JSON.parse(event.data);
        } catch {}

        const topic = payload.type ?? payload.topic ?? 'message';
        total += 1;
        times.push(Date.now());
        topics[topic] = (topics[topic] ?? 0) + 1;

        if (!paused) {
            print([['time', time()], ['topic', topic], ['data', event.data.slice(0, 400)]]);
        }

        refresh();
    };

    pause.addEventListener('click', () => {
        paused = !paused;
        pause.textContent = paused ? labels.resume : labels.pause;
        state.textContent = paused ? labels.paused : labels.live;
    });

    filter.addEventListener('input', () => {
        [...feed.children].forEach((row) => {
            if (row !== cursor) {
                row.hidden = !visible(row);
            }
        });
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const button = form.querySelector('[type=submit]');
        const topic = form.elements.topic.value;
        button.disabled = true;
        print([['prompt', '$'], ['text', 'mercure publish "' + topic + '"']], 'command');

        try {
            const response = await fetch(form.action, { method: 'POST', body: new FormData(form), headers: { Accept: 'application/json' } });
            const ok = response.ok;
            system(ok ? labels.published : labels.failed, ok ? 'success' : 'error');
            window.dispatchEvent(new CustomEvent('admin:flash', { detail: { type: ok ? 'success' : 'error', message: ok ? labels.published : labels.failed } }));
        } catch {
            system(labels.failed, 'error');
        }

        button.disabled = false;
    });

    setInterval(refresh, 5000);
}

import dom from 'shared/dom';

const { node, format, avatar, name } = dom;

const TYPING_DELAY = 3000;
const TYPING_DISPLAY = 4000;
const LIMIT = 200;

const day = (date) => date.toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' });
const time = (date) => date.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });

export default function Chat(element, data) {
    const labels = JSON.parse(data.labels);
    const list = element.querySelector('.chat__messages');
    const typing = element.querySelector('.chat__typing');
    const form = element.querySelector('.chat__form');
    const invite = element.querySelector('[data-invite]');
    let lastDay = null;
    let typedAt = 0;
    let typingTimer = null;
    let readTimer = null;

    const post = (url, body = {}) => dom.post(url, data.token, body);

    const separator = (date) => {
        const label = date.toDateString() === new Date().toDateString() ? labels.today : day(date);
        const line = node('div', 'chat__day');
        line.append(node('span', 'chat__day-line'), node('span', 'chat__day-label', label), node('span', 'chat__day-line'));

        return line;
    };

    const card = (room) => {
        const box = node('div', 'chat__card');
        const text = node('span', 'chat__card-text');
        const link = node('a', 'chat__card-join', labels.join);

        link.href = data.roomUrl.replace('XXX-XXX', room.code);
        text.append(node('strong', 'chat__card-title', format(labels.invite, { name: room.name })), node('span', 'chat__card-meta', format(labels.invite_meta, { code: room.code, max: room.max })));
        box.append(node('span', 'chat__card-icon'), text, link);

        return box;
    };

    const message = (item) => {
        const date = new Date(item.date);
        const row = node('div', 'chat__message' + (item.user === data.player ? ' chat__message--mine' : ''));
        const body = node('div', 'chat__body');
        const head = node('span', 'chat__meta');

        head.append(name(item, 'chat__author'));

        if (item.staff) {
            head.append(node('span', 'chat__staff', labels.staff));
        }

        head.append(node('span', 'chat__time', time(date)));
        body.append(head);

        if (item.text) {
            body.append(node('span', 'chat__text', item.text));
        }

        if (item.room) {
            body.append(card(item.room));
        }

        row.append(avatar(item, 'avatar--base'), body);

        return [date, row];
    };

    const append = (item, scroll = true) => {
        const [date, row] = message(item);
        const stuck = list.scrollHeight - list.scrollTop - list.clientHeight < 80;

        list.querySelector('.chat__empty')?.remove();

        if (lastDay !== date.toDateString()) {
            lastDay = date.toDateString();
            list.append(separator(date));
        }

        list.append(row);

        while (list.children.length > LIMIT) {
            list.firstElementChild.remove();
        }

        if (scroll || stuck) {
            list.scrollTop = list.scrollHeight;
        }
    };

    const markRead = () => {
        if (!data.token) {
            return;
        }

        clearTimeout(readTimer);
        readTimer = setTimeout(() => post(data.read), 800);
    };

    const showTyping = (username) => {
        typing.textContent = format(labels.typing, { name: username });
        clearTimeout(typingTimer);
        typingTimer = setTimeout(() => {
            typing.textContent = '';
        }, TYPING_DISPLAY);
    };

    new EventSource(data.url).onmessage = (event) => {
        const payload = JSON.parse(event.data);
        const mine = payload.key === undefined || payload.key === data.key;

        if ((payload.type === 'message' || payload.type === 'dm') && mine) {
            typing.textContent = '';
            append(payload.message, payload.message.user === data.player);

            if (payload.message.user !== data.player) {
                markRead();
            }
        }

        if (payload.type === 'typing' && mine && payload.user !== data.player) {
            showTyping(payload.username);
        }
    };

    form?.addEventListener('submit', (event) => {
        event.preventDefault();
        const input = form.elements.text;
        const text = input.value.trim();

        if (text !== '') {
            post(data.post, { text });
            input.value = '';
        }
    });

    form?.elements.text.addEventListener('input', () => {
        if (Date.now() - typedAt > TYPING_DELAY) {
            typedAt = Date.now();
            post(data.typing);
        }
    });

    invite?.addEventListener('click', () => post(data.post, { text: form.elements.text.value.trim(), invite: true }).then(() => {
        form.elements.text.value = '';
    }));

    form?.querySelector('.chat__send').removeAttribute('disabled');
    JSON.parse(data.messages).forEach((item) => append(item));
    list.scrollTop = list.scrollHeight;
}

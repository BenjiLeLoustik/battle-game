import dom from 'shared/dom';

const { node, avatar, format } = dom;

const MIN_LENGTH = 2;
const DELAY = 160;

export default function PlayerPicker(element, data) {
    const input = element.querySelector('.player-picker__input');
    const panel = element.querySelector('.player-picker__panel');
    let timer = null;
    let controller = null;
    let active = -1;

    const options = () => [...panel.querySelectorAll('.player-picker__option')];

    const toggle = (open) => {
        panel.hidden = !open;
        input.setAttribute('aria-expanded', String(open));
    };

    const highlight = (index) => {
        const items = options();
        active = items.length === 0 ? -1 : (index + items.length) % items.length;
        items.forEach((item, position) => {
            item.classList.toggle('player-picker__option--active', position === active);
            item.setAttribute('aria-selected', String(position === active));
        });
        items[active]?.scrollIntoView({ block: 'nearest' });
    };

    const choose = (player) => {
        input.value = player.username;
        element.classList.add('player-picker--chosen');
        toggle(false);
        input.dispatchEvent(new Event('change', { bubbles: true }));
    };

    const render = (players) => {
        if (players.length === 0) {
            panel.replaceChildren(node('p', 'player-picker__empty', data.empty));
            toggle(true);

            return;
        }

        panel.replaceChildren(...players.map((player) => {
            const option = node('button', 'player-picker__option');
            const text = node('span', 'player-picker__text');
            option.type = 'button';
            option.setAttribute('role', 'option');
            text.append(
                node('span', 'player-picker__name', player.username),
                node('span', 'player-picker__meta', format(data.coins, { coins: player.coins.toLocaleString('fr-FR') }) + (player.plan ? ' · ' + player.plan : '')),
            );
            option.append(avatar(player, 'avatar--sm'), text);
            option.addEventListener('mousedown', (event) => event.preventDefault());
            option.addEventListener('click', () => choose(player));

            return option;
        }));
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
            const response = await fetch(data.url + '?q=' + encodeURIComponent(query), { headers: { Accept: 'application/json' }, signal: controller.signal });

            if (response.ok && input.value.trim() === query) {
                render(await response.json());
            }
        } catch {}
    };

    input.addEventListener('input', () => {
        element.classList.remove('player-picker--chosen');
        clearTimeout(timer);
        timer = setTimeout(search, DELAY);
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
            options()[active].click();
        } else if (event.key === 'Escape') {
            toggle(false);
        }
    });

    input.addEventListener('blur', () => setTimeout(() => toggle(false), 120));
}

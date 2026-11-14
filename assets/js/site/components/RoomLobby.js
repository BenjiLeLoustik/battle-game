import dom from 'shared/dom';

const { node, format, avatar, name, title } = dom;

const PING_INTERVAL = 15000;
const ICON = 'M13 2 3 14h9l-1 8 10-12h-9l1-8z';

const clock = (ms) => {
    const total = Math.max(0, Math.ceil(ms / 1000));

    return `${Math.floor(total / 60)}:${String(total % 60).padStart(2, '0')}`;
};

const icon = (game) => {
    if (game.icon) {
        const image = node('img', 'room-vote__icon');
        image.src = game.icon;
        image.alt = '';

        return image;
    }

    const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
    svg.setAttribute('class', 'room-vote__icon');
    svg.setAttribute('viewBox', '0 0 24 24');
    svg.setAttribute('fill', 'none');
    svg.setAttribute('stroke', 'currentColor');
    svg.setAttribute('stroke-width', '1.7');
    svg.setAttribute('aria-hidden', 'true');
    path.setAttribute('d', ICON);
    svg.append(path);

    return svg;
};

export default function RoomLobby(element, data) {
    const find = (selector) => element.querySelector(selector);
    const labels = JSON.parse(data.labels);
    const ui = {
        meta: find('.room-lobby__meta'),
        groupTitle: find('.room-lobby__group-title'),
        slots: find('.room-lobby__slots'),
        copy: find('.room-lobby__copy'),
        vote: find('.room-vote'),
        title: find('.room-vote__title'),
        help: find('.room-vote__help'),
        voted: find('.room-vote__voted'),
        clock: find('.room-vote__clock'),
        options: find('.room-vote__options'),
        watch: find('.room-vote__watch'),
        winner: find('.room-vote__winner'),
        winnerName: find('.room-vote__winner-name'),
        winnerNote: find('.room-vote__winner-note'),
        launch: find('.room-vote__launch'),
        rules: find('.room-rules'),
        rulesTitle: find('.room-rules__title'),
        rulesClock: find('.room-rules__clock'),
        rulesHelp: find('.room-rules__help'),
        rulesFields: find('.room-rules__fields'),
        rulesActions: find('.room-rules__actions'),
        rulesDefault: find('.room-rules__default'),
        rulesApply: find('.room-rules__apply'),
        present: find('.room-chat__present'),
        messages: find('.room-chat__messages'),
        form: find('.room-chat__form'),
    };
    let room = JSON.parse(data.snapshot);
    let offset = room.serverTime - Date.now();
    let nudgedAt = null;
    let choices = {};
    let choicesRound = null;
    let sending = false;
    let playing = room.status === 'playing';

    const post = (url, body) => dom.post(url, data.token, body);
    const now = () => Date.now() + offset;
    const isPresent = (member) => now() - member.seenAt < room.presence;
    const players = () => room.members.filter((member) => !member.spectator && isPresent(member));
    const me = () => room.members.find((member) => member.id === data.player);
    const votes = () => room.round?.votes ?? {};
    const plural = (count) => format(count > 1 ? labels.vote_many : labels.vote_one, { count });

    const update = (snapshot) => {
        room = snapshot;
        offset = snapshot.serverTime - Date.now();

        if (room.status === 'playing' && room.match && !playing && me() && !me().spectator) {
            window.location.href = data.matchUrl.replace('__code__', room.match);

            return;
        }

        playing = room.status === 'playing';
        render();
    };

    const ping = async () => {
        try {
            const response = await post(data.ping);

            if (response.ok) {
                update(await response.json());
            }
        } catch {}
    };

    const slot = (member) => {
        const present = isPresent(member);
        const host = member.id === room.host;
        const voted = room.status === 'voting' && votes()[member.id] !== undefined;
        const status = member.spectator ? labels.spectator : !present ? labels.away : room.status === 'voting' ? (voted ? labels.voted : labels.thinking) : labels.ready;
        const item = node('div', 'room-slot' + (host ? ' room-slot--host' : '') + (present ? '' : ' room-slot--away'));
        const text = node('span', 'room-slot__text');

        text.append(
            name(member, 'room-slot__name', member.username + (host ? labels.host_suffix : '') + (member.id === data.player ? labels.you : '')),
            ...[title(member, 'room-slot__title')].filter(Boolean),
            node('span', 'room-slot__status' + (voted ? ' room-slot__status--voted' : ''), status),
        );
        item.append(avatar(member, 'avatar--base'), text);

        return item;
    };

    const empty = () => {
        const button = node('button', 'room-slot room-slot--empty', labels.invite);
        button.type = 'button';
        button.addEventListener('click', () => ui.copy.click());

        return button;
    };

    const renderGroup = () => {
        const list = [...room.members].sort((a, b) => (b.id === room.host) - (a.id === room.host) || isPresent(b) - isPresent(a) || a.spectator - b.spectator);
        const count = players().length;

        ui.meta.textContent = format(labels.meta, { count, max: room.maxPlayers, played: room.played });
        ui.groupTitle.textContent = `${labels.group} · ${count} / ${room.maxPlayers}`;
        ui.present.textContent = format(labels.present, { count: room.members.filter(isPresent).length });
        ui.slots.replaceChildren(...list.map(slot), ...Array.from({ length: Math.max(0, room.maxPlayers - list.length) }, empty));
    };

    const option = (game, counts, total) => {
        const done = room.status !== 'voting';
        const mine = votes()[data.player] === game.id;
        const winner = done && room.round.winner === game.id;
        const count = counts[game.id] ?? 0;
        const card = node('button', 'room-vote__option' + (mine ? ' room-vote__option--mine' : '') + (winner ? ' room-vote__option--winner' : '') + (done && !winner ? ' room-vote__option--out' : ''));
        const thumb = node('span', 'room-vote__thumb');
        const body = node('span', 'room-vote__body');
        const head = node('span', 'room-vote__name-row');
        const track = node('span', 'room-vote__track');
        const fill = node('span', 'room-vote__fill');
        const foot = node('span', 'room-vote__foot');
        const voters = node('span', 'room-vote__voters');
        const spectator = me()?.spectator ?? true;

        card.type = 'button';
        card.setAttribute('role', 'radio');
        card.setAttribute('aria-checked', String(mine));
        card.disabled = done || spectator;
        card.style.setProperty('--game-color', game.color);
        card.addEventListener('click', () => post(data.vote, { game: game.id }));

        thumb.append(icon(game));

        if (winner) {
            thumb.append(node('span', 'room-vote__badge', labels.winner_badge));
        }

        fill.style.width = `${total ? (count / total) * 100 : 0}%`;
        track.append(fill);
        head.append(node('strong', 'room-vote__name', game.name), node('span', 'room-vote__players', game.players));

        room.members.filter((member) => votes()[member.id] === game.id).forEach((member) => {
            const chip = avatar(member, 'avatar--sm');
            chip.title = member.username;
            voters.append(chip);
        });

        foot.append(voters, node('span', 'room-vote__count', plural(count)));
        body.append(head, track, foot, node('span', 'room-vote__cta' + (mine ? ' room-vote__cta--mine' : ''), done ? (winner ? labels.launching : '—') : (mine ? labels.your_vote : labels.vote)));
        card.append(thumb, body);

        return card;
    };

    const renderVote = () => {
        const round = room.round;
        const settled = room.status === 'configuring' || room.status === 'launching';
        const active = room.status === 'voting' || settled;
        const total = players().length;

        ui.vote.classList.toggle('room-vote--done', settled);
        ui.voted.hidden = !active;
        ui.clock.hidden = !active;
        ui.winner.hidden = !settled;
        ui.watch.hidden = !(room.status === 'playing' && room.match);

        if (room.match) {
            ui.watch.href = data.matchUrl.replace('__code__', room.match);
        }

        if (!active) {
            ui.title.textContent = room.status === 'playing' ? labels.playing_title : labels.waiting_title;
            ui.help.textContent = room.status === 'playing' ? labels.playing : round?.blockedAt ? labels.no_games : labels.waiting;
            ui.options.replaceChildren();

            return;
        }

        const counts = {};
        Object.values(votes()).forEach((game) => {
            counts[game] = (counts[game] ?? 0) + 1;
        });

        ui.title.textContent = format(labels.vote_title, { number: round.number });
        ui.help.textContent = format(labels.vote_help, { choices: round.games.length, compatible: round.compatible, players: round.players });
        ui.voted.textContent = format(labels.voted_count, { voted: players().filter((member) => votes()[member.id] !== undefined).length, total });
        ui.options.replaceChildren(...round.games.map((game) => option(game, counts, total)));

        if (settled) {
            const winner = round.games.find((game) => game.id === round.winner);
            ui.winnerName.textContent = format(labels.wins, { name: winner?.name ?? '', votes: plural(counts[round.winner] ?? 0) });
            ui.launch.hidden = room.status !== 'launching' || room.host !== data.player;
        }

        renderRules();
        tick();
    };

    const summary = () => (room.round?.setup ?? [])
        .map((setting) => `${setting.label} : ${room.round.settings?.[setting.name] ?? setting.default}${setting.suffix}`)
        .join(' · ');

    const hostName = () => room.members.find((member) => member.id === room.host)?.username ?? '';

    const chip = (setting, value) => {
        const button = node('button', 'room-rules__chip' + (choices[setting.name] === value ? ' room-rules__chip--active' : ''), `${value}${setting.suffix}`);
        button.type = 'button';
        button.setAttribute('aria-pressed', String(choices[setting.name] === value));

        if (value === setting.default) {
            button.append(node('span', 'room-rules__tag', labels.rules_default_tag));
        }

        button.addEventListener('click', () => {
            choices[setting.name] = value;
            renderRules();
        });

        return button;
    };

    const renderRules = () => {
        const round = room.round;
        const configuring = room.status === 'configuring';
        const host = room.host === data.player;
        ui.rules.hidden = !configuring;

        if (room.status === 'launching' && round?.setup?.length) {
            ui.winnerNote.dataset.rules = format(labels.rules_chosen, { rules: summary() });
        } else {
            delete ui.winnerNote.dataset.rules;
        }

        if (!configuring) {
            return;
        }

        const game = round.games.find((item) => item.id === round.winner);

        if (choicesRound !== round.number) {
            choicesRound = round.number;
            choices = { ...(round.settings ?? {}) };
        }

        ui.rulesTitle.textContent = format(labels.rules_title, { game: game?.name ?? '' });
        ui.rulesHelp.textContent = host ? labels.rules_host : format(labels.rules_waiting, { host: hostName(), game: game?.name ?? '' });
        ui.rulesActions.hidden = !host;
        ui.rules.classList.toggle('room-rules--waiting', !host);
        ui.rulesFields.replaceChildren(...(host ? round.setup.map((setting) => {
            const field = node('div', 'room-rules__field');
            const options = node('div', 'room-rules__options');
            options.setAttribute('role', 'group');
            options.setAttribute('aria-label', setting.label);
            options.append(...setting.choices.map((value) => chip(setting, value)));
            field.append(node('span', 'room-rules__label', setting.label), options);

            return field;
        }) : [node('span', 'room-rules__spinner')]));
    };

    const configure = async (body) => {
        if (sending) {
            return;
        }

        sending = true;
        ui.rulesApply.disabled = true;
        ui.rulesDefault.disabled = true;

        try {
            await post(data.configure, body);
        } finally {
            sending = false;
            ui.rulesApply.disabled = false;
            ui.rulesDefault.disabled = false;
        }
    };

    const render = () => {
        renderGroup();
        renderVote();
    };

    const tick = () => {
        const round = room.round;

        if (room.status === 'voting') {
            const left = round.endsAt - now();
            ui.clock.textContent = clock(left);
            ui.clock.classList.toggle('room-vote__clock--urgent', left <= 5000);
            ui.clock.classList.remove('room-vote__clock--done');
            nudge(round.endsAt);
        }

        if (room.status === 'configuring') {
            const left = round.configureEndsAt - now();
            ui.clock.textContent = labels.done;
            ui.clock.classList.add('room-vote__clock--done');
            ui.clock.classList.remove('room-vote__clock--urgent');
            ui.winnerNote.textContent = (round.tie ? labels.tie + ' ' : '') + labels.configuring;
            ui.rulesClock.textContent = format(labels.rules_left, { time: clock(left) });
            ui.rulesClock.classList.toggle('room-rules__clock--urgent', left <= 10000);
            nudge(round.configureEndsAt);
        }

        if (room.status === 'launching') {
            ui.clock.textContent = labels.done;
            ui.clock.classList.add('room-vote__clock--done');
            ui.clock.classList.remove('room-vote__clock--urgent');
            ui.winnerNote.textContent = (round.tie ? labels.tie + ' ' : '') + format(labels.launch_in, { seconds: Math.max(0, Math.ceil((round.launchAt - now()) / 1000)) }) + (ui.winnerNote.dataset.rules ? ' · ' + ui.winnerNote.dataset.rules : '');
            nudge(round.launchAt);
        }
    };

    const nudge = (at) => {
        if (now() >= at + 300 && nudgedAt !== at) {
            nudgedAt = at;
            ping();
        }
    };

    const message = (item) => {
        if (item.user === null) {
            return node('span', 'room-chat__system', item.text);
        }

        const line = node('div', 'room-chat__message');
        const author = room.members.find((member) => member.id === item.user) ?? item;
        line.append(name({ username: item.username ?? '', look: author.look }, 'room-chat__author' + (item.user === data.player ? ' room-chat__author--me' : '')), node('span', 'room-chat__text', item.text));

        return line;
    };

    const append = (item) => {
        ui.messages.append(message(item));

        while (ui.messages.children.length > 50) {
            ui.messages.firstElementChild.remove();
        }

        ui.messages.scrollTop = ui.messages.scrollHeight;
    };

    ui.form.addEventListener('submit', (event) => {
        event.preventDefault();
        const input = ui.form.elements.text;
        const text = input.value.trim();

        if (text !== '') {
            post(data.message, { text });
            input.value = '';
        }
    });

    ui.launch.addEventListener('click', () => post(data.launch));
    ui.rulesApply.addEventListener('click', () => configure({ settings: choices }));
    ui.rulesDefault.addEventListener('click', () => configure({ defaults: true }));

    new EventSource(data.url).onmessage = (event) => {
        const payload = JSON.parse(event.data);

        if (payload.type === 'state') {
            update(payload.room);
        }

        if (payload.type === 'closed' || (payload.type === 'kicked' && payload.user === data.player)) {
            element.replaceChildren(node('p', 'room-lobby__closed', payload.message ?? labels.kicked));
            setTimeout(() => {
                window.location.href = data.home;
            }, 4000);
        }

        if (payload.type === 'message') {
            append(payload.message);
        }
    };

    JSON.parse(data.messages).forEach(append);
    render();
    setInterval(tick, 250);
    setInterval(() => {
        renderGroup();
        ping();
    }, PING_INTERVAL);
}

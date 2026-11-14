import dom from 'shared/dom';
import badge from 'shared/badge';
import sound from 'shared/sound';

const { node, format, avatar, name } = dom;

const clock = (ms) => {
    const total = Math.max(0, Math.floor(ms / 1000));

    return `${Math.floor(total / 60)}:${String(total % 60).padStart(2, '0')}`;
};

const seconds = (ms) => Math.max(0, Math.ceil(ms / 1000));

class SafeHtml extends String {}

const ENTITIES = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };

const piece = (value) => {
    if (value instanceof SafeHtml) {
        return String(value);
    }

    if (Array.isArray(value)) {
        return value.map(piece).join('');
    }

    return value === null || value === undefined || value === false ? '' : String(value).replace(/[&<>"']/g, (character) => ENTITIES[character]);
};

const html = (strings, ...values) => new SafeHtml(strings.reduce((output, part, index) => output + part + (index < values.length ? piece(values[index]) : ''), ''));

const hour = (ms) => new Date(ms).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });

export default async function GameStage(element, data) {
    const find = (selector) => element.querySelector(selector);
    const labels = JSON.parse(data.labels);
    const languages = JSON.parse(data.languages || '{}');
    const codes = Object.keys(languages);
    const picker = find('.game-language');
    const storage = (store, key, value) => {
        try {
            return value === undefined ? store.getItem(key) : store.setItem(key, value);
        } catch {
            return null;
        }
    };
    const remembered = storage(sessionStorage, `bg-game-language-${data.slug}`);
    const preferred = storage(localStorage, `bg-game-language-${data.slug}`);
    let locale = [remembered, preferred, data.locale].find((code) => codes.includes(code)) ?? codes[0] ?? data.locale;
    let choosing = codes.length > 1 && !codes.includes(remembered) && !['finished', 'cancelled'].includes(JSON.parse(data.snapshot).status);
    const settings = JSON.parse(data.settings);
    const ping = find('.game-stage__ping');
    const views = { search: find('.game-search'), countdown: find('.game-countdown'), play: find('.game-stage__play'), results: find('.game-results') };
    const countdown = find('.game-countdown__value');
    const search = {
        tag: find('.game-search__tag'),
        headline: find('.game-search__headline'),
        count: find('.game-search__count'),
        track: find('.game-search__track'),
        fill: find('.game-search__fill'),
        sub: find('.game-search__sub'),
        clock: find('.game-search__clock-value'),
        slots: find('.game-search__slots'),
        launch: find('.game-search__launch'),
        leave: find('.game-search__leave'),
    };
    const play = {
        screen: find('.game-stage__screen'),
        progress: find('.game-stage__progress'),
        dots: find('.game-stage__dots'),
        count: find('.game-stage__count'),
        ranking: find('.game-stage__ranking'),
    };
    const results = {
        winner: find('.game-results__winner'),
        podium: find('.game-results__podium'),
        rewards: find('.game-rewards'),
        vote: find('.game-results__vote'),
        timer: find('.game-results__timer'),
        help: find('.game-results__help'),
        yes: find('.game-results__yes'),
        needed: find('.game-results__needed'),
        fill: find('.game-results__fill'),
        mark: find('.game-results__mark'),
        voters: find('.game-results__voters'),
        actions: find('.game-results__actions'),
        relaunch: find('.game-results__relaunch'),
        declined: find('.game-results__declined'),
        dump: find('.game-results__dump'),
    };
    const alerts = [...element.querySelectorAll('.game-alert')].map((root) => ({
        root,
        tag: root.querySelector('.game-alert__tag'),
        text: root.querySelector('.game-alert__text'),
        link: root.querySelector('.game-alert__link'),
    }));
    const cut = {
        root: find('.game-cut'),
        reason: find('.game-cut__reason'),
        back: find('.game-cut__back'),
        follow: find('.game-cut__follow'),
    };
    const listeners = new Set();
    const client = await import(data.client);
    let snapshot = JSON.parse(data.snapshot);
    let secret = Object.assign({}, JSON.parse(data.secret || '{}'));
    let offset = snapshot.serverTime - Date.now();
    let view = null;
    let mounted = false;
    let changing = false;
    let leavingAt = null;
    let readyAt = null;
    let cutAt = null;

    const post = (url, body) => dom.post(url, data.token, body);

    const sync = async () => {
        let best = null;

        try {
            for (let index = 0; index < 3; index += 1) {
                const sent = Date.now();
                const { time } = await (await fetch(data.clock, { headers: { Accept: 'application/json' } })).json();
                const received = Date.now();

                if (best === null || received - sent < best.rtt) {
                    best = { rtt: received - sent, offset: time - (sent + received) / 2 };
                }
            }

            offset = best.offset;

            if (ping) {
                ping.hidden = !settings.ping;
                ping.textContent = format(labels.ping, { ms: best.rtt });
            }
        } catch {}
    };

    let searchStartedAt = null;
    let counted = null;
    let previous = null;
    let ended = false;
    const audio = sound({ slug: data.slug, effects: settings.sound / 100, music: settings.music / 100 });
    const soundButtons = [...element.querySelectorAll('[data-sound]')];

    const paintSound = (enabled) => {
        soundButtons.forEach((button) => {
            const on = enabled[button.dataset.sound];
            button.setAttribute('aria-pressed', String(on));
            button.classList.toggle('game-stage__icon-btn--off', !on);
            button.title = labels[`sound_${button.dataset.sound}_${on ? 'on' : 'off'}`] ?? button.title;
        });
    };

    soundButtons.forEach((button) => button.addEventListener('click', () => audio.toggle(button.dataset.sound)));
    audio.onChange(paintSound);
    paintSound(audio.enabled());

    const myPosition = () => {
        const me = String(data.player);
        const result = snapshot.results?.[me] ?? snapshot.results?.[data.player];

        if (result?.position) {
            return result.position;
        }

        const index = (snapshot.scoreboard ?? []).findIndex((row) => String(row.player) === me);

        return index >= 0 ? index + 1 : null;
    };

    const listen = (before, after) => {
        if (!before || !after || typeof before !== 'object' || typeof after !== 'object' || after.finished) {
            return;
        }

        const me = String(data.player);
        const score = (state) => Number(state.scores?.[me] ?? NaN);

        if (score(after) > score(before)) {
            audio.play('gain');
        }

        if (after.round !== undefined && before.round !== undefined && after.round !== before.round) {
            audio.play('round');
        } else if (Array.isArray(after.players) && after.turn !== undefined && after.turn !== before.turn && String(after.players[after.turn]) === me) {
            audio.play('turn');
        } else if (after.phase === 'reveal' && before.phase !== 'reveal') {
            audio.play('reveal');
        }
    };

    const fit = (root) => {
        if (!root) {
            return;
        }

        const style = getComputedStyle(root);

        if (style.display.includes('flex')) {
            const column = style.flexDirection.startsWith('column');

            if (column && style.justifyContent === 'center') {
                root.style.justifyContent = 'safe center';
            }

            if (!column && style.alignItems === 'center') {
                root.style.alignItems = 'safe center';
            }
        }

        if (style.overflowY === 'hidden') {
            root.style.overflowY = 'auto';
        }
    };

    const context = {
        player: data.player,
        settings: { sound: settings.sound / 100, music: settings.music / 100, vibrations: settings.vibrations },
        sound: { play: (name) => audio.play(name) },
        get snapshot() {
            return snapshot;
        },
        get secret() {
            return secret;
        },
        now: () => Date.now() + offset,
        on: (listener) => listeners.add(listener),
        send: (action, payload = {}) => (snapshot.status === 'playing' && context.now() >= (snapshot.timeline.startsAt ?? 0)
            ? post(data.action, { action, payload })
            : Promise.resolve(new Response(null, { status: 204 }))),
        get locale() {
            return locale;
        },
        t: (key, params = {}) => format(languages[locale]?.[key] ?? languages[codes[0]]?.[key] ?? key, params),
        html,
        text: (target, key, params = {}) => {
            target.textContent = context.t(key, params);

            return target;
        },
    };

    const choose = (code) => {
        locale = code;
        choosing = false;
        storage(sessionStorage, `bg-game-language-${data.slug}`, code);
        storage(localStorage, `bg-game-language-${data.slug}`, code);
        picker.hidden = true;
        tick();
    };

    if (picker) {
        picker.querySelectorAll('.game-language__option').forEach((option) => {
            option.classList.toggle('game-language__option--active', option.dataset.locale === locale);
            option.addEventListener('click', () => choose(option.dataset.locale));
        });
        picker.hidden = !choosing;

        if (choosing) {
            picker.querySelector('.game-language__option--active')?.focus();
        }
    }

    const playersById = () => Object.fromEntries(snapshot.players.map((player) => [player.id, player]));
    const nameOf = (player) => (player.username ?? '') + (player.id === data.player ? labels.you : '');
    const voteOf = (id) => (snapshot.votes ?? {})[id];
    const expired = () => context.now() >= (snapshot.timeline.voteEndsAt ?? Infinity);

    const viewOf = () => {
        const now = context.now();

        if (snapshot.status === 'cancelled') {
            return 'play';
        }

        if (snapshot.status === 'finished') {
            return now >= snapshot.timeline.resultsAt ? 'results' : 'play';
        }

        if (snapshot.mode === 'solo' && (snapshot.status === 'waiting' || now < snapshot.timeline.startsAt)) {
            return 'countdown';
        }

        return snapshot.status === 'playing' && now >= snapshot.timeline.startsAt ? 'play' : 'search';
    };

    const slot = (player) => {
        const item = node('li', 'game-search__slot' + (player ? '' : ' game-search__slot--empty') + (player?.id === data.player ? ' game-search__slot--me' : ''));

        if (player) {
            item.append(avatar(player, 'avatar--md'), name(player, 'game-search__player', nameOf(player)));
        } else {
            item.append(node('span', 'game-search__unknown', '?'), node('span', 'game-search__looking', labels.search_looking));
        }

        return item;
    };

    const renderSearch = () => {
        const found = snapshot.players.length;
        const total = snapshot.maxPlayers;
        const full = snapshot.status !== 'waiting';

        views.search.classList.toggle('game-search--found', full);
        search.tag.textContent = full ? labels.search_full : labels.search_tag;
        search.headline.textContent = full ? labels.search_found : labels.search_title;
        search.count.textContent = format(labels.search_count, { found, total });
        search.fill.style.width = `${(found / total) * 100}%`;
        search.track.setAttribute('aria-valuenow', String(found));
        search.track.setAttribute('aria-valuemax', String(total));
        search.leave.hidden = full;
        search.launch.hidden = !full;
        search.slots.replaceChildren(...Array.from({ length: total }, (_, index) => slot(snapshot.players[index])));
    };

    const renderPlay = () => {
        const value = snapshot.progress;
        const players = playersById();
        const rows = snapshot.scoreboard.length > 0 ? snapshot.scoreboard : snapshot.players.map((player) => ({ player: player.id, score: '', detail: '' }));
        play.progress.hidden = !value;

        if (value) {
            play.dots.replaceChildren(...Array.from({ length: value.total }, (_, index) => {
                const round = index + 1;
                const modifier = round < value.current ? ' game-stage__dot--done' : round === value.current ? ' game-stage__dot--current' : '';

                return node('span', 'game-stage__dot' + modifier);
            }));
            play.count.textContent = `${value.current} / ${value.total}`;
        }

        play.ranking.replaceChildren(...rows.map((row, index) => {
            const player = players[row.player] ?? {};
            const item = node('li', 'game-stage__rank' + (row.player === data.player ? ' game-stage__rank--me' : ''));
            const info = node('span', 'game-stage__rank-info');

            info.append(name(player, 'game-stage__rank-name'), node('span', 'game-stage__rank-detail', row.detail));
            item.append(node('span', 'game-stage__rank-position', String(index + 1)), avatar(player, 'avatar--sm'), info, node('span', 'game-stage__rank-score', row.score));

            return item;
        }));
    };

    const step = (row, index, players) => {
        const player = players[row.player] ?? {};
        const item = node('div', `game-results__step game-results__step--${index + 1}`);

        item.append(
            avatar(player, index === 0 ? 'avatar--xl' : 'avatar--lg'),
            name(player, 'game-results__name'),
            node('span', 'game-results__score', snapshot.mode === 'solo' ? row.score : format(labels.results_points, { score: row.score })),
            node('div', 'game-results__block', String(index + 1)),
        );

        return item;
    };

    const voter = (player) => {
        const vote = voteOf(player.id);
        const state = vote === true ? 'yes' : vote === false ? 'no' : 'wait';
        const item = node('li', 'game-results__voter');

        item.append(avatar(player, 'avatar--sm'), name(player, 'game-results__voter-name', nameOf(player)), node('span', `game-results__state game-results__state--${state}`, labels[`vote_${state}`]));

        return item;
    };

    const progression = (title, change, xp, modifier) => {
        const card = node('div', `game-rewards__card game-rewards__card--${modifier}`);
        const head = node('span', 'game-rewards__head');
        const track = node('div', 'game-rewards__track');
        const fill = node('div', 'game-rewards__fill');
        const level = change.before === change.after
            ? format(labels.rewards_level, { level: change.after })
            : format(labels.rewards_level_up, { before: change.before, after: change.after });

        fill.style.width = `${change.percent}%`;
        track.append(fill);
        head.append(node('strong', 'game-rewards__level', level), node('span', 'game-rewards__xp', format(labels.rewards_xp, { xp })));
        card.append(node('span', 'game-rewards__title', title), head, track);

        return card;
    };

    const unlocked = (view) => {
        const card = node('div', 'game-rewards__card game-rewards__card--badge');
        const text = node('span', 'game-rewards__text');

        text.append(node('span', 'game-rewards__title', view.game ? labels.rewards_badge_game : labels.rewards_badge), node('strong', 'game-rewards__badge-name', view.name));
        card.append(badge(view, 40), text);

        return card;
    };

    const wallet = (mine) => {
        const card = node('div', 'game-rewards__card game-rewards__card--wallet');
        const head = node('span', 'game-rewards__head');

        head.append(
            node('strong', 'game-rewards__coins', format(labels.rewards_coins, { coins: mine.coins })),
            node('span', 'game-rewards__lives', mine.lives === null ? labels.rewards_lives_unlimited : format(labels.rewards_lives, { lives: mine.lives })),
        );
        card.append(node('span', 'game-rewards__title', labels.rewards_wallet), head);

        return card;
    };

    const rating = (mine) => {
        const card = node('div', 'game-rewards__card game-rewards__card--rating');
        const head = node('span', 'game-rewards__head');
        const delta = mine.rating.after - mine.rating.before;

        head.append(
            node('strong', 'game-rewards__level', `${mine.rating.before} → ${mine.rating.after}`),
            node('span', 'game-rewards__delta' + (delta < 0 ? ' game-rewards__delta--down' : ''), (delta >= 0 ? '+' : '') + delta),
        );
        const title = node('span', 'game-rewards__title', `${labels.rewards_rating} · `);
        const tier = node('span', 'rank-badge', mine.rating.tier?.label ?? '');
        tier.style.setProperty('--rank-color', mine.rating.tier?.color ?? '#8A93B5');
        title.append(tier);
        card.append(title, head);

        return card;
    };

    const victory = () => {
        const winner = playersById()[snapshot.scoreboard[0]?.player];
        const effect = winner?.look?.victory;

        if (!effect || typeof effect !== 'object' || element.querySelector('.victory-fx') || document.documentElement.dataset.motion === 'reduced' || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            return;
        }

        const layer = node('div', `victory-fx victory-fx--${effect.animation ?? 'rain'}`);
        const view = { name: '', path: effect.path, design: { ...effect, label: '', double: false } };

        for (let index = 0; index < (effect.count ?? 30); index += 1) {
            const piece = node('span', 'victory-fx__piece');
            const angle = Math.random() * Math.PI * 2;
            const distance = 30 + Math.random() * 45;
            piece.style.setProperty('--x', `${Math.random() * 100}%`);
            piece.style.setProperty('--delay', `${Math.random() * 0.9}s`);
            piece.style.setProperty('--drift', `${(Math.random() - 0.5) * 180}px`);
            piece.style.setProperty('--dx', `${Math.cos(angle) * distance}vw`);
            piece.style.setProperty('--dy', `${Math.sin(angle) * distance}vh`);
            piece.style.setProperty('--spin', `${(Math.random() - 0.5) * 720}deg`);
            piece.append(badge(view, 22 + Math.round(Math.random() * 18)));
            layer.append(piece);
        }

        element.append(layer);
        setTimeout(() => layer.remove(), 4600);
    };

    const renderRewards = () => {
        const mine = (snapshot.rewards ?? {})[data.player];

        if (!results.rewards || !mine) {
            return;
        }

        results.rewards.hidden = false;
        results.rewards.replaceChildren(
            progression(labels.rewards_general, mine.general, mine.xp, 'general'),
            progression(labels.rewards_game, mine.game, mine.xp, 'game'),
            ...(mine.rating ? [rating(mine)] : []),
            ...(mine.coins !== undefined ? [wallet(mine)] : []),
            ...mine.badges.map(unlocked),
        );
    };

    const renderResults = () => {
        const players = playersById();
        const top = snapshot.scoreboard.slice(0, 3);
        const total = snapshot.players.length;
        const yes = snapshot.players.filter((player) => voteOf(player.id) === true).length;
        const mine = voteOf(data.player);
        const next = Boolean(snapshot.next);

        results.podium?.replaceChildren(...[1, 0, 2].filter((index) => top[index]).map((index) => step(top[index], index, players)));
        renderRewards();
        victory();

        if (snapshot.mode === 'solo') {
            if (results.dump) {
                results.dump.textContent = JSON.stringify({ results: snapshot.results, state: snapshot.state }, null, 2);
            }

            return;
        }

        results.winner.textContent = players[top[0]?.player]?.username ?? '';
        results.help.textContent = format(labels.vote_help, { needed: snapshot.majority, total });
        results.yes.textContent = format(labels.vote_count, { yes, total });
        results.needed.textContent = format(labels.vote_majority, { needed: snapshot.majority });
        results.fill.style.width = `${(yes / total) * 100}%`;
        results.mark.style.left = `${(snapshot.majority / total) * 100}%`;
        results.vote.classList.toggle('game-results__vote--reached', next);
        results.voters.replaceChildren(...snapshot.players.map(voter));
        results.relaunch.hidden = !next;
        results.declined.hidden = mine !== false || changing || next;
        results.actions.hidden = (mine !== undefined && !changing) || next || expired();

        if (snapshot.room && settings.stay_in_room && !next && expired() && leavingAt === null) {
            leavingAt = Date.now() + 3000;
            setTimeout(() => {
                window.location.href = data.gameUrl;
            }, 3000);
        }

        if (next && leavingAt === null) {
            leavingAt = Date.now() + 3000;
            setTimeout(() => {
                window.location.href = mine === true ? data.matchUrl.replace('__code__', snapshot.next) : data.gameUrl;
            }, 3000);
        }
    };

    const renderMaintenance = () => {
        const warning = snapshot.maintenance;
        const now = context.now();
        const started = warning && now >= warning.startsAt;
        const stopping = Boolean(warning) && warning.policy === 'stop' && started && snapshot.status === 'playing';
        const cancelled = snapshot.status === 'cancelled' || stopping;
        const url = warning?.target === 'site' ? data.maintenanceUrl : data.gameUrl;

        if (stopping && cutAt !== warning.startsAt) {
            cutAt = warning.startsAt;
            post(data.ready, {});
        }

        cut.root.hidden = !cancelled;

        if (cancelled) {
            const value = snapshot.progress;

            cut.reason.textContent = value ? format(labels.maintenance_cut_round, value) : labels.maintenance_cut;
            cut.back.textContent = warning ? format(labels.maintenance_back, { time: hour(warning.endsAt) }) : '';
            cut.follow.href = url;
        }

        alerts.forEach((alert) => {
            alert.root.hidden = !warning || cancelled || snapshot.status === 'finished';

            if (alert.root.hidden) {
                return;
            }

            const minutes = Math.ceil((warning.startsAt - now) / 60000);
            const subject = warning.target === 'site' ? labels.maintenance_site : data.gameName;

            alert.tag.textContent = started ? labels.maintenance_now : format(labels.maintenance_at, { time: hour(warning.startsAt) });
            alert.text.textContent = started
                ? labels.maintenance_running
                : format(labels.maintenance_soon, { subject, delay: format(labels.maintenance_minutes, { minutes: Math.max(1, minutes) }) })
                    + ' ' + (warning.policy === 'stop' ? labels.maintenance_stop : labels.maintenance_finish);
            alert.link.href = url;
        });
    };

    const tick = () => {
        renderMaintenance();
        const current = viewOf();
        const now = context.now();

        if (current !== view && !(current === 'play' && choosing)) {
            view = current;
            Object.entries(views).forEach(([name, root]) => {
                root.hidden = name !== view;
            });

            if (view === 'play') {
                if (counted !== null) {
                    audio.play('go');
                }

                if (snapshot.status === 'playing') {
                    audio.startMusic();
                }
            }

            if (view === 'results') {
                audio.stopMusic(1.5);
            }

            if (view === 'play' && !mounted) {
                mounted = true;
                client.default(play.screen, context);
                fit(play.screen.firstElementChild);

                if (settings.fullscreen && !document.fullscreenElement && find('.game-stage__fullscreen')) {
                    element.requestFullscreen().catch(() => {});
                }
                listeners.forEach((listener) => listener(snapshot));
            }
        }

        if (view === 'countdown') {
            countdown.textContent = String(snapshot.timeline.startsAt ? Math.max(1, seconds(snapshot.timeline.startsAt - now)) : 3);

            if (snapshot.timeline.startsAt && countdown.textContent !== counted) {
                counted = countdown.textContent;
                audio.play('tick');
            }
        }

        if (view === 'search') {
            const left = seconds(snapshot.timeline.startsAt - now);
            const fill = snapshot.timeline.fillEndsAt;
            const waiting = snapshot.status === 'waiting';

            searchStartedAt ??= now;
            search.clock.textContent = clock(now - searchStartedAt);
            search.sub.textContent = !waiting ? format(labels.search_launch, { seconds: left }) : fill ? format(labels.search_fill, { seconds: seconds(fill - now) }) : labels.search_help;
            search.launch.textContent = format(labels.search_enter, { seconds: left });

            if (waiting && fill && now >= fill + 300 && readyAt !== fill) {
                readyAt = fill;
                post(data.ready, {});
            }
        }

        if (view === 'results' && snapshot.mode !== 'solo') {
            results.timer.textContent = snapshot.next || expired() ? labels.vote_done : format(labels.vote_ends, { time: clock(snapshot.timeline.voteEndsAt - now) });
            results.relaunch.textContent = format(labels.vote_relaunch, { seconds: seconds((leavingAt ?? Date.now()) - Date.now()) });
            results.actions.hidden = results.actions.hidden || expired();

            if (snapshot.room && !snapshot.next && expired() && leavingAt === null) {
                renderResults();
            }
        }
    };

    const update = () => {
        renderSearch();
        renderPlay();

        if (snapshot.status === 'finished') {
            renderResults();

            if (!ended && mounted) {
                ended = true;
                audio.stopMusic(1.2);
                audio.play(myPosition() === 1 ? 'win' : 'lose');
            }
        } else if (snapshot.status === 'playing' && view === 'play') {
            audio.startMusic();
        }

        listen(previous, snapshot.state);
        previous = snapshot.state;

        tick();

        if (mounted) {
            listeners.forEach((listener) => listener(snapshot));
        }
    };

    results.actions?.querySelectorAll('[data-replay]').forEach((button) => {
        button.addEventListener('click', () => {
            changing = false;
            post(data.vote, { replay: button.dataset.replay === '1' });
        });
    });

    find('.game-results__change')?.addEventListener('click', () => {
        changing = true;
        renderResults();
    });

    play.screen.addEventListener('pointerdown', (event) => {
        const target = event.target.closest('button, [role="button"], a, input[type="range"], select');

        if (target && !target.disabled && target.getAttribute('aria-disabled') !== 'true' && !target.closest('[data-silent]')) {
            audio.play('click');
        }
    }, true);

    find('.game-stage__fullscreen')?.addEventListener('click', () => {
        document.fullscreenElement ? document.exitFullscreen() : element.requestFullscreen();
    });

    const reveal = async () => {
        if (!snapshot.private) {
            return;
        }

        try {
            const response = await fetch(data.secretUrl, { headers: { Accept: 'application/json' } });
            secret = (await response.json()).secret ?? {};
        } catch {}
    };

    let pending = Promise.resolve();

    const EMOTES = JSON.parse(data.emoteIcons ?? '{}');
    const emoteIcon = (key) => {
        if (!EMOTES[key]) {
            return node('span', 'game-emotes__symbol game-emotes__symbol--text', 'GG');
        }

        const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
        svg.setAttribute('viewBox', '0 0 24 24');
        svg.setAttribute('class', 'game-emotes__symbol');
        Object.entries({ fill: 'none', stroke: 'currentColor', 'stroke-width': '2', 'stroke-linecap': 'round', 'stroke-linejoin': 'round' }).forEach(([key, value]) => svg.setAttribute(key, value));
        path.setAttribute('d', EMOTES[key]);
        svg.append(path);

        return svg;
    };
    const emoteLayer = node('div', 'game-emotes');
    element.append(emoteLayer);

    const showEmote = (event) => {
        const player = playersById()[event.player] ?? {};
        const bubble = node('div', 'game-emotes__bubble' + (event.player === data.player ? ' game-emotes__bubble--me' : ''));
        bubble.append(avatar(player, 'avatar--sm'), name(player, 'game-emotes__name'), emoteIcon(event.emote));
        emoteLayer.append(bubble);
        setTimeout(() => bubble.remove(), 2600);
    };

    const emotePicker = element.querySelector('[data-emote-picker]');

    if (emotePicker) {
        const toggle = emotePicker.querySelector('[data-emote-toggle]');
        const menu = emotePicker.querySelector('.game-emote__menu');
        toggle.addEventListener('click', () => {
            menu.hidden = !menu.hidden;
            toggle.setAttribute('aria-expanded', String(!menu.hidden));
        });
        menu.querySelectorAll('[data-emote-key]').forEach((button) => button.addEventListener('click', () => {
            menu.hidden = true;
            toggle.setAttribute('aria-expanded', 'false');
            post(data.emote, { emote: button.dataset.emoteKey }).catch(() => {});
        }));
    }

    new EventSource(data.url).onmessage = (message) => {
        const next = JSON.parse(message.data);

        if (next.type === 'emote') {
            showEmote(next);

            return;
        }

        pending = pending.then(async () => {
            snapshot = next;
            await reveal();
            update();
        });
    };

    await sync();
    update();

    if (snapshot.mode === 'solo' && snapshot.status === 'waiting') {
        post(data.ready, {});
    }
    setInterval(tick, 250);
    setInterval(sync, 20000);
}
const node = (tag, className, text = '') => {
    const element = document.createElement(tag);
    element.className = className;
    element.textContent = text;

    return element;
};

const format = (text, values = {}) => text.replace(/\{(\w+)\}/g, (match, key) => String(values[key] ?? match));

const avatar = (player, modifier) => {
    const element = node('span', `avatar ${modifier}`, player.avatar ? '' : player.initials ?? '?');
    const look = player.look ?? {};

    if (player.avatar) {
        const image = node('img', 'avatar__image');
        image.src = player.avatar;
        image.alt = '';
        element.append(image);
    }

    if (look.frame) {
        element.classList.add('avatar--frame', ...`avatar--frame-${look.frame}`.split(' '));

        if (look.frame_style) {
            element.style.cssText += look.frame_style;
        }
    }

    if (look.plan?.path || look.plan?.svg) {
        const badge = node('span', 'plan-badge plan-badge--avatar');
        const icon = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        element.classList.add('avatar--plan');
        badge.style.setProperty('--plan-color', look.plan.color);
        badge.title = look.plan.label ?? '';
        icon.setAttribute('viewBox', '0 0 24 24');

        if (look.plan.svg) {
            Object.entries({ fill: 'none', stroke: 'currentColor', 'stroke-width': '2.4', 'stroke-linecap': 'round', 'stroke-linejoin': 'round', class: 'plan-icon plan-icon--line' }).forEach(([key, value]) => icon.setAttribute(key, value));
            icon.innerHTML = look.plan.svg;
        } else {
            const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
            icon.setAttribute('fill', 'currentColor');
            icon.setAttribute('class', 'plan-icon');
            path.setAttribute('d', look.plan.path);
            icon.append(path);
        }

        badge.append(icon);
        element.append(badge);
    }

    return element;
};

const name = (player, className, text = null) => {
    const label = node('span', `user-name ${className}`, text ?? player.username ?? '');

    if (player.look?.color) {
        label.style.setProperty('--name-color', player.look.color);
    }

    if (!player.look?.clan) {
        return label;
    }

    const wrap = node('span', `user-name-wrap ${className}`);
    const tag = node('span', 'user-clan', `[${player.look.clan.tag}]`);
    tag.style.setProperty('--clan-color', player.look.clan.color);
    label.className = 'user-name';
    wrap.append(tag, label);

    return wrap;
};

const title = (player, className = '') => (player.look?.title ? node('span', `user-title ${className}`, player.look.title) : null);

const post = (url, token, body = {}) => fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': token },
    body: JSON.stringify(body),
});

export default { node, format, avatar, name, title, post };

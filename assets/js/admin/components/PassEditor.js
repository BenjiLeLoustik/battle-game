const parse = (template, values, wrapper) => {
    let html = template.innerHTML;
    Object.entries(values).forEach(([key, value]) => {
        html = html.split(key).join(value);
    });
    const holder = document.createElement(wrapper);
    holder.innerHTML = html.trim();

    return holder.firstElementChild;
};

export default function PassEditor(form) {
    const trackTemplate = form.querySelector('[data-track-template]');
    const tierTemplate = form.querySelector('[data-tier-template]');
    const tracks = form.querySelector('[data-tracks]');
    const tiers = form.querySelector('[data-tiers]');
    const empty = form.querySelector('[data-tier-empty]');
    const count = form.querySelector('[data-tier-count]');
    const table = form.querySelector('[data-tier-table]');
    const footer = form.querySelector('[data-tier-footer]');
    let counter = 100000;

    const syncReward = (box) => {
        const type = box.querySelector('[data-reward-type]').value;
        const value = box.querySelector('[data-reward-value]');
        const product = box.querySelector('[data-reward-product]');
        const numeric = ['coins', 'lives'].includes(type);

        value.hidden = !numeric;
        value.disabled = !numeric;
        value.required = numeric;
        product.hidden = type !== 'product';
        product.disabled = type !== 'product';
        product.required = type === 'product';
    };

    const renumber = () => {
        if (!tiers) {
            return;
        }

        const rows = [...tiers.querySelectorAll('[data-tier]')];
        rows.forEach((row, index) => {
            row.querySelector('[data-tier-number]').textContent = String(index + 1);
        });

        if (empty) {
            empty.hidden = rows.length > 0;
        }

        if (table) {
            table.hidden = rows.length === 0;
        }

        if (footer) {
            footer.hidden = rows.length === 0;
        }

        if (count) {
            count.textContent = String(rows.length);
        }
    };

    form.querySelectorAll('[data-add-tier]').forEach((button) => button.addEventListener('click', () => {
        const tier = parse(tierTemplate, { __ROW__: String(counter += 1) }, 'tbody');
        const last = [...tiers.querySelectorAll('[data-tier-xp]')].pop();
        tier.querySelector('[data-tier-xp]').value = String((Number(last?.value) || 0) + 100);
        tiers.append(tier);
        renumber();
        tier.querySelectorAll('.pass-admin__reward').forEach(syncReward);
        tier.querySelector('[data-tier-xp]').focus();
    }));

    form.querySelector('[data-add-track]')?.addEventListener('click', () => {
        const row = parse(trackTemplate, { __INDEX__: String(counter += 1), __KEY__: `t${Date.now().toString(36)}` }, 'ul');
        tracks.append(row);
        row.querySelector('input[name$="[name]"]').focus();
    });

    form.addEventListener('click', (event) => {
        const removeTier = event.target.closest('[data-remove-tier]');
        const removeTrack = event.target.closest('[data-remove-track]');

        if (removeTier) {
            removeTier.closest('[data-tier]').remove();
            renumber();
        }

        if (removeTrack) {
            removeTrack.closest('[data-track-row]').remove();
        }
    });

    form.addEventListener('input', (event) => {
        if (event.target.matches('[data-track-color]')) {
            event.target.closest('[data-track-row]').style.setProperty('--track', event.target.value);
        }
    });

    form.addEventListener('change', (event) => {
        if (event.target.matches('[data-reward-type]')) {
            syncReward(event.target.closest('.pass-admin__reward'));
        }
    });

    form.querySelectorAll('.pass-admin__reward').forEach(syncReward);
    renumber();
}
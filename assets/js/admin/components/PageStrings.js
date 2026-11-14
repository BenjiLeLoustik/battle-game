import dom from 'shared/dom';

const { node } = dom;

const TOKEN = /\{([a-z][a-z0-9_]{0,39})\}/g;
const DELAY = 250;

export default function PageStrings(element, data) {
    const locales = JSON.parse(data.locales);
    const content = element.closest('form').elements.content;
    const rows = element.querySelector('[data-rows]');
    const table = rows.closest('table');
    const empty = element.querySelector('[data-empty]');
    const known = new Map([...rows.children].map((row) => [row.dataset.key, row]));
    let timer = null;

    const fit = (input) => {
        input.style.height = 'auto';
        input.style.height = `${input.scrollHeight + 2}px`;
    };

    const row = (key) => {
        const tr = node('tr', 'admin-table__row');
        tr.dataset.key = key;
        tr.append(node('td', 'admin-table__cell admin-table__cell--mono page-strings__key', `{${key}}`));

        locales.forEach((locale) => {
            const input = node('textarea', 'admin-field__input page-strings__input');
            input.name = `strings[${key}][${locale}]`;
            input.rows = 1;
            input.setAttribute('aria-label', `${key} · ${locale.toUpperCase()}`);
            const cell = node('td', 'admin-table__cell');
            cell.append(input);
            tr.append(cell);
        });

        return tr;
    };

    const sync = () => {
        const keys = [...new Set([...content.value.matchAll(TOKEN)].map((match) => match[1]))];

        keys.forEach((key) => {
            if (!known.has(key)) {
                known.set(key, row(key));
            }
        });

        rows.replaceChildren(...keys.map((key) => known.get(key)));
        table.hidden = keys.length === 0;
        empty.hidden = keys.length > 0;
    };

    content.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(sync, DELAY);
    });

    rows.addEventListener('input', (event) => fit(event.target));
    rows.querySelectorAll('textarea').forEach(fit);
}

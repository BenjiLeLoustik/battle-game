const ICONS = { text: 'T', counter: '#', button: '▭', grid: '▦', input: '⌨', timer: '◷', scores: '☰', message: '💬', choices: '☑', image: '🖼', icon: '★', divider: '—', box: '▢', custom: '</>' };
const DEFAULTS = {
    text: { text: 'Nouveau texte', variant: 'normal' },
    counter: { text: 'Score', variable: 'score' },
    button: { text: 'Bouton' },
    grid: { columns: 3 },
    input: { text: 'Valider' },
    timer: {},
    scores: {},
    message: {},
    choices: { options: ['Choix A', 'Choix B', 'Choix C', 'Choix D'], columns: 2 },
    image: { src: '', text: '' },
    icon: { text: '★' },
    divider: {},
    box: { text: '' },
    custom: { html: '<button type="button" data-click>Mon bloc</button>', css: '', script: '' },
};
const TEXTUAL = ['text', 'button', 'counter', 'input', 'box', 'image', 'icon'];
const SIZED = ['text', 'button', 'image', 'icon'];

const copy = (value) => JSON.parse(JSON.stringify(value));

export default function StudioBuilder(element, data) {
    const find = (selector) => element.querySelector(selector);
    const labels = JSON.parse(data.labels);
    const schema = JSON.parse(data.schema);
    const templates = JSON.parse(data.templates);
    const ui = {
        palette: find('.studio-builder__palette'),
        outline: find('.studio-builder__outline'),
        screen: find('.studio-builder__screen'),
        pane: find('.studio-builder__pane'),
        tabs: [...element.querySelectorAll('[data-tab]')],
        state: find('.studio-builder__state'),
        chooser: find('.studio-builder__chooser[hidden]'),
        save: find('[data-action="save"]'),
        generate: find('[data-action="generate"]'),
        publish: find('[data-action="publish"]'),
        library: find('.studio-builder__library'),
    };
    let library = JSON.parse(data.library || '[]');

    let bp = data.blueprint ? JSON.parse(data.blueprint) : null;
    let selected = null;
    let tab = 'element';
    let dirty = false;

    const node = (tag, className = '', text = '') => {
        const item = document.createElement(tag);

        if (className) {
            item.className = className;
        }

        if (text !== '') {
            item.textContent = text;
        }

        return item;
    };

    const status = (text, tone = '') => {
        ui.state.textContent = text;
        ui.state.dataset.tone = tone;
    };

    const changed = (full = true) => {
        dirty = true;
        status(labels.status.unsaved, 'warn');

        if (full) {
            render();
        } else {
            renderScreen();
            renderOutline();
        }
    };

    const nextId = (prefix, list) => `${prefix}${list.reduce((max, item) => Math.max(max, Number(String(item.id).slice(1)) || 0), 0) + 1}`;
    const variablesOf = (filter) => bp.variables.filter(filter);
    const elementOf = (id) => bp.elements.find((item) => item.id === id);
    const boards = () => variablesOf((variable) => variable.type === 'board');

    const choice = (options, value, onChange, className = 'studio-builder__select') => {
        const select = node('select', className);
        options.forEach(([optionValue, text]) => select.append(new Option(text, optionValue, false, String(optionValue) === String(value ?? ''))));
        select.addEventListener('change', () => onChange(select.value));

        return select;
    };

    const input = (value, onInput, { type = 'text', className = 'studio-builder__input', placeholder = '', full = false } = {}) => {
        const field = node('input', className);
        field.type = type;
        field.value = value ?? '';
        field.placeholder = placeholder;
        field.addEventListener(full ? 'change' : 'input', () => onInput(field.value));

        return field;
    };

    const checkbox = (checked, text, onChange) => {
        const label = node('label', 'studio-builder__check');
        const box = node('input');
        box.type = 'checkbox';
        box.checked = Boolean(checked);
        box.addEventListener('change', () => onChange(box.checked));
        label.append(box, document.createTextNode(` ${text}`));

        return label;
    };

    const field = (text, control) => {
        const label = node('label', 'studio-builder__field');
        label.append(node('span', 'studio-builder__label', text), control);

        return label;
    };

    const button = (text, onClick, className = 'studio-builder__link') => {
        const item = node('button', className, text);
        item.type = 'button';
        item.addEventListener('click', onClick);

        return item;
    };

    const rename = (from, to) => {
        const walk = (value) => {
            if (Array.isArray(value)) {
                value.forEach(walk);
            } else if (value && typeof value === 'object') {
                if (['var', 'my'].includes(value.kind) && value.value === from) {
                    value.value = to;
                }

                ['board', 'variable'].forEach((key) => {
                    if (value[key] === from) {
                        value[key] = to;
                    }
                });
                Object.values(value).forEach(walk);
            }
        };

        walk(bp.elements);
        walk(bp.rules);
    };

    const addElement = (type) => {
        const item = { id: nextId('e', bp.elements), type, variant: 'normal', inline: false, ...copy(DEFAULTS[type]) };

        if (type === 'grid') {
            let board = boards()[0];

            if (!board) {
                board = { key: 'plateau', scope: 'game', type: 'board', initial: '', size: 9, reset: false, hidden: false };
                bp.variables.push(board);
            }

            item.variable = board.key;
        }

        bp.elements.push(item);
        selected = item.id;
        tab = 'element';
        changed();
    };

    const renderPalette = () => {
        ui.palette.replaceChildren(...schema.elements.map((type) => {
            const item = button(`${ICONS[type]}  ${labels.elements[type]}`, () => addElement(type), 'studio-builder__block');
            item.dataset.type = type;

            return item;
        }));
    };

    const accepts = (when) => schema.eventElements[when] ?? null;

    const walk = (value, visit) => {
        if (Array.isArray(value)) {
            value.forEach((item) => walk(item, visit));
        } else if (value && typeof value === 'object') {
            visit(value);
            Object.values(value).forEach((item) => walk(item, visit));
        }
    };

    const collect = (id) => {
        const ids = new Set([id, ...bp.elements.filter((item) => item.parent === id).map((item) => item.id)]);
        const elements = bp.elements.filter((item) => ids.has(item.id));
        const rules = bp.rules.filter((rule) => ids.has(rule.element));
        const keys = new Set();
        walk([elements, rules], (value) => {
            if (['var', 'my'].includes(value.kind)) {
                keys.add(value.value);
            }

            ['board', 'variable'].forEach((key) => typeof value[key] === 'string' && keys.add(value[key]));
        });

        return { elements: copy(elements), rules: copy(rules), variables: copy(bp.variables.filter((variable) => keys.has(variable.key))) };
    };

    const insert = (block) => {
        const definition = copy(block.definition);
        const keys = {};

        definition.variables.forEach((variable) => {
            const existing = bp.variables.find((other) => other.key === variable.key);

            if (existing && existing.type === variable.type && existing.scope === variable.scope) {
                return;
            }

            let key = variable.key;
            let count = 2;

            while (bp.variables.some((other) => other.key === key)) {
                key = `${variable.key}${count}`.slice(0, 21);
                count += 1;
            }

            keys[variable.key] = key;
            bp.variables.push({ ...variable, key });
        });

        const ids = {};
        definition.elements.forEach((item) => {
            ids[item.id] = nextId('e', bp.elements.concat(Object.values(ids).map((id) => ({ id }))));
        });

        walk([definition.elements, definition.rules], (value) => {
            if (['var', 'my'].includes(value.kind) && keys[value.value]) {
                value.value = keys[value.value];
            }

            ['board', 'variable'].forEach((key) => {
                if (typeof value[key] === 'string' && keys[value[key]]) {
                    value[key] = keys[value[key]];
                }
            });
        });

        definition.elements.forEach((item) => bp.elements.push({ ...item, id: ids[item.id], parent: ids[item.parent] ?? '' }));
        definition.rules.forEach((rule) => bp.rules.push({ ...rule, id: nextId('r', bp.rules), element: ids[rule.element] ?? rule.element }));
        selected = ids[definition.elements[0]?.id] ?? selected;
        tab = 'element';
        changed();
        status(labels.library.inserted.replace('{name}', block.name), 'ok');
    };

    const renderLibrary = () => {
        if (!ui.library) {
            return;
        }

        ui.library.replaceChildren(...library.map((block) => {
            const row = node('div', 'studio-builder__saved');
            const add = button(`＋ ${block.name}`, () => bp && insert(block), 'studio-builder__saved-name');
            add.title = block.author ? labels.library.by.replace('{name}', block.author) : '';
            row.append(add);

            if (String(block.authorId) === data.me) {
                const remove = button('×', async () => {
                    if (!window.confirm(labels.library.delete_confirm.replace('{name}', block.name))) {
                        return;
                    }

                    try {
                        await request(data.blockDelete.replace(/\/0\/delete$/, `/${block.id}/delete`), {});
                        library = library.filter((other) => other.id !== block.id);
                        renderLibrary();
                    } catch (error) {
                        status(error.message, 'error');
                    }
                }, 'studio-builder__mini');
                remove.setAttribute('aria-label', labels.library.delete);
                row.append(remove);
            }

            return row;
        }));

        if (!library.length) {
            ui.library.append(node('p', 'studio-builder__muted', labels.library.empty));
        }
    };

    const saveBlock = async (item) => {
        const name = window.prompt(labels.library.prompt, item.text || labels.elements[item.type]);

        if (!name || !name.trim()) {
            return;
        }

        try {
            const result = await request(data.blockSave, { name: name.trim(), definition: collect(item.id) });
            library = [...library, result.block].sort((a, b) => a.name.localeCompare(b.name));
            renderLibrary();
            status(labels.library.saved.replace('{name}', result.block.name), 'ok');
        } catch (error) {
            status(error.message, 'error');
        }
    };

    const renderOutline = () => {
        ui.outline.replaceChildren(...bp.elements.map((item, index) => {
            const row = node('li', 'studio-builder__item' + (item.id === selected ? ' studio-builder__item--active' : ''));
            const open = button(`${item.parent ? '↳ ' : ''}${ICONS[item.type]}  ${labels.elements[item.type]}${item.text ? ` · ${item.text}` : ''}`, () => {
                selected = item.id;
                tab = 'element';
                render();
            }, 'studio-builder__item-name');
            const move = (step) => {
                const target = index + step;

                if (target >= 0 && target < bp.elements.length) {
                    [bp.elements[index], bp.elements[target]] = [bp.elements[target], bp.elements[index]];
                    changed();
                }
            };
            const up = button('↑', () => move(-1), 'studio-builder__mini');
            const down = button('↓', () => move(1), 'studio-builder__mini');
            up.setAttribute('aria-label', labels.fields.up);
            down.setAttribute('aria-label', labels.fields.down);
            row.append(open, up, down);

            return row;
        }));

        if (!bp.elements.length) {
            ui.outline.append(node('li', 'studio-builder__muted', labels.empty_screen));
        }
    };

    const mock = (item) => {
        const box = node('div', `studio-builder__mock studio-builder__mock--${item.type} studio-builder__mock--${item.variant ?? 'normal'}` + (item.id === selected ? ' studio-builder__mock--active' : ''));
        box.dataset.id = item.id;

        if (item.type === 'text') {
            box.textContent = item.text || labels.elements.text;
        } else if (item.type === 'button') {
            box.append(node('span', 'studio-builder__mock-button', item.text || labels.elements.button));
        } else if (item.type === 'counter') {
            box.append(node('small', '', item.text), node('strong', '', item.variable === 'score' ? '0' : `{${item.variable}}`));
        } else if (item.type === 'grid') {
            const size = boards().find((board) => board.key === item.variable)?.size ?? 9;
            box.style.setProperty('--columns', item.columns ?? 3);
            box.append(...Array.from({ length: size }, () => node('span', 'studio-builder__mock-cell')));
        } else if (item.type === 'input') {
            box.append(node('span', 'studio-builder__mock-field'), node('span', 'studio-builder__mock-button', item.text || 'OK'));
        } else if (item.type === 'timer') {
            box.append(node('span', 'studio-builder__mock-fill'));
        } else if (item.type === 'scores') {
            box.append(node('span', 'studio-builder__mock-chip', 'Joueur 1 · 0'), node('span', 'studio-builder__mock-chip', 'Joueur 2 · 0'));
        } else if (item.type === 'choices') {
            box.style.setProperty('--columns', item.columns ?? 2);
            box.append(...(item.options ?? []).map((option) => node('span', 'studio-builder__mock-choice', option)));
        } else if (item.type === 'image') {
            if (item.src) {
                const image = node('img', 'studio-builder__mock-image');
                image.src = item.src;
                image.alt = item.text ?? '';
                box.append(image);
            } else {
                box.append(node('span', 'studio-builder__mock-placeholder', `🖼 ${labels.elements.image}`));
            }
        } else if (item.type === 'icon') {
            box.textContent = item.text || '★';
        } else if (item.type === 'divider') {
            box.append(node('hr', 'studio-builder__mock-divider'));
        } else if (item.type === 'box') {
            if (item.text) {
                box.append(node('strong', 'studio-builder__mock-title', item.text));
            }

            const children = rowsOf(item.id);
            box.append(...children.map(line));

            if (!children.length) {
                box.append(node('span', 'studio-builder__mock-placeholder', labels.fields.parent));
            }
        } else if (item.type === 'custom') {
            box.append(node('span', 'studio-builder__mock-placeholder', `</> ${labels.elements.custom}`));
        } else {
            box.textContent = labels.elements.message;
        }

        box.addEventListener('click', (event) => {
            event.stopPropagation();
            selected = item.id;
            tab = 'element';
            render();
        });

        return box;
    };

    const rowsOf = (parent) => {
        const boxes = new Set(bp.elements.filter((item) => item.type === 'box').map((item) => item.id));
        const rows = [];

        bp.elements.filter((item) => (boxes.has(item.parent) && item.type !== 'box' ? item.parent : '') === parent).forEach((item) => {
            if (item.inline && rows.length) {
                rows.at(-1).push(item);
            } else {
                rows.push([item]);
            }
        });

        return rows;
    };

    const line = (row) => {
        const wrap = node('div', 'studio-builder__row');
        wrap.append(...row.map(mock));

        return wrap;
    };

    const renderScreen = () => {
        const rows = rowsOf('');
        ui.screen.replaceChildren(...rows.map(line));

        if (!rows.length) {
            ui.screen.append(node('p', 'studio-builder__muted', labels.empty_screen));
        }
    };

    const placeholders = (target, item) => {
        const wrap = node('div', 'studio-builder__chips');
        const tokens = ['{round}', '{rounds}', '{score}', '{player}', '{name}', ...bp.variables.filter((variable) => variable.type !== 'board').map((variable) => (variable.scope === 'player' ? `{my.${variable.key}}` : `{var.${variable.key}}`))];
        wrap.append(node('span', 'studio-builder__label', labels.fields.placeholders));
        tokens.forEach((token) => wrap.append(button(token, () => {
            const start = target.selectionStart ?? target.value.length;
            target.value = target.value.slice(0, start) + token + target.value.slice(target.selectionEnd ?? start);
            item.text = target.value;
            target.focus();
            changed(false);
        }, 'studio-builder__chip')));

        return wrap;
    };

    const elementPane = () => {
        const item = elementOf(selected);

        if (!item) {
            return [node('p', 'studio-builder__muted', labels.fields.select_block)];
        }

        const parts = [node('h3', 'studio-builder__pane-title', `${ICONS[item.type]} ${labels.elements[item.type]} · ${item.id}`)];

        if (TEXTUAL.includes(item.type)) {
            const text = item.type === 'text' ? node('textarea', 'studio-builder__input studio-builder__textarea') : node('input', 'studio-builder__input');
            text.value = item.text ?? '';
            text.maxLength = 300;
            text.addEventListener('input', () => {
                item.text = text.value;
                changed(false);
            });
            parts.push(field(labels.fields[item.type === 'text' ? 'text' : `text_${item.type}`], text));

            if (!['image', 'icon'].includes(item.type)) {
                parts.push(placeholders(text, item));
            }
        }

        if (item.type === 'choices') {
            const options = node('textarea', 'studio-builder__input studio-builder__textarea');
            options.value = (item.options ?? []).join('\n');
            options.addEventListener('input', () => {
                item.options = options.value.split('\n').map((option) => option.trim()).filter(Boolean).slice(0, 8);
                changed(false);
            });
            parts.push(
                field(labels.fields.options, options),
                node('p', 'studio-builder__muted', labels.fields.options_help),
                field(labels.fields.columns, input(item.columns ?? 2, (value) => {
                    item.columns = Math.max(1, Math.min(4, Number(value) || 2));
                    changed(false);
                }, { type: 'number' })),
            );
        }

        if (item.type === 'image') {
            parts.push(field(labels.fields.src, input(item.src, (value) => {
                item.src = value.trim();
                changed(false);
            }, { full: true, placeholder: 'https://…' })));
        }

        if (item.type === 'custom') {
            [['html', 10], ['css', 6], ['script', 8]].forEach(([key, rows]) => {
                const code = node('textarea', 'studio-builder__input studio-builder__code');
                code.rows = rows;
                code.spellcheck = false;
                code.value = item[key] ?? '';
                code.addEventListener('input', () => {
                    item[key] = code.value;
                    dirty = true;
                    status(labels.status.unsaved, 'warn');
                });
                parts.push(field(labels.fields[key], code), node('p', 'studio-builder__muted', labels.fields[`${key}_help`].replace('{id}', item.id)));
            });
        }

        if (item.type !== 'box' && bp.elements.some((other) => other.type === 'box')) {
            parts.push(field(labels.fields.parent, choice([['', labels.fields.none], ...bp.elements.filter((other) => other.type === 'box').map((other) => [other.id, `${labels.elements.box} « ${other.text || other.id} »`])], item.parent ?? '', (value) => {
                item.parent = value;
                changed();
            })));
        }

        if (SIZED.includes(item.type)) {
            parts.push(field(labels.fields.variant, choice(schema.variants.map((variant) => [variant, labels.variants[variant]]), item.variant, (value) => {
                item.variant = value;
                changed();
            })));
        }

        if (item.type === 'counter') {
            parts.push(field(labels.fields.variable, choice([['score', labels.operands.score], ...variablesOf((variable) => variable.type !== 'board').map((variable) => [variable.key, `${variable.key} (${labels.variables.scopes[variable.scope]})`])], item.variable, (value) => {
                item.variable = value;
                changed();
            })));
        }

        if (item.type === 'grid') {
            parts.push(
                field(labels.fields.board, choice(boards().map((board) => [board.key, `${board.key} · ${board.size}`]), item.variable, (value) => {
                    item.variable = value;
                    changed();
                })),
                field(labels.fields.columns, input(item.columns ?? 3, (value) => {
                    item.columns = Math.max(1, Math.min(12, Number(value) || 3));
                    changed(false);
                }, { type: 'number' })),
            );
        }

        if (['timer', 'scores', 'message'].includes(item.type)) {
            parts.push(node('p', 'studio-builder__muted', labels.fields.no_options));
        }

        parts.push(checkbox(item.inline, labels.fields.inline, (value) => {
            item.inline = value;
            changed();
        }));
        const actions = node('div', 'studio-builder__element-actions');
        actions.append(
            button(`☆ ${labels.fields.save_block}`, () => saveBlock(item), 'admin-btn admin-btn--secondary admin-btn--small'),
            button(labels.fields.delete, () => {
                bp.elements = bp.elements.filter((other) => other.id !== item.id).map((other) => (other.parent === item.id ? { ...other, parent: '' } : other));
                selected = null;
                changed();
            }, 'admin-btn admin-btn--danger admin-btn--small'),
        );
        parts.push(actions);

        return parts;
    };

    const operandKinds = (when, allowed = schema.operands) => allowed.filter((kind) => !((kind === 'answer' && when !== 'answer') || (['cell', 'cellvalue'].includes(kind) && when !== 'cell')));

    const operand = (value, when, onChange, kinds = null, source = labels.operands) => {
        const wrap = node('span', 'studio-builder__operand');
        const current = value ?? { kind: 'number', value: '0' };
        const list = operandKinds(when, kinds ?? schema.operands);
        const update = (patch) => onChange({ ...current, ...patch });
        wrap.append(choice(list.map((kind) => [kind, source[kind] ?? labels.operands[kind]]), current.kind, (kind) => {
            const preset = { number: { value: '0' }, text: { value: '' }, var: { value: variablesOf((variable) => variable.scope === 'game' && variable.type !== 'board')[0]?.key ?? '' }, my: { value: variablesOf((variable) => variable.scope === 'player')[0]?.key ?? '' }, random: { min: 1, max: 10 } }[kind] ?? {};
            onChange({ kind, ...preset });
        }));

        if (current.kind === 'number') {
            wrap.append(input(current.value, (text) => update({ value: text }), { type: 'number', className: 'studio-builder__input studio-builder__input--small', full: true }));
        } else if (current.kind === 'text') {
            wrap.append(input(current.value, (text) => update({ value: text }), { className: 'studio-builder__input studio-builder__input--small', full: true }));
        } else if (current.kind === 'var' || current.kind === 'my') {
            const options = variablesOf((variable) => variable.type !== 'board' && variable.scope === (current.kind === 'var' ? 'game' : 'player')).map((variable) => [variable.key, variable.key]);
            wrap.append(options.length ? choice(options, current.value, (key) => update({ value: key })) : node('em', 'studio-builder__warn', labels.variables.add));
        } else if (current.kind === 'random') {
            wrap.append(
                node('span', 'studio-builder__word', labels.words.between),
                input(current.min ?? 1, (text) => update({ min: Number(text) }), { type: 'number', className: 'studio-builder__input studio-builder__input--tiny', full: true }),
                node('span', 'studio-builder__word', labels.words.and),
                input(current.max ?? 10, (text) => update({ max: Number(text) }), { type: 'number', className: 'studio-builder__input studio-builder__input--tiny', full: true }),
            );
        }

        return wrap;
    };

    const boardChoice = (value, onChange) => {
        const list = boards();

        return list.length ? choice(list.map((board) => [board.key, board.key]), value ?? list[0].key, onChange) : node('em', 'studio-builder__warn', labels.variables.add);
    };

    const conditionRow = (rule, condition, index) => {
        const row = node('div', 'studio-builder__step');
        const head = node('div', 'studio-builder__line');
        const params = node('div', 'studio-builder__params');
        const replace = (next) => {
            rule.if[index] = next;
            changed();
        };
        head.append(choice(schema.checks.filter((check) => check !== 'empty' || rule.when === 'cell').map((check) => [check, labels.checks[check]]), condition.check, (check) => {
            replace({ compare: { check, left: { kind: 'score' }, op: '>=', right: { kind: 'number', value: '10' } }, empty: { check }, line: { check, board: boards()[0]?.key ?? '', length: 3 }, full: { check, board: boards()[0]?.key ?? '' } }[check]);
        }));

        if (condition.check === 'compare') {
            params.append(
                operand(condition.left, rule.when, (value) => replace({ ...condition, left: value })),
                choice(schema.ops.map((op) => [op, labels.ops[op]]), condition.op, (op) => replace({ ...condition, op })),
                operand(condition.right, rule.when, (value) => replace({ ...condition, right: value })),
            );
        } else if (condition.check === 'line') {
            params.append(
                input(condition.length ?? 3, (value) => replace({ ...condition, length: Number(value) }), { type: 'number', className: 'studio-builder__input studio-builder__input--tiny', full: true }),
                node('span', 'studio-builder__word', labels.line_length),
                boardChoice(condition.board, (board) => replace({ ...condition, board })),
            );
        } else if (condition.check === 'full') {
            params.append(boardChoice(condition.board, (board) => replace({ ...condition, board })));
        }

        head.append(button('×', () => {
            rule.if.splice(index, 1);
            changed();
        }, 'studio-builder__remove'));

        row.append(head);

        if (params.children.length) {
            row.append(params);
        }

        return row;
    };

    const actionRow = (rule, action, index) => {
        const row = node('div', 'studio-builder__step');
        const head = node('div', 'studio-builder__line');
        const params = node('div', 'studio-builder__params');
        const replace = (next) => {
            rule.then[index] = next;
            changed();
        };
        head.append(choice(schema.actions.filter((name) => name !== 'set_cell' || rule.when === 'cell').map((name) => [name, labels.actions[name]]), action.do, (name) => {
            replace({
                set: { do: name, target: { kind: 'score' }, value: { kind: 'number', value: '0' } },
                add: { do: name, target: { kind: 'score' }, value: { kind: 'number', value: '1' } },
                random: { do: name, target: { kind: variablesOf((variable) => variable.scope === 'game' && variable.type === 'number')[0] ? 'var' : 'score', value: variablesOf((variable) => variable.scope === 'game' && variable.type === 'number')[0]?.key }, min: 1, max: 10 },
                set_cell: { do: name, board: boards()[0]?.key ?? '', value: { kind: 'mark' } },
                clear_board: { do: name, board: boards()[0]?.key ?? '' },
                message: { do: name, text: 'Bravo !', to: 'me' },
                sound: { do: name, sound: 'gain' },
            }[name] ?? { do: name });
        }));

        if (['set', 'add', 'random'].includes(action.do)) {
            params.append(operand(action.target, rule.when, (value) => replace({ ...action, target: value }), schema.targets, labels.targets));
        }

        if (['set', 'add'].includes(action.do)) {
            params.append(node('span', 'studio-builder__word', action.do === 'add' ? labels.words.value : labels.words.to), operand(action.value, rule.when, (value) => replace({ ...action, value })));
        }

        if (action.do === 'random') {
            params.append(
                node('span', 'studio-builder__word', labels.words.between),
                input(action.min ?? 1, (value) => replace({ ...action, min: Number(value) }), { type: 'number', className: 'studio-builder__input studio-builder__input--tiny', full: true }),
                node('span', 'studio-builder__word', labels.words.and),
                input(action.max ?? 10, (value) => replace({ ...action, max: Number(value) }), { type: 'number', className: 'studio-builder__input studio-builder__input--tiny', full: true }),
            );
        }

        if (action.do === 'set_cell') {
            params.append(node('span', 'studio-builder__word', labels.words.board), boardChoice(action.board, (board) => replace({ ...action, board })), node('span', 'studio-builder__word', labels.words.value), operand(action.value, rule.when, (value) => replace({ ...action, value })));
        }

        if (action.do === 'clear_board') {
            params.append(boardChoice(action.board, (board) => replace({ ...action, board })));
        }

        if (action.do === 'message') {
            params.append(
                input(action.text, (value) => {
                    action.text = value;
                    dirty = true;
                    status(labels.status.unsaved, 'warn');
                }, { className: 'studio-builder__input studio-builder__input--grow' }),
                node('span', 'studio-builder__word', labels.words.for),
                choice([['me', labels.words.me], ['all', labels.words.all]], action.to, (to) => replace({ ...action, to })),
            );
        }

        if (action.do === 'sound') {
            params.append(choice(schema.sounds.map((sound) => [sound, labels.sounds[sound]]), action.sound, (sound) => replace({ ...action, sound })));
        }

        head.append(button('×', () => {
            rule.then.splice(index, 1);
            changed();
        }, 'studio-builder__remove'));

        row.append(head);

        if (params.children.length) {
            row.append(params);
        }

        return row;
    };

    const rulesPane = () => {
        const parts = [node('p', 'studio-builder__muted', labels.rules.help)];

        bp.rules.forEach((rule, index) => {
            const card = node('section', 'studio-builder__rule');
            const head = node('div', 'studio-builder__line studio-builder__line--head');
            const elementType = accepts(rule.when);
            const title = node('div', 'studio-builder__rule-head');
            title.append(node('strong', 'studio-builder__rule-title', labels.rules.number.replace('{number}', index + 1)), button(`🗑 ${labels.rules.delete_rule}`, () => {
                if (window.confirm(labels.rules.delete_confirm.replace('{number}', index + 1))) {
                    bp.rules.splice(index, 1);
                    changed();
                }
            }, 'studio-builder__delete'));
            head.append(node('strong', 'studio-builder__keyword', labels.rules.when), choice(schema.events.map((event) => [event, labels.events[event]]), rule.when, (when) => {
                rule.when = when;
                const types = accepts(when);
                rule.element = types ? bp.elements.find((item) => types.includes(item.type))?.id ?? '' : '';
                changed();
            }));

            if (elementType) {
                const options = bp.elements.filter((item) => elementType.includes(item.type)).map((item) => [item.id, `${labels.elements[item.type]} « ${item.text || item.id} »`]);
                head.append(options.length ? choice(options, rule.element, (value) => {
                    rule.element = value;
                    changed();
                }) : node('em', 'studio-builder__warn', `+ ${elementType.map((type) => labels.elements[type]).join(' / ')}`));
            }

            const conditions = node('div', 'studio-builder__group');
            conditions.append(node('strong', 'studio-builder__keyword', labels.rules.if));

            if (!rule.if.length) {
                conditions.append(node('span', 'studio-builder__muted', labels.rules.always));
            }

            rule.if.forEach((condition, position) => conditions.append(conditionRow(rule, condition, position)));
            conditions.append(button(`+ ${labels.rules.add_condition}`, () => {
                rule.if.push({ check: 'compare', left: { kind: 'score' }, op: '>=', right: { kind: 'number', value: '10' } });
                changed();
            }));

            const actions = node('div', 'studio-builder__group');
            actions.append(node('strong', 'studio-builder__keyword', labels.rules.then));
            rule.then.forEach((action, position) => actions.append(actionRow(rule, action, position)));
            actions.append(button(`+ ${labels.rules.add_action}`, () => {
                rule.then.push({ do: 'add', target: { kind: 'score' }, value: { kind: 'number', value: '1' } });
                changed();
            }));

            card.append(title, head, conditions, actions);
            parts.push(card);
        });

        if (!bp.rules.length) {
            parts.push(node('p', 'studio-builder__muted', labels.rules.empty));
        }

        parts.push(button(`+ ${labels.rules.add}`, () => {
            const target = bp.elements.find((item) => item.type === 'button');
            bp.rules.push({ id: nextId('r', bp.rules), when: target ? 'click' : 'round_start', element: target?.id ?? '', if: [], then: [{ do: 'add', target: { kind: 'score' }, value: { kind: 'number', value: '1' } }] });
            changed();
        }, 'studio-builder__add'));

        return parts;
    };

    const variablesPane = () => {
        const parts = [node('p', 'studio-builder__muted', labels.variables.help)];

        bp.variables.forEach((variable, index) => {
            const card = node('section', 'studio-builder__variable');
            const key = input(variable.key, (value) => {
                const next = value.toLowerCase().normalize('NFD').replace(/[^a-z0-9_]/g, '').replace(/^[^a-z]+/, '').slice(0, 21);

                if (next && next !== variable.key && !bp.variables.some((other) => other.key === next)) {
                    rename(variable.key, next);
                    variable.key = next;
                }

                changed();
            }, { full: true });
            card.append(
                field(labels.variables.key, key),
                field(labels.variables.type, choice(['number', 'text', 'board'].map((type) => [type, labels.variables.types[type]]), variable.type, (type) => {
                    variable.type = type;
                    variable.scope = type === 'board' ? 'game' : variable.scope;
                    changed();
                })),
            );

            if (variable.type !== 'board') {
                card.append(
                    field(labels.variables.scope, choice(['game', 'player'].map((scope) => [scope, labels.variables.scopes[scope]]), variable.scope, (scope) => {
                        variable.scope = scope;
                        changed();
                    })),
                    field(labels.variables.initial, input(variable.initial, (value) => {
                        variable.initial = value;
                        dirty = true;
                    }, { type: variable.type === 'number' ? 'number' : 'text' })),
                    checkbox(variable.hidden, labels.variables.hidden, (value) => {
                        variable.hidden = value;
                        changed();
                    }),
                );
            } else {
                card.append(field(labels.variables.size, input(variable.size ?? 9, (value) => {
                    variable.size = Math.max(4, Math.min(100, Number(value) || 9));
                    changed(false);
                }, { type: 'number' })));
            }

            card.append(
                checkbox(variable.reset, labels.variables.reset, (value) => {
                    variable.reset = value;
                    changed();
                }),
                button(labels.variables.delete, () => {
                    bp.variables.splice(index, 1);
                    changed();
                }, 'studio-builder__link studio-builder__link--danger'),
            );
            parts.push(card);
        });

        if (!bp.variables.length) {
            parts.push(node('p', 'studio-builder__muted', labels.variables.empty));
        }

        parts.push(button(`+ ${labels.variables.add}`, () => {
            let count = bp.variables.length + 1;

            while (bp.variables.some((variable) => variable.key === `valeur${count}`)) {
                count += 1;
            }

            bp.variables.push({ key: `valeur${count}`, scope: 'game', type: 'number', initial: '0', size: 9, reset: false, hidden: false });
            changed();
        }, 'studio-builder__add'));

        return parts;
    };

    const settingsPane = () => [
        field(labels.settings.mode, choice(['simultaneous', 'turns'].map((mode) => [mode, labels.settings.modes[mode]]), bp.mode, (mode) => {
            bp.mode = mode;
            changed();
        })),
        field(labels.settings.rounds, input(bp.rounds, (value) => {
            bp.rounds = Math.max(1, Math.min(50, Number(value) || 1));
            dirty = true;
        }, { type: 'number' })),
        field(labels.settings.time, input(bp.time, (value) => {
            bp.time = Math.max(3, Math.min(600, Number(value) || 20));
            dirty = true;
        }, { type: 'number' })),
        field(labels.settings.reveal, input(bp.reveal, (value) => {
            bp.reveal = Math.max(0, Math.min(15, Number(value) || 0));
            dirty = true;
        }, { type: 'number' })),
        node('p', 'studio-builder__muted', labels.settings.turns_help),
    ];

    const renderPane = () => {
        const scroll = ui.pane.scrollTop;
        ui.tabs.forEach((item) => item.setAttribute('aria-selected', String(item.dataset.tab === tab)));
        ui.pane.replaceChildren(...{ element: elementPane, rules: rulesPane, variables: variablesPane, settings: settingsPane }[tab]());
        ui.pane.scrollTop = scroll;
    };

    const render = () => {
        if (!bp) {
            return;
        }

        renderOutline();
        renderScreen();
        renderPane();
    };

    const request = async (url, body) => {
        const response = await fetch(url, {
            method: 'POST',
            headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': data.token },
            body: JSON.stringify(body),
        });
        const json = await response.json().catch(() => ({}));

        if (!response.ok) {
            throw new Error(json.message ?? labels.status.error);
        }

        return json;
    };

    const accept = (next) => {
        const lost = bp.rules.length !== next.rules.length || bp.elements.length !== next.elements.length || bp.variables.length !== next.variables.length;
        bp = next;

        if (selected && !elementOf(selected)) {
            selected = null;
        }

        dirty = false;
        render();

        return lost;
    };

    const busy = (on) => [ui.save, ui.generate, ui.publish].forEach((item) => {
        if (item) {
            item.disabled = on;
        }
    });

    const save = async () => {
        busy(true);
        status(labels.status.saving);

        try {
            const lost = accept((await request(data.save, { blueprint: bp })).blueprint);
            status(lost ? labels.status.dropped : labels.status.saved, lost ? 'warn' : 'ok');
        } catch (error) {
            status(error.message, 'error');
        } finally {
            busy(false);
        }
    };

    const generate = async (publish) => {
        busy(true);
        status(labels.status.generating);

        try {
            const result = await request(data.generate, { blueprint: bp, publish });
            const lost = accept(result.blueprint);

            if (result.error) {
                status(`${labels.status.invalid} ${result.error}`, 'error');
            } else if (result.version) {
                status(labels.status.published.replace('{version}', result.version), 'ok');
            } else {
                status(lost ? labels.status.dropped : labels.status.generated.replace('{count}', result.files.length), lost ? 'warn' : 'ok');
            }
        } catch (error) {
            status(error.message, 'error');
        } finally {
            busy(false);
        }
    };

    ui.tabs.forEach((item) => item.addEventListener('click', () => {
        tab = item.dataset.tab;
        renderPane();
    }));

    if (data.locked === '1') {
        return;
    }

    ui.save.addEventListener('click', save);
    ui.generate.addEventListener('click', () => generate(false));
    ui.publish?.addEventListener('click', () => generate(true));

    ui.chooser.querySelectorAll('[data-template]').forEach((item) => item.addEventListener('click', () => {
        bp = copy(templates[item.dataset.template]);
        ui.chooser.hidden = true;
        selected = bp.elements[0]?.id ?? null;
        changed();
    }));

    window.addEventListener('keydown', (event) => {
        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 's' && bp) {
            event.preventDefault();
            save();
        }
    });

    window.addEventListener('beforeunload', (event) => {
        if (dirty) {
            event.preventDefault();
        }
    });

    renderPalette();
    renderLibrary();

    if (bp) {
        status(labels.status.ready);
        render();
    } else {
        ui.chooser.hidden = false;
    }
}

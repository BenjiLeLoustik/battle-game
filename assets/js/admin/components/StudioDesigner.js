const COLOR_PROPS = ['color', 'background-color', 'border-color'];

const hex = (value) => {
    const match = /rgba?\((\d+),\s*(\d+),\s*(\d+)/.exec(value ?? '');

    return match ? `#${match.slice(1, 4).map((part) => Number(part).toString(16).padStart(2, '0')).join('')}` : (value ?? '');
};

export default function StudioDesigner(element, data) {
    const find = (selector) => element.querySelector(selector);
    const labels = JSON.parse(data.labels);
    const fonts = JSON.parse(data.fonts);
    const shadows = JSON.parse(data.shadows);
    const parsed = JSON.parse(data.rules || '{}');
    const ui = {
        frame: find('.studio-design__frame'),
        stage: find('.studio-design__stage'),
        hover: find('.studio-design__outline--hover'),
        selected: find('.studio-design__outline--selected'),
        empty: find('.studio-design__empty'),
        target: find('.studio-design__target'),
        crumbs: find('.studio-design__crumbs'),
        selector: find('.studio-design__selector'),
        hoverState: find('.studio-design__hover-state'),
        controls: find('.studio-design__controls'),
        rules: find('.studio-design__rules'),
        state: find('.studio-design__state'),
        save: find('[data-action="save"]'),
        modes: [...element.querySelectorAll('[data-mode]')],
        reload: find('[data-action="reload"]'),
        reset: find('[data-action="reset"]'),
    };
    const inputs = [...ui.controls.querySelectorAll('[data-prop]')];
    const swatches = [...ui.controls.querySelectorAll('[data-color-for]')];
    const gradient = Object.fromEntries([...ui.controls.querySelectorAll('[data-gradient]')].map((input) => [input.dataset.gradient, input]));

    let rules = Array.isArray(parsed) ? {} : parsed;
    let doc = null;
    let style = null;
    let mode = 'select';
    let picked = null;
    let selector = null;
    let dirty = false;

    const status = (text, tone = '') => {
        ui.state.textContent = text;
        ui.state.dataset.tone = tone;
    };

    const output = (property, value) => (property === 'font-family' ? fonts[value] : property === 'box-shadow' ? shadows[value] : value);

    const css = () => Object.entries(rules).map(([target, properties]) => `${target} {\n${Object.entries(properties).map(([property, value]) => `    ${property}: ${output(property, value)} !important;`).join('\n')}\n}`).join('\n');

    const apply = () => {
        if (style) {
            style.textContent = css();
        }
    };

    const current = () => (selector ? `${selector}${ui.hoverState.checked ? ':hover' : ''}` : null);

    const screen = () => doc?.querySelector('.game-stage__screen');

    const pick = (event) => {
        const root = screen();

        if (!root) {
            return null;
        }

        let best = null;
        let area = Infinity;

        root.querySelectorAll('[class]').forEach((node) => {
            const box = node.getBoundingClientRect();
            const size = box.width * box.height;

            if (size > 0 && size <= area && event.clientX >= box.left && event.clientX <= box.right && event.clientY >= box.top && event.clientY <= box.bottom && doc.defaultView.getComputedStyle(node).visibility !== 'hidden') {
                best = node;
                area = size;
            }
        });

        return best ? classed(best) : classed(event.target);
    };

    const classed = (node) => {
        let target = node;

        while (target && target !== doc.body && !(target.classList && target.classList.length)) {
            target = target.parentElement;
        }

        return target && screen()?.contains(target) && target !== screen() ? target : null;
    };

    const place = (outline, node, label = '') => {
        if (!node || !doc || !node.isConnected) {
            outline.hidden = true;

            return;
        }

        const frame = ui.frame.getBoundingClientRect();
        const stage = ui.stage.getBoundingClientRect();
        const box = node.getBoundingClientRect();
        outline.hidden = false;
        outline.style.left = `${frame.left - stage.left + box.left}px`;
        outline.style.top = `${frame.top - stage.top + box.top}px`;
        outline.style.width = `${box.width}px`;
        outline.style.height = `${box.height}px`;

        if (label) {
            outline.dataset.label = label;
        }
    };

    const candidates = (node) => {
        const classes = [...node.classList].filter((name) => /^[a-zA-Z][a-zA-Z0-9_-]*$/.test(name));
        const options = classes.map((name) => `.${name}`);

        Object.entries(node.dataset).forEach(([key, value]) => {
            const attribute = key.replace(/[A-Z]/g, (letter) => `-${letter.toLowerCase()}`);

            if (classes[0] && /^[a-zA-Z0-9_-]{1,30}$/.test(value) && /^[a-z-]{1,30}$/.test(attribute)) {
                options.push(`.${classes[0]}[data-${attribute}="${value}"]`);
            }
        });

        return options;
    };

    const fill = () => {
        const target = current();
        const values = rules[target] ?? {};
        const computed = picked ? doc.defaultView.getComputedStyle(picked) : null;

        inputs.forEach((input) => {
            const property = input.dataset.prop;
            input.value = values[property] ?? '';

            if (input.tagName === 'INPUT' && computed) {
                const value = computed.getPropertyValue(property);
                input.placeholder = COLOR_PROPS.includes(property) ? hex(value) : value;
            }
        });

        swatches.forEach((swatch) => {
            const value = values[swatch.dataset.colorFor] ?? (computed ? hex(computed.getPropertyValue(swatch.dataset.colorFor)) : '#000000');
            swatch.value = /^#[0-9a-f]{6}$/i.test(value) ? value : '#000000';
        });

        const match = /^linear-gradient\((\d+)deg,\s*(#[0-9a-fA-F]+|transparent),\s*(#[0-9a-fA-F]+|transparent)\)$/.exec(values['background-image'] ?? '');
        gradient.enabled.checked = Boolean(match);
        gradient.angle.value = match?.[1] ?? '135';
        gradient.from.value = match?.[2]?.length === 7 ? match[2] : '#2EE6D6';
        gradient.to.value = match?.[3]?.length === 7 ? match[3] : '#FF3DCB';
        ui.controls.querySelector('.studio-design__opacity-value').textContent = values.opacity ?? '';
    };

    const renderRules = () => {
        const entries = Object.entries(rules);
        ui.rules.replaceChildren(...entries.map(([target, properties]) => {
            const item = document.createElement('li');
            const open = document.createElement('button');
            const remove = document.createElement('button');
            item.className = 'studio-design__rule' + (target === current() ? ' studio-design__rule--active' : '');
            open.type = 'button';
            open.className = 'studio-design__rule-name';
            open.textContent = `${target} · ${Object.keys(properties).length}`;
            open.addEventListener('click', () => {
                const node = doc?.querySelector(target.replace(':hover', ''));

                if (node) {
                    select(node, target.replace(':hover', ''), target.endsWith(':hover'));
                }
            });
            remove.type = 'button';
            remove.className = 'studio-design__rule-remove';
            remove.textContent = '×';
            remove.setAttribute('aria-label', labels.remove);
            remove.addEventListener('click', () => {
                delete rules[target];
                changed();
            });
            item.append(open, remove);

            return item;
        }));
        ui.rules.dataset.empty = String(entries.length === 0);
    };

    const changed = () => {
        dirty = true;
        status(labels.unsaved, 'warn');
        apply();
        renderRules();
        fill();
    };

    const select = (node, preferred = null, hover = false) => {
        picked = node;
        const options = candidates(node);
        selector = preferred && options.includes(preferred) ? preferred : options[0];
        ui.hoverState.checked = hover;
        ui.selector.replaceChildren(...options.map((option) => new Option(option, option, false, option === selector)));
        ui.empty.hidden = true;
        ui.target.hidden = false;
        ui.controls.hidden = false;
        ui.crumbs.replaceChildren(...ancestors(node).map((ancestor) => {
            const crumb = document.createElement('button');
            crumb.type = 'button';
            crumb.className = 'studio-design__crumb' + (ancestor === node ? ' studio-design__crumb--active' : '');
            crumb.textContent = `.${ancestor.classList[0]}`;
            crumb.addEventListener('click', () => select(ancestor));

            return crumb;
        }));
        place(ui.selected, node, selector);
        fill();
        renderRules();
    };

    const ancestors = (node) => {
        const list = [];
        let target = node;

        while (target && target !== screen()) {
            if (target.classList?.length) {
                list.unshift(target);
            }

            target = target.parentElement;
        }

        return list.slice(-5);
    };

    const setMode = (next) => {
        mode = next;
        ui.modes.forEach((button) => button.setAttribute('aria-pressed', String(button.dataset.mode === mode)));
        element.dataset.mode = mode;
        ui.hover.hidden = true;
    };

    const attach = () => {
        try {
            doc = ui.frame.contentDocument;
        } catch {
            doc = null;
        }

        if (!doc) {
            return;
        }

        doc.querySelectorAll('link[data-design]').forEach((link) => link.remove());
        style = doc.createElement('style');
        style.id = 'bg-designer';
        doc.head.append(style);
        apply();

        doc.addEventListener('mousemove', (event) => {
            if (mode !== 'select') {
                return;
            }

            const node = pick(event);
            place(ui.hover, node, node ? `.${node.classList[0]}` : '');
        }, true);

        doc.addEventListener('mouseleave', () => {
            ui.hover.hidden = true;
        });

        ['click', 'pointerdown', 'mousedown', 'pointerup'].forEach((type) => doc.addEventListener(type, (event) => {
            if (mode !== 'select') {
                return;
            }

            const node = pick(event);

            if (!node) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();

            if (type === 'click') {
                select(node);
            }
        }, true));

        status(labels.ready);
    };

    const loop = () => {
        if (picked) {
            place(ui.selected, picked, current());
        }

        requestAnimationFrame(loop);
    };

    inputs.forEach((input) => input.addEventListener(input.tagName === 'SELECT' || input.type === 'range' ? 'change' : 'input', () => {
        const target = current();

        if (!target) {
            return;
        }

        const value = input.value.trim();
        rules[target] ??= {};

        if (value === '') {
            delete rules[target][input.dataset.prop];
        } else {
            rules[target][input.dataset.prop] = value;
        }

        if (Object.keys(rules[target]).length === 0) {
            delete rules[target];
        }

        changed();
    }));

    swatches.forEach((swatch) => swatch.addEventListener('input', () => {
        const input = ui.controls.querySelector(`[data-prop="${swatch.dataset.colorFor}"]`);
        input.value = swatch.value.toUpperCase();
        input.dispatchEvent(new Event('input'));
    }));

    Object.values(gradient).forEach((input) => input.addEventListener('input', () => {
        const target = current();

        if (!target) {
            return;
        }

        rules[target] ??= {};

        if (gradient.enabled.checked) {
            rules[target]['background-image'] = `linear-gradient(${Math.max(0, Math.min(360, Number(gradient.angle.value) || 0))}deg, ${gradient.from.value.toUpperCase()}, ${gradient.to.value.toUpperCase()})`;
        } else {
            delete rules[target]['background-image'];
        }

        if (Object.keys(rules[target]).length === 0) {
            delete rules[target];
        }

        changed();
    }));

    ui.controls.querySelector('[data-prop="opacity"]').addEventListener('input', (event) => {
        ui.controls.querySelector('.studio-design__opacity-value').textContent = event.target.value;
    });

    ui.selector.addEventListener('change', () => {
        selector = ui.selector.value;
        fill();
        renderRules();
    });

    ui.hoverState.addEventListener('change', () => {
        fill();
        renderRules();
    });

    ui.reset.addEventListener('click', () => {
        if (current() && rules[current()]) {
            delete rules[current()];
            changed();
        }
    });

    ui.modes.forEach((button) => button.addEventListener('click', () => setMode(button.dataset.mode)));

    ui.reload?.addEventListener('click', () => {
        picked = null;
        ui.selected.hidden = true;
        ui.frame.src = data.preview;
    });

    ui.save.addEventListener('click', async () => {
        ui.save.disabled = true;
        status(labels.saving);

        try {
            const response = await fetch(data.save, {
                method: 'POST',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': data.token },
                body: JSON.stringify({ rules }),
            });
            const json = await response.json();

            if (!response.ok) {
                throw new Error(json.message ?? labels.error);
            }

            const sent = JSON.stringify(rules);
            rules = Array.isArray(json.rules) ? {} : json.rules;
            dirty = false;
            status(JSON.stringify(rules) === sent ? labels.saved : labels.cleaned, JSON.stringify(rules) === sent ? 'ok' : 'warn');
            apply();
            renderRules();
            fill();
        } catch (error) {
            status(error.message, 'error');
        } finally {
            ui.save.disabled = false;
        }
    });

    window.addEventListener('beforeunload', (event) => {
        if (dirty) {
            event.preventDefault();
        }
    });

    window.addEventListener('keydown', (event) => {
        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 's') {
            event.preventDefault();
            ui.save.click();
        }
    });

    ui.frame.addEventListener('load', attach);

    if (data.preview) {
        ui.frame.src = data.preview;
    }

    setMode('select');
    renderRules();
    requestAnimationFrame(loop);
}

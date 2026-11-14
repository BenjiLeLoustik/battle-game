const ESM = 'https://esm.sh';
const STATE = '@codemirror/state@6.7.6';
const VIEW = '@codemirror/view@6.43.15';
const DEPS = `deps=${STATE},${VIEW},@codemirror/language@6.13.1`;
const cdn = (module) => import(`${ESM}/${module}?${DEPS}`);

const LANGUAGES = {
    php: { label: 'PHP', load: async () => (await cdn('@codemirror/lang-php@6.0.2')).php() },
    js: { label: 'JavaScript', load: async () => (await cdn('@codemirror/lang-javascript@6.2.5')).javascript() },
    json: { label: 'JSON', load: async () => (await cdn('@codemirror/lang-json@6.0.2')).json() },
    css: { label: 'CSS', load: async () => (await cdn('@codemirror/lang-css@6.3.1')).css() },
    md: { label: 'Markdown', load: async () => (await cdn('@codemirror/lang-markdown@6.5.2')).markdown() },
    yaml: { label: 'YAML', load: async () => (await cdn('@codemirror/lang-yaml@6.1.3')).yaml() },
};

const extensionOf = (path) => {
    const extension = path.split('.').pop().toLowerCase();

    return extension === 'yml' ? 'yaml' : extension;
};

const node = (tag, className, text = '') => {
    const element = document.createElement(tag);
    element.className = className;
    element.textContent = text;

    return element;
};

const time = () => new Date().toLocaleTimeString('fr-FR');
const parentOf = (path) => (path.includes('/') ? path.slice(0, path.lastIndexOf('/')) : '');
const join = (folder, name) => (folder ? `${folder}/${name}` : name).replace(/^\/+/, '');

export default async function StudioEditor(element, data) {
    const find = (selector) => element.querySelector(selector);
    const labels = JSON.parse(data.labels);
    const ui = {
        tree: find('.studio-editor__tree'),
        tabs: find('.studio-editor__tabs'),
        code: find('.studio-editor__code'),
        empty: find('.studio-editor__empty'),
        state: find('.studio-editor__state'),
        language: find('.studio-editor__language'),
        cursor: find('.studio-editor__cursor'),
        console: find('.studio-editor__console'),
        preview: find('.studio-editor__frame'),
        reloaded: find('.studio-editor__reloaded'),
        menu: find('.studio-editor__menu'),
    };
    const [{ basicSetup, EditorView }, { EditorState }, { keymap }, { indentWithTab }, { indentUnit }, { oneDark }] = await Promise.all([
        cdn('codemirror@6.0.2'),
        import(`${ESM}/${STATE}`),
        import(`${ESM}/${VIEW}?deps=${STATE}`),
        cdn('@codemirror/commands@6.11.1'),
        import(`${ESM}/@codemirror/language@6.13.1?deps=${STATE},${VIEW}`),
        cdn('@codemirror/theme-one-dark@6.1.3'),
    ]);

    const theme = EditorView.theme({
        '&': { height: '100%', backgroundColor: '#0A0B0E', fontSize: '13px' },
        '.cm-scroller': { fontFamily: "'JetBrains Mono', monospace", lineHeight: '1.7' },
        '.cm-gutters': { backgroundColor: '#0A0B0E', color: '#4B5060', border: 'none' },
        '.cm-activeLine, .cm-activeLineGutter': { backgroundColor: 'rgba(139, 124, 255, 0.08)' },
    }, { dark: true });
    const indentation = [keymap.of([indentWithTab]), indentUnit.of('    '), EditorState.tabSize.of(4)];
    const files = new Map();
    const collapsed = new Set();
    let tree = JSON.parse(data.tree);
    let tabs = [];
    let current = null;
    let view = null;
    let target = null;
    let notice = null;

    const log = (message, type = 'info') => {
        ui.console.append(node('span', `studio-editor__log studio-editor__log--${type}`, `${time()}  ${message}`));
        ui.console.scrollTop = ui.console.scrollHeight;
        clearTimeout(notice);
        ui.state.textContent = message;
        ui.state.title = message;
        ui.state.dataset.tone = type;
        notice = setTimeout(() => {
            notice = null;
            renderState();
        }, type === 'error' ? 8000 : 4000);
    };

    const request = async (url, body = null) => {
        const response = await fetch(url, {
            method: body === null ? 'GET' : 'POST',
            headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': data.token },
            body: body === null ? undefined : JSON.stringify(body),
        });
        const json = await response.json().catch(() => ({}));

        if (!response.ok) {
            throw new Error(json.message ?? labels.error);
        }

        return json;
    };

    const isDirty = (path) => files.get(path).state.doc.toString() !== files.get(path).saved;
    const dirtyPaths = () => [...files.keys()].filter(isDirty);
    const isInside = (path, folder) => path === folder || path.startsWith(`${folder}/`);

    const renderState = () => {
        if (notice !== null) {
            return;
        }

        const dirty = dirtyPaths().length > 0;
        delete ui.state.dataset.tone;
        ui.state.title = '';
        ui.state.textContent = dirty ? labels.unsaved : labels.saved;
        ui.state.classList.toggle('studio-editor__state--dirty', dirty);
    };

    const renderCursor = () => {
        if (!view || !current) {
            return;
        }

        const head = view.state.selection.main.head;
        const line = view.state.doc.lineAt(head);
        ui.cursor.textContent = `Ln ${line.number}, Col ${head - line.from + 1}`;
    };

    const isHidden = (entry) => [...collapsed].some((folder) => entry.path.startsWith(`${folder}/`));

    const toggle = (folder) => {
        collapsed.has(folder) ? collapsed.delete(folder) : collapsed.add(folder);
        renderTree();
    };

    const renderTree = () => {
        ui.tree.replaceChildren(...tree.filter((entry) => !isHidden(entry)).map((entry) => {
            const className = entry.folder ? 'studio-editor__folder' : 'studio-editor__file' + (entry.path === current ? ' studio-editor__file--current' : '');
            const text = entry.folder ? `${collapsed.has(entry.path) ? '▸' : '▾'} ${entry.name}` : entry.name;
            const item = node('button', className, text);

            item.type = 'button';
            item.dataset.path = entry.path;
            item.style.setProperty('--depth', String(entry.depth));

            if (entry.folder) {
                item.setAttribute('aria-expanded', String(!collapsed.has(entry.path)));
                item.addEventListener('click', () => toggle(entry.path));
            } else {
                item.classList.toggle('studio-editor__file--locked', !entry.editable);
                item.addEventListener('click', () => entry.editable && open(entry.path));
            }

            return item;
        }));
    };

    const renderTabs = () => {
        ui.tabs.replaceChildren(...tabs.map((path) => {
            const tab = node('div', 'studio-editor__tab' + (path === current ? ' studio-editor__tab--active' : '') + (isDirty(path) ? ' studio-editor__tab--dirty' : ''));
            const label = node('button', 'studio-editor__tab-label', path.split('/').pop());
            const closer = node('button', 'studio-editor__tab-close', '×');

            label.type = 'button';
            label.title = path;
            label.setAttribute('role', 'tab');
            label.setAttribute('aria-selected', String(path === current));
            label.addEventListener('click', () => open(path));
            closer.type = 'button';
            closer.title = labels.close;
            closer.setAttribute('aria-label', `${labels.close} ${path}`);
            closer.addEventListener('click', () => close(path));
            tab.addEventListener('auxclick', (event) => {
                if (event.button === 1) {
                    close(path);
                }
            });
            tab.append(label, closer);

            return tab;
        }));
    };

    const render = () => {
        renderTree();
        renderTabs();
        renderState();
        renderCursor();
    };

    const showEmpty = () => {
        if (view) {
            view.dom.hidden = true;
        }

        ui.empty.textContent = labels.no_file;
        ui.empty.hidden = false;
        ui.language.textContent = '';
        ui.cursor.textContent = '';
    };

    const discard = (path) => {
        tabs = tabs.filter((tab) => tab !== path);
        files.delete(path);

        if (path === current) {
            current = null;
        }
    };

    const close = (path) => {
        if (isDirty(path) && !window.confirm(labels.close_unsaved)) {
            return;
        }

        const index = tabs.indexOf(path);
        const wasCurrent = path === current;
        discard(path);

        if (!wasCurrent) {
            render();

            return;
        }

        const next = tabs[index] ?? tabs[index - 1];

        if (next) {
            open(next);

            return;
        }

        showEmpty();
        render();
    };

    const listener = EditorView.updateListener.of((update) => {
        if (!current) {
            return;
        }

        if (update.docChanged || update.selectionSet) {
            files.get(current).state = update.state;
        }

        if (update.docChanged) {
            renderTabs();
            renderState();
        }

        renderCursor();
    });

    const load = async (path) => {
        const { content } = await request(`${data.read}?path=${encodeURIComponent(path)}`);
        const language = LANGUAGES[extensionOf(path)];
        const extensions = [basicSetup, ...indentation, oneDark, theme, listener, EditorView.lineWrapping];

        if (language) {
            extensions.push(await language.load());
        }

        files.set(path, { saved: content, state: EditorState.create({ doc: content, extensions }) });
    };

    const open = async (path) => {
        try {
            if (!files.has(path)) {
                await load(path);
            }
        } catch (error) {
            log(`${path} : ${error.message}`, 'error');

            return;
        }

        current = path;
        tabs = tabs.includes(path) ? tabs : [...tabs, path];
        ui.empty.hidden = true;
        ui.language.textContent = LANGUAGES[extensionOf(path)]?.label ?? labels.text;

        if (view) {
            view.setState(files.get(path).state);
            view.dom.hidden = false;
        } else {
            view = new EditorView({ state: files.get(path).state, parent: ui.code });
        }

        render();
        view.focus();
    };

    const reload = () => {
        if (!ui.preview) {
            return;
        }

        ui.preview.src = data.preview;
        ui.reloaded.textContent = `● ${labels.reloaded} ${time()}`;
    };

    const save = async () => {
        const paths = dirtyPaths();

        for (const path of paths) {
            const content = files.get(path).state.doc.toString();
            await request(data.save, { path, content });
            files.get(path).saved = content;
            log(`${labels.saved_file} ${path}`, 'success');
        }

        if (paths.length === 0) {
            log(labels.nothing);
        } else {
            reload();
        }

        render();
    };

    const run = async (action, button = null) => {
        if (button?.disabled) {
            return;
        }

        button?.setAttribute('aria-busy', 'true');
        button && (button.disabled = true);

        try {
            await action();
        } catch (error) {
            log(error.message, 'error');
        } finally {
            button?.removeAttribute('aria-busy');
            button && (button.disabled = button.dataset.pending === 'true');
        }
    };

    const validate = async () => {
        const { error } = await request(data.validate, {});
        log(error ? `${labels.invalid} ${error}` : labels.valid, error ? 'error' : 'success');
    };

    const publish = async () => {
        await save();
        const { error, version } = await request(data.publish, {});

        if (error) {
            log(`${labels.invalid} ${error}`, 'error');

            return;
        }

        log(`${labels.published} v${version}`, 'success');
        reload();
    };

    const review = async () => {
        await save();
        const message = window.prompt(labels.review_prompt, '');

        if (message === null) {
            return;
        }

        const { already } = await request(data.review, { message });
        const button = find('[data-action="review"]');
        log(already ? labels.review_already : labels.review_sent, 'success');
        button.dataset.pending = 'true';
        button.textContent = labels.review_pending;
    };

    const ask = (message, value) => {
        const answer = window.prompt(message, value);

        return answer ? answer.trim().replace(/^\/+|\/+$/g, '') : null;
    };

    const createFile = async (folder) => {
        const path = ask(labels.new_file, folder ? `${folder}/` : '');

        if (!path) {
            return;
        }

        tree = (await request(data.create, { path })).tree;
        collapsed.delete(folder);
        log(`${labels.created} ${path}`, 'success');
        await open(path);
    };

    const createFolder = async (folder) => {
        const path = ask(labels.new_folder, folder ? `${folder}/` : '');

        if (!path) {
            return;
        }

        tree = (await request(data.folder, { path })).tree;
        collapsed.delete(folder);
        log(`${labels.folder_created} ${path}`, 'success');
        renderTree();
    };

    const rename = async (from) => {
        const name = ask(labels.rename, from.split('/').pop());
        const to = name ? join(parentOf(from), name) : null;

        if (!to || to === from) {
            return;
        }

        tree = (await request(data.rename, { from, to })).tree;

        tabs = tabs.map((path) => (isInside(path, from) ? to + path.slice(from.length) : path));
        [...files.keys()].filter((path) => isInside(path, from)).forEach((path) => {
            files.set(to + path.slice(from.length), files.get(path));
            files.delete(path);
        });
        current = current && isInside(current, from) ? to + current.slice(from.length) : current;

        log(`${labels.renamed} ${from} → ${to}`, 'success');
        reload();
        render();
    };

    const remove = async (path) => {
        if (!window.confirm(`${labels.delete_confirm} ${path}`)) {
            return;
        }

        tree = (await request(data.delete, { path })).tree;
        tabs.filter((tab) => isInside(tab, path)).forEach(discard);

        if (!current) {
            tabs.length > 0 ? await open(tabs[tabs.length - 1]) : showEmpty();
        }

        log(`${labels.deleted} ${path}`, 'success');
        reload();
        render();
    };

    const hideMenu = () => {
        ui.menu.hidden = true;
        target = null;
    };

    const showMenu = (event, entry) => {
        event.preventDefault();
        target = entry;

        ui.menu.querySelectorAll('[data-command]').forEach((item) => {
            const command = item.dataset.command;
            const forEntry = command === 'rename' || command === 'delete';
            item.hidden = forEntry ? entry === null : false;
            item.disabled = forEntry && entry !== null && entry.protected;
        });

        ui.menu.hidden = false;
        const { offsetWidth, offsetHeight } = ui.menu;
        ui.menu.style.left = `${Math.min(event.clientX, window.innerWidth - offsetWidth - 8)}px`;
        ui.menu.style.top = `${Math.min(event.clientY, window.innerHeight - offsetHeight - 8)}px`;
        ui.menu.querySelector('[data-command]:not([hidden]):not([disabled])')?.focus();
    };

    const commands = {
        'new-file': () => createFile(target === null ? '' : target.folder ? target.path : parentOf(target.path)),
        'new-folder': () => createFolder(target === null ? '' : target.folder ? target.path : parentOf(target.path)),
        rename: () => rename(target.path),
        delete: () => remove(target.path),
    };

    ui.tree.addEventListener('contextmenu', (event) => {
        const item = event.target.closest('[data-path]');
        showMenu(event, item ? tree.find((entry) => entry.path === item.dataset.path) : null);
    });

    ui.menu.addEventListener('click', (event) => {
        const item = event.target.closest('[data-command]');

        if (!item || item.disabled) {
            return;
        }

        const command = commands[item.dataset.command];
        run(command).finally(hideMenu);
    });

    document.addEventListener('click', (event) => {
        if (!ui.menu.contains(event.target)) {
            hideMenu();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            hideMenu();
        }

        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 's') {
            event.preventDefault();
            run(save);
        }
    });

    window.addEventListener('blur', hideMenu);
    ui.tree.addEventListener('scroll', hideMenu);

    window.addEventListener('beforeunload', (event) => {
        if (dirtyPaths().length > 0) {
            event.preventDefault();
        }
    });

    const actions = { save, validate, publish, review };

    Object.keys(actions).forEach((name) => {
        const button = find(`[data-action="${name}"]`);
        button?.addEventListener('click', () => run(actions[name], button));
    });
    find('[data-action="create"]').addEventListener('click', () => run(() => createFile('')));
    find('[data-action="reload"]')?.addEventListener('click', reload);

    render();
    log(labels.ready);
    await open(data.initial);
    await run(validate);
    reload();
}
const fold = (text) => text.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');

export default function DocsSearch(element) {
    const query = element.querySelector('.docs-search__query');
    const empty = element.querySelector('.docs-search__empty');
    const items = [...element.querySelectorAll('.docs-search__item')].map((item) => ({ item, text: fold(`${item.textContent} ${item.dataset.search ?? ''}`) }));
    const groups = [...element.querySelectorAll('.docs-search__group')];

    if (!query) {
        return;
    }

    query.addEventListener('input', () => {
        const words = fold(query.value).split(/\s+/).filter(Boolean);
        let shown = 0;

        items.forEach(({ item, text }) => {
            item.hidden = !words.every((word) => text.includes(word));
            shown += item.hidden ? 0 : 1;
        });

        groups.forEach((group) => {
            group.hidden = !group.querySelector('.docs-search__item:not([hidden])');

            if (words.length && group.tagName === 'DETAILS') {
                group.open = true;
            }
        });

        if (empty) {
            empty.hidden = shown > 0;
        }
    });

    window.addEventListener('keydown', (event) => {
        if (event.key === '/' && !['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement?.tagName)) {
            event.preventDefault();
            query.focus();
        }
    });
}

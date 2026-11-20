export default function ShopCatalog(element, data) {
    const search = element.querySelector('[data-shop-search]');
    const items = [...element.querySelectorAll('[data-shop-item]')];
    const groups = [...element.querySelectorAll('[data-shop-group]')];
    const empty = element.querySelector('[data-shop-empty]');
    const results = element.querySelector('[data-shop-results]');
    const clear = element.querySelector('[data-shop-clear]');
    const fold = (value) => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase().trim();

    const filter = () => {
        const words = fold(search.value).split(/\s+/).filter(Boolean);
        let count = 0;

        items.forEach((item) => {
            const text = fold(item.dataset.search || '');
            item.hidden = !words.every((word) => text.includes(word));
            count += item.hidden ? 0 : 1;
        });
        groups.forEach((group) => {
            group.hidden = !group.querySelector('[data-shop-item]:not([hidden])');
        });
        empty.hidden = count > 0;
        clear.hidden = words.length === 0;
        results.textContent = data.countLabel.replace('__COUNT__', String(count));
    };

    search.addEventListener('input', filter);
    clear.addEventListener('click', () => {
        search.value = '';
        filter();
        search.focus();
    });

    element.addEventListener('click', (event) => {
        const open = event.target.closest('[data-shop-open]');
        const close = event.target.closest('[data-shop-close]');

        if (open) {
            const dialog = open.closest('[data-shop-item]').querySelector('.shop-offer');
            if (!dialog.open) {
                dialog.showModal();
            }
        }

        if (close) {
            close.closest('.shop-offer').close();
        }

        if (event.target.matches('.shop-offer')) {
            const dialog = event.target;
            const rect = dialog.getBoundingClientRect();
            if (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom) {
                dialog.close();
            }
        }
    });

    filter();
}

export default function RoomFilter(element) {
    const search = element.querySelector('[data-filter="search"]');
    const free = element.querySelector('[data-filter="free"]');
    const rows = element.querySelectorAll('[data-search]');

    const apply = () => {
        const query = search.value.trim().toLowerCase();

        rows.forEach((row) => {
            row.hidden = (query !== '' && !row.dataset.search.includes(query)) || (free.checked && row.dataset.full === '1');
        });
    };

    search.addEventListener('input', apply);
    free.addEventListener('change', apply);
    apply();
}

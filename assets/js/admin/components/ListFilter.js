export default function ListFilter(element, data) {
    const list = document.querySelector(data.target);
    const empty = data.empty ? document.querySelector(data.empty) : null;
    const rows = [...list.children].map((row) => ({ row, text: row.textContent.toLowerCase() }));

    element.addEventListener('input', () => {
        const query = element.value.trim().toLowerCase();
        let shown = 0;

        rows.forEach(({ row, text }) => {
            row.hidden = query !== '' && !text.includes(query);
            shown += row.hidden ? 0 : 1;
        });

        if (empty) {
            empty.hidden = shown > 0;
        }
    });
}

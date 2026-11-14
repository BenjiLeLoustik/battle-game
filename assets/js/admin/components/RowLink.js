export default function RowLink(element) {
    element.addEventListener('click', (event) => {
        const row = event.target.closest('[data-href]');

        if (!row || event.target.closest('a, button, input, select, label, form')) {
            return;
        }

        if (event.ctrlKey || event.metaKey) {
            window.open(row.dataset.href, '_blank');

            return;
        }

        window.location.href = row.dataset.href;
    });
}

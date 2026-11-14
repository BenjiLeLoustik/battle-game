export default function RowToggle(element) {
    const row = document.getElementById(element.getAttribute('aria-controls'));

    element.addEventListener('click', () => {
        const open = row.hidden;
        row.hidden = !open;
        element.setAttribute('aria-expanded', String(open));
        element.classList.toggle('admin-btn--active', open);

        if (open) {
            row.querySelector('input')?.focus();
        }
    });
}

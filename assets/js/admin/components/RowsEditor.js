export default function RowsEditor(form) {
    const rows = form.querySelector('[data-rows]');
    const template = form.querySelector('[data-row-template]');
    let counter = 100000;

    const sync = (row) => {
        const kind = row.querySelector('[data-reward-kind]');

        if (!kind) {
            return;
        }

        row.querySelectorAll('[data-show]').forEach((field) => {
            const visible = field.dataset.show.split(' ').includes(kind.value);
            field.hidden = !visible;
            field.disabled = !visible;
        });
    };

    rows.querySelectorAll('[data-row]').forEach(sync);

    form.addEventListener('change', (event) => {
        if (event.target.matches('[data-reward-kind]')) {
            sync(event.target.closest('[data-row]'));
        }
    });

    form.querySelector('[data-add-row]')?.addEventListener('click', () => {
        const holder = document.createElement('ul');
        holder.innerHTML = template.innerHTML.split('__INDEX__').join(String(counter += 1)).trim();
        const row = holder.firstElementChild;
        rows.append(row);
        sync(row);
        row.querySelector('input:not([type="color"])')?.focus();
    });

    form.addEventListener('click', (event) => {
        const remove = event.target.closest('[data-remove-row]');

        if (remove) {
            remove.closest('[data-row]').remove();
        }
    });

    form.addEventListener('input', (event) => {
        const row = event.target.closest('[data-row]');

        if (!row) {
            return;
        }

        if (event.target.matches('[data-rank-color]')) {
            row.style.setProperty('--rank-color', event.target.value);
        }

        if (event.target.matches('[data-rank-name]')) {
            const preview = row.querySelector('[data-rank-preview]');

            if (preview) {
                preview.textContent = event.target.value;
            }
        }
    });
}

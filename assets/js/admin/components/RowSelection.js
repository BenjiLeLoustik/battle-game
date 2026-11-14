export default function RowSelection(form, data) {
    const rows = [...document.querySelectorAll(`[form="${form.id}"][data-selection-row]`)];
    const page = document.querySelector(`[form="${form.id}"][data-selection-page]`);
    const all = form.querySelector('[data-selection-all]');
    const count = form.querySelector('[data-selection-count]');
    const everything = form.querySelector('[data-selection-everything]');
    const submit = form.querySelector('[data-selection-submit]');
    const total = Number(data.total ?? 0);

    const sync = () => {
        const checked = rows.filter((row) => row.checked).length;
        const whole = all.value === '1';

        if (page) {
            page.checked = rows.length > 0 && checked === rows.length;
            page.indeterminate = checked > 0 && checked < rows.length;
        }

        count.textContent = whole ? data.labelAll : data.label.replace('{count}', checked);
        everything.hidden = whole || checked === 0 || checked !== rows.length || total <= rows.length;
        submit.disabled = !whole && checked === 0;
    };

    rows.forEach((row) => row.addEventListener('change', () => {
        all.value = '0';
        sync();
    }));

    page?.addEventListener('change', () => {
        all.value = '0';
        rows.forEach((row) => {
            row.checked = page.checked;
        });
        sync();
    });

    everything.addEventListener('click', () => {
        all.value = '1';
        sync();
    });

    sync();
}

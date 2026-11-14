export default function FieldReveal(element, data) {
    const form = element.closest('form');
    const values = data.values.split(',');
    const inputs = [...form.querySelectorAll(`[name="${data.field}"]`)];

    const sync = () => {
        const input = inputs.find((item) => item.type !== 'radio' || item.checked);
        const value = input?.type === 'checkbox' ? String(input.checked) : input?.value;
        element.hidden = !values.includes(value);

        inputs.filter((item) => item.type === 'radio').forEach((item) => {
            item.closest('.admin-segment__item')?.classList.toggle('admin-segment__item--active', item.checked);
        });
    };

    inputs.forEach((input) => input.addEventListener('change', sync));
    sync();
}

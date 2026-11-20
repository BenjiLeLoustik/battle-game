export default function ProductSizes(element, data) {
    const select = element.querySelector('[data-size-select]');
    const list = element.querySelector('[data-size-list]');

    const values = () => new Set([...list.querySelectorAll('[data-size-value]')].map((input) => input.value));

    const sync = () => {
        const selected = values();
        [...select.options].forEach((option) => {
            option.disabled = option.value !== '' && selected.has(option.value);
        });
        list.hidden = selected.size === 0;
        select.value = '';
    };

    select.addEventListener('change', () => {
        const size = select.value;

        if (!size || values().has(size)) {
            return;
        }

        const row = document.createElement('li');
        row.className = 'shop-sizes__row';
        row.dataset.sizeRow = '';

        const input = document.createElement('input');
        input.type = 'text';
        input.className = 'admin-field__input shop-sizes__value';
        input.name = 'sizes[]';
        input.value = size;
        input.size = Math.max(4, size.length + 2);
        input.title = size;
        input.readOnly = true;
        input.dataset.sizeValue = '';
        input.disabled = Boolean(element.closest('[data-for][hidden]'));
        input.setAttribute('aria-label', data.fieldLabel + ' : ' + size);

        const remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'shop-sizes__remove';
        remove.dataset.sizeRemove = '';
        remove.textContent = '×';
        remove.setAttribute('aria-label', data.removeLabel.replace('__SIZE__', size));
        remove.title = data.removeLabel.replace('__SIZE__', size);

        row.append(input, remove);
        list.append(row);
        sync();
    });

    element.addEventListener('click', (event) => {
        const remove = event.target.closest('[data-size-remove]');

        if (remove) {
            remove.closest('[data-size-row]').remove();
            sync();
            select.focus();
        }
    });

    sync();
}

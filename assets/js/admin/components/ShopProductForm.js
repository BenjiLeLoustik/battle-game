export default function ShopProductForm(form) {
    const types = [...form.querySelectorAll('input[name="type"]')];
    const kinds = [...form.querySelectorAll('input[name="kind"]')];
    const coins = form.querySelector('[data-coins-field]');
    const unlimited = form.querySelector('[data-plan-unlimited]');
    const lives = form.querySelector('[data-plan-lives]');
    const file = form.querySelector('[data-image-input]');
    const preview = form.querySelector('[data-image-preview]');

    const update = () => {
        const type = types.find((input) => input.checked);
        const kind = kinds.find((input) => input.checked)?.value ?? 'frame';

        form.querySelectorAll('[data-for]').forEach((block) => {
            block.hidden = block.dataset.for !== type?.value;
        });

        form.querySelectorAll('[data-kind]').forEach((block) => {
            block.hidden = block.dataset.kind !== kind;
        });

        if (coins) {
            coins.hidden = type?.dataset.coins !== '1';
        }

        form.querySelectorAll('[data-for] :is(input, select, textarea), [data-coins-field] :is(input, select, textarea)').forEach((control) => {
            control.disabled = Boolean(control.closest('[data-for][hidden], [data-kind][hidden], [data-coins-field][hidden]'));
        });

        if (unlimited && lives && !unlimited.disabled) {
            lives.disabled = unlimited.checked;
        }
    };

    [...types, ...kinds, unlimited].filter(Boolean).forEach((input) => input.addEventListener('change', update));

    file?.addEventListener('change', () => {
        const [image] = file.files;

        if (image && preview) {
            preview.src = URL.createObjectURL(image);
            preview.hidden = false;
            preview.closest('.shop-drop')?.classList.add('shop-drop--filled');
        }
    });

    const editor = form.querySelector('[data-badge-editor]');

    if (editor) {
        const badge = editor.querySelector('.plan-badge');
        const hex = editor.querySelector('[data-hex]');
        const refresh = () => {
            const icon = editor.querySelector('input[name="icon"]:checked')?.closest('label')?.querySelector('svg');

            if (badge && hex && /^#[0-9a-f]{6}$/i.test(hex.value)) {
                badge.style.setProperty('--plan-color', hex.value);
            }

            if (badge && icon) {
                badge.replaceChildren(icon.cloneNode(true));
            }
        };

        editor.addEventListener('input', refresh);
        editor.addEventListener('change', refresh);
        editor.addEventListener('click', () => setTimeout(refresh));
    }

    update();
}

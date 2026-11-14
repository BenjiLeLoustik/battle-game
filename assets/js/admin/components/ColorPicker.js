const HEX = /^#[0-9A-F]{6}$/;

export default function ColorPicker(element) {
    const hex = element.querySelector('[data-hex]');
    const native = element.querySelector('[data-native]');
    const custom = native.closest('.color-picker__custom');
    const swatches = [...element.querySelectorAll('[data-color]')];

    const sync = (emit) => {
        const value = hex.value.toUpperCase();

        if (!HEX.test(value)) {
            return;
        }

        const preset = swatches.find((swatch) => swatch.dataset.color.toUpperCase() === value);
        swatches.forEach((swatch) => {
            swatch.classList.toggle('color-picker__swatch--active', swatch === preset);
            swatch.setAttribute('aria-pressed', String(swatch === preset));
        });
        custom.classList.toggle('color-picker__swatch--active', preset === undefined);
        custom.style.setProperty('--chip', value);
        native.value = value.toLowerCase();

        if (emit) {
            hex.dispatchEvent(new Event('input', { bubbles: true }));
        }
    };

    const set = (value) => {
        hex.value = value.toUpperCase();
        sync(true);
    };

    swatches.forEach((swatch) => swatch.addEventListener('click', () => set(swatch.dataset.color)));
    native.addEventListener('input', (event) => {
        event.stopPropagation();
        set(native.value);
    });
    hex.addEventListener('input', () => sync(false));
    hex.addEventListener('blur', () => {
        if (!hex.value.startsWith('#')) {
            set('#' + hex.value);
        }
    });
}

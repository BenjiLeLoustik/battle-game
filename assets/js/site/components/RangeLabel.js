export default function RangeLabel(element) {
    const input = element.querySelector('input[type="range"]');
    const value = element.querySelector('.settings-range__value');

    input.addEventListener('input', () => {
        value.textContent = input.value;
    });
}

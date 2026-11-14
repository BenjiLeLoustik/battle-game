export default function ConfirmMatch(element, data) {
    const button = element.form.querySelector('[type="submit"]');
    const update = () => {
        button.disabled = element.value.trim() !== data.expected;
    };

    element.addEventListener('input', update);
    update();
}
export default function AutoSubmit(element) {
    element.addEventListener('change', () => element.form.submit());
}
export default function ConfirmSubmit(element, data) {
    element.addEventListener('submit', (event) => {
        if (!window.confirm(data.message)) {
            event.preventDefault();
        }
    });
}

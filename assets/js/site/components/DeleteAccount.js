export default function DeleteAccount(element, data) {
    const input = element.querySelector('input[name="username"]');
    const submit = element.querySelector('button[type="submit"]');

    const sync = () => {
        submit.disabled = input.value.trim() !== data.username;
    };

    input.addEventListener('input', sync);
    sync();
}

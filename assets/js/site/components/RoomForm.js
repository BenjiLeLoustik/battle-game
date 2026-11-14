export default function RoomForm(element) {
    const password = element.querySelector('[data-password]');
    const input = password.querySelector('input');

    const sync = () => {
        const protectedRoom = element.querySelector('input[name="visibility"]:checked')?.value === 'password';
        password.hidden = !protectedRoom;
        input.required = protectedRoom;
    };

    element.querySelectorAll('input[name="visibility"]').forEach((radio) => radio.addEventListener('change', sync));
    sync();
}

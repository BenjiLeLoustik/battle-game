export default function RestoreDialog(dialog) {
    const form = dialog.querySelector('form');
    const name = dialog.querySelector('[data-restore-name]');
    const confirm = form.querySelector('[name="confirm"]');

    document.querySelectorAll('[data-restore]').forEach((button) => button.addEventListener('click', () => {
        form.action = button.dataset.restore;
        name.textContent = button.dataset.name;
        confirm.value = '';
        confirm.dispatchEvent(new Event('input'));
        dialog.showModal();
        confirm.focus();
    }));

    dialog.querySelector('[data-restore-close]').addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) {
            dialog.close();
        }
    });
}

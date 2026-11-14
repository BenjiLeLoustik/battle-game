export default function ResetForm(form) {
    const boxes = [...form.querySelectorAll('input[name="blocks[]"]')];
    const confirm = form.querySelector('[name="confirm"]');
    const submit = form.querySelector('[data-reset-submit]');

    const sync = () => {
        submit.disabled = !boxes.some((box) => box.checked) || confirm.value.trim() !== confirm.dataset.expected;
    };

    boxes.forEach((box) => box.addEventListener('change', sync));
    confirm.addEventListener('input', () => setTimeout(sync));
    sync();
}

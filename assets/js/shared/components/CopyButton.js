export default function CopyButton(element, data) {
    const label = element.innerHTML;
    let timer = null;

    element.addEventListener('click', async () => {
        try {
            await navigator.clipboard.writeText(data.text);
        } catch {
            return;
        }

        element.classList.add('is-copied');
        clearTimeout(timer);

        if (data.done) {
            element.textContent = data.done;
        }

        timer = setTimeout(() => {
            element.classList.remove('is-copied');
            element.innerHTML = label;
        }, 2500);
    });
}

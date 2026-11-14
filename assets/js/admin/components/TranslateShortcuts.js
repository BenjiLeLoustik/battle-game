export default function TranslateShortcuts(element) {
    const skip = element.querySelector('[data-skip]');

    element.addEventListener('keydown', (event) => {
        if (event.ctrlKey && event.key === 'Enter') {
            event.preventDefault();
            element.requestSubmit();
        }

        if (event.ctrlKey && event.key === 'ArrowRight') {
            event.preventDefault();
            window.location.href = skip.href;
        }
    });
}

export default function EmailLayout(element, data) {
    const code = element.querySelector('.email-editor__code');
    const frame = element.querySelector('.email-editor__frame');
    let timer = null;

    const refresh = async () => {
        const body = new FormData();
        body.append('_token', data.token);
        body.append('layout', code.value);

        try {
            const response = await fetch(data.preview, { method: 'POST', body });
            frame.srcdoc = await response.text();
        } catch {}
    };

    code.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(refresh, 400);
    });

    refresh();
}

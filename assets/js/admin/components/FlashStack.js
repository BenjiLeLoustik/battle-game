import dom from 'shared/dom';

const { node } = dom;

const DELAY = 5000;

export default function FlashStack(element, data) {
    const hide = (flash) => {
        flash.classList.add('admin-flash--hidden');
        flash.addEventListener('transitionend', () => flash.remove(), { once: true });
    };

    const mount = (flash) => {
        const close = node('button', 'admin-flash__close', '×');
        close.type = 'button';
        close.setAttribute('aria-label', data.close);
        close.addEventListener('click', () => hide(flash));
        flash.append(close);

        if (!flash.classList.contains('admin-flash--error')) {
            setTimeout(() => hide(flash), DELAY);
        }
    };

    element.querySelectorAll('.admin-flash').forEach(mount);

    window.addEventListener('admin:flash', (event) => {
        const flash = node('p', 'admin-flash admin-flash--' + event.detail.type);
        flash.setAttribute('role', event.detail.type === 'error' ? 'alert' : 'status');
        flash.append(node('span', 'admin-flash__text', event.detail.message));
        element.append(flash);
        mount(flash);
    });
}

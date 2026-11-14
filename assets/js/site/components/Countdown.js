const pad = (value) => String(value).padStart(2, '0');

export default function Countdown(element, data) {
    const until = Number(data.until) * 1000;

    const tick = () => {
        const left = Math.max(0, Math.floor((until - Date.now()) / 1000));
        const days = Math.floor(left / 86400);
        const clock = `${pad(Math.floor((left % 86400) / 3600))}:${pad(Math.floor((left % 3600) / 60))}:${pad(left % 60)}`;
        element.textContent = days > 0 ? `${days}${data.day ?? 'j'} ${clock}` : clock;

        if (left === 0) {
            clearInterval(timer);

            if (data.reload) {
                setTimeout(() => window.location.reload(), 2000);
            }
        }
    };

    const timer = setInterval(tick, 1000);
    tick();
}

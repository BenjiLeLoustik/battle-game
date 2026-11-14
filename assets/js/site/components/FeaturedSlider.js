export default function FeaturedSlider(element, data) {
    const track = element.querySelector('.featured__track');
    const slides = [...track.children];
    const dots = [...element.querySelectorAll('.featured__dot')];
    const toggle = element.querySelector('.featured__arrow--toggle');
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)');
    const interval = Number(data.interval) || 7000;
    let index = 0;
    let timer = null;
    let hovered = false;
    let stopped = reduced.matches;

    const mark = () => {
        slides.forEach((slide, position) => {
            slide.inert = position !== index;
        });
        dots.forEach((dot, position) => {
            dot.setAttribute('aria-current', String(position === index));
        });
    };

    const sync = () => {
        index = Math.round(track.scrollLeft / track.clientWidth);
        mark();
    };

    const go = (next) => {
        index = (next + slides.length) % slides.length;
        mark();
        track.scrollTo({ left: index * track.clientWidth, behavior: reduced.matches ? 'auto' : 'smooth' });
    };

    const schedule = () => {
        clearInterval(timer);
        timer = null;

        if (slides.length > 1 && !stopped && !hovered && !document.hidden) {
            timer = setInterval(() => go(index + 1), interval);
        }

        if (toggle) {
            toggle.setAttribute('aria-pressed', String(stopped));
            toggle.setAttribute('aria-label', stopped ? data.play : data.pause);
        }
    };

    track.addEventListener('scrollend', sync);
    track.addEventListener('touchend', () => setTimeout(sync, 400), { passive: true });

    element.querySelectorAll('[data-step]').forEach((button) => button.addEventListener('click', () => {
        go(index + Number(button.dataset.step));
        schedule();
    }));

    dots.forEach((dot) => dot.addEventListener('click', () => {
        go(Number(dot.dataset.index));
        schedule();
    }));

    toggle?.addEventListener('click', () => {
        stopped = !stopped;
        schedule();
    });

    element.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
            event.preventDefault();
            go(index + (event.key === 'ArrowLeft' ? -1 : 1));
            schedule();
        }
    });

    element.addEventListener('mouseenter', () => {
        hovered = true;
        schedule();
    });

    element.addEventListener('mouseleave', () => {
        hovered = false;
        schedule();
    });

    element.addEventListener('focusin', () => {
        hovered = true;
        schedule();
    });

    element.addEventListener('focusout', (event) => {
        hovered = element.contains(event.relatedTarget);
        schedule();
    });

    document.addEventListener('visibilitychange', schedule);
    window.addEventListener('resize', () => track.scrollTo({ left: index * track.clientWidth }));

    sync();
    schedule();
}

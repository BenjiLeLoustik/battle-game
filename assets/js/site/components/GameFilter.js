export default function GameFilter(element, data) {
    const chips = [...element.querySelectorAll('.home__chip')];
    const grid = element.querySelector('.home__grid');
    const empty = element.querySelector('.home__empty');
    const more = element.querySelector('.home__more');
    const retry = element.querySelector('.home__retry');

    let category = '';
    let next = data.next ? Number(data.next) : null;
    let loading = null;
    let version = 0;

    const toggle = () => {
        more.hidden = next === null && loading === null;
        empty.hidden = grid.children.length > 0 || loading !== null;
        grid.setAttribute('aria-busy', String(loading !== null));
    };

    const load = (page, replace = false) => {
        const current = ++version;
        const url = new URL(data.feed, window.location.origin);
        url.searchParams.set('page', String(page));

        if (category !== '') {
            url.searchParams.set('category', category);
        }

        retry.hidden = true;
        loading = fetch(url, { headers: { Accept: 'application/json' } })
            .then((response) => (response.ok ? response.json() : Promise.reject(response)))
            .then((result) => {
                if (current !== version) {
                    return;
                }

                const fragment = document.createRange().createContextualFragment(result.html);
                replace ? grid.replaceChildren(fragment) : grid.append(fragment);
                next = result.next;
            })
            .catch(() => {
                if (current === version) {
                    retry.hidden = false;
                }
            })
            .finally(() => {
                if (current === version) {
                    loading = null;
                    toggle();
                    fill();
                }
            });
        toggle();
    };

    const observer = new IntersectionObserver((entries) => {
        if (entries.some((entry) => entry.isIntersecting) && next !== null && loading === null && retry.hidden) {
            load(next);
        }
    }, { rootMargin: '400px 0px' });

    const fill = () => {
        const bounds = more.getBoundingClientRect();

        if (next !== null && loading === null && retry.hidden && bounds.top < window.innerHeight + 400) {
            load(next);
        }
    };

    chips.forEach((chip) => {
        chip.addEventListener('click', () => {
            if (chip.dataset.category === category) {
                return;
            }

            chips.forEach((other) => {
                other.classList.toggle('home__chip--active', other === chip);
                other.setAttribute('aria-pressed', String(other === chip));
            });

            category = chip.dataset.category;
            next = null;
            loading = null;
            grid.replaceChildren();
            load(1, true);
        });
    });

    retry.addEventListener('click', () => load(next ?? 1, grid.children.length === 0));
    observer.observe(more);
    toggle();
}

export default function MaintenanceForm(element, data) {
    const labels = JSON.parse(data.labels);
    const find = (selector) => element.querySelector(selector);
    const sections = element.querySelectorAll('[data-for-target]');
    const preview = {
        title: find('.maintenance-form__preview-title'),
        message: find('.maintenance-form__preview-message'),
        when: find('.maintenance-form__preview-when'),
        step: find('.maintenance-form__notice-step'),
    };

    const value = (name) => find(`[name="${name}"]`)?.value ?? '';
    const checked = (name) => [...element.querySelectorAll(`[name="${name}"]:checked`)].map((input) => input.dataset.label);
    const date = (raw) => (raw ? new Date(raw).toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' }) : '…');

    const titleOf = (target) => {
        if (target === 'site') {
            return labels.site;
        }

        const names = checked(target === 'games' ? 'games[]' : 'features[]').join(', ') || labels.none;

        return `${names} ${target === 'games' ? labels.games : labels.features}`;
    };

    const update = () => {
        const target = find('[name="target"]:checked')?.value ?? 'site';

        sections.forEach((section) => {
            section.hidden = !section.dataset.forTarget.split(' ').includes(target);
        });

        preview.step.textContent = target === 'site' ? '4' : '3';
        preview.title.textContent = titleOf(target);
        preview.message.textContent = value('message');
        preview.when.textContent = `${date(value('starts_at'))} → ${date(value('ends_at'))}`;
    };

    element.addEventListener('input', update);
    element.addEventListener('change', update);
    update();
}
const space = document.body.dataset.space;

const load = async (name) => {
    try {
        return await import(`${space}/components/${name}`);
    } catch {
        return import(`shared/components/${name}`);
    }
};

const mount = async (element) => {
    if (element.dataset.mounted) {
        return;
    }

    element.dataset.mounted = 'true';

    try {
        const module = await load(element.dataset.component);
        module.default(element, { ...element.dataset });
    } catch (error) {
        console.error(`[data-component="${element.dataset.component}"]`, error);
    }
}

export default function mountComponents(root = document) {
    root.querySelectorAll('[data-component]').forEach(mount);
}

mountComponents();
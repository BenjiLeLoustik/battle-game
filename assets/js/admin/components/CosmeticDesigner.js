import badge from 'shared/badge';

const field = (form, name) => form.elements[name];
const values = (form, name) => [...form.querySelectorAll(`[name="${name}"]`)].map((input) => input.value);
const GLOW = { common: 0, rare: 0.5, epic: 0.8, legendary: 1.2 };

export default function CosmeticDesigner(element, data) {
    const form = element.closest('form');
    const icons = JSON.parse(data.icons);
    const stage = element.querySelector('[data-victory-stage]');
    const banner = element.querySelector('[data-banner-preview]');
    const file = form.querySelector('[data-image-input]');

    const frame = () => {
        const [a, b, c] = values(form, 'design[frame][colors][]');
        const paint = field(form, 'design[frame][paint]').value;
        const animation = field(form, 'design[frame][animation]').value;
        element.querySelectorAll('[data-frame-preview]').forEach((avatar) => {
            avatar.className = avatar.className.replace(/\s?avatar--frame-(paint|anim)-\S+/g, '') + ` avatar--frame-paint-${paint} avatar--frame-anim-${animation}`;
            avatar.style.cssText = `--frame-a:${a};--frame-b:${b};--frame-c:${c};--frame-width:${field(form, 'design[frame][width]').value}px;--frame-glow:${field(form, 'design[frame][glow]').value}px`;
        });
        element.querySelectorAll('.designer__choices--paint').forEach((group) => group.style.cssText = `--p-a:${a};--p-b:${b};--p-c:${c}`);
    };

    const paintBanner = () => {
        const [a, b, c] = values(form, 'design[banner][colors][]');
        const mode = field(form, 'design[banner][mode]').value || 'gradient';
        element.querySelectorAll('[data-banner-mode]').forEach((group) => {
            group.hidden = group.dataset.bannerMode !== mode;
        });
        const image = banner.dataset.upload || banner.dataset.image;
        banner.className = banner.className.replace(/\s?profile-banner--pattern-\S+/g, '') + ` profile-banner--pattern-${field(form, 'design[banner][pattern]').value}`;
        banner.style.cssText = `--banner-a:${a};--banner-b:${b};--banner-c:${c};--banner-angle:${field(form, 'design[banner][angle]').value}deg` + (mode === 'image' && image ? `;--banner-image:url('${image}')` : '');
        element.querySelectorAll('.designer__choices--pattern').forEach((group) => group.style.cssText = `--p-a:${a};--p-b:${b};--p-c:${c}`);
    };

    const view = () => {
        const rarity = field(form, 'design[victory][rarity]').value;

        return {
            name: '',
            path: icons[field(form, 'design[victory][icon]').value],
            design: { shape: field(form, 'design[victory][shape]').value, rarity, color: field(form, 'design[victory][color]').value, glow: GLOW[rarity] ?? 0.5, label: '', double: false },
        };
    };

    const victory = () => {
        element.querySelector('[data-victory-preview]').replaceChildren(badge(view(), 72));
    };

    const play = () => {
        stage.querySelector('.cosmetic-fx')?.remove();
        const layer = document.createElement('div');
        const width = stage.clientWidth;
        const height = stage.clientHeight;
        layer.className = `cosmetic-fx cosmetic-fx--${field(form, 'design[victory][animation]').value}`;

        for (let index = 0; index < Number(field(form, 'design[victory][count]').value); index += 1) {
            const piece = document.createElement('span');
            const angle = Math.random() * Math.PI * 2;
            const distance = 0.35 + Math.random() * 0.4;
            piece.className = 'cosmetic-fx__piece';
            piece.style.setProperty('--x', `${Math.round(Math.random() * width)}px`);
            piece.style.setProperty('--h', `${height + 60}px`);
            piece.style.setProperty('--delay', `${(Math.random() * 0.9).toFixed(2)}s`);
            piece.style.setProperty('--drift', `${Math.round((Math.random() - 0.5) * width * 0.5)}px`);
            piece.style.setProperty('--dx', `${Math.round(Math.cos(angle) * width * distance)}px`);
            piece.style.setProperty('--dy', `${Math.round(Math.sin(angle) * height * distance)}px`);
            piece.style.setProperty('--rise', `${Math.round(height * (0.55 + Math.random() * 0.35))}px`);
            piece.style.setProperty('--spin', `${Math.round((Math.random() - 0.5) * 720)}deg`);
            piece.append(badge(view(), 18 + Math.round(Math.random() * 14)));
            layer.append(piece);
        }

        stage.append(layer);
        setTimeout(() => layer.remove(), 4200);
    };

    const outputs = () => {
        element.querySelectorAll('input[type="range"]').forEach((input) => {
            const output = input.closest('.designer__range')?.querySelector('[data-range-output]');

            if (output) {
                output.textContent = `${input.value}${input.dataset.unit ?? ''}`;
            }
        });
        element.querySelectorAll('.designer__swatch').forEach((swatch) => {
            const input = swatch.querySelector('input[type="color"]');
            swatch.style.setProperty('--swatch', input.value);
            const hex = swatch.querySelector('[data-swatch-hex]');

            if (hex) {
                hex.textContent = input.value.toUpperCase();
            }
        });
    };

    const render = () => {
        outputs();
        frame();
        paintBanner();
        victory();
    };

    element.querySelectorAll('[data-preset]').forEach((button) => button.addEventListener('click', () => {
        Object.entries(JSON.parse(button.dataset.preset)).forEach(([name, value]) => {
            if (Array.isArray(value)) {
                form.querySelectorAll(`[name="${name}"]`).forEach((input, index) => {
                    input.value = value[index];
                });

                return;
            }

            const target = form.elements[name];

            if (target instanceof RadioNodeList || target?.length) {
                [...target].forEach((input) => {
                    input.checked = input.value === String(value);
                });
            } else if (target) {
                target.value = value;
            }
        });
        render();
    }));

    const upload = element.querySelector('[data-banner-upload]');

    file?.addEventListener('change', () => {
        const [image] = file.files;

        if (!image) {
            return;
        }

        banner.dataset.upload = URL.createObjectURL(image);

        if (upload) {
            const thumb = upload.querySelector('[data-banner-thumb]');
            const title = upload.querySelector('[data-upload-pick]');
            thumb.src = banner.dataset.upload;
            thumb.hidden = false;
            title.textContent = title.dataset.uploadChange;
            upload.querySelector('[data-upload-name]').textContent = image.name;
            upload.classList.add('designer__upload--filled');
        }

        paintBanner();
    });

    element.querySelectorAll('input[name="design[banner][mode]"]').forEach((input) => input.addEventListener('change', () => {
        if (input.checked && input.value === 'image' && !banner.dataset.upload && !banner.dataset.image) {
            file?.click();
        }
    }));

    element.querySelector('[data-victory-play]')?.addEventListener('click', play);
    element.addEventListener('input', render);
    element.addEventListener('change', render);
    render();
}

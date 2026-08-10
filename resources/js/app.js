import './bootstrap';
import Alpine from 'alpinejs';
import html2canvas from 'html2canvas-pro';

window.Alpine = Alpine;
Alpine.start();

/**
 * Client-side snapshot of the "Laporan Pencapaian" panel — avoids the
 * server-side headless-Chromium route, which is blocked by snap
 * confinement on the VPS (see AchievementWhatsappService history). Uses
 * html2canvas-pro (not plain html2canvas) because Tailwind v4's generated
 * CSS uses oklch()/oklab(), which plain html2canvas can't parse.
 *
 * To force the desktop 2-column layout regardless of the capturing device,
 * the target's grid uses a Tailwind *container* query (@container /
 * @3xl:grid-cols-2 in achievement-report.blade.php) instead of a viewport
 * media query. We clone the target into a hidden, fixed-width (1280px)
 * off-screen wrapper and capture the clone — a real DOM element at a real
 * width, so the container query resolves to "desktop" naturally. (An
 * earlier attempt used html2canvas's windowWidth option to fake a wider
 * viewport, but that made html2canvas crop the capture at the wrong
 * offset — visible as a misaligned background in the downloaded image.)
 */
window.captureAchievementSnapshot = async function (targetId, action, button) {
    const target = document.getElementById(targetId);
    if (!target) return;

    const originalLabel = button ? button.textContent : null;
    if (button) button.textContent = 'Memproses...';

    const wrapper = document.createElement('div');
    wrapper.style.position = 'fixed';
    wrapper.style.top = '0';
    wrapper.style.left = '-10000px';
    wrapper.style.width = '1280px';
    wrapper.style.zIndex = '-1';
    const clone = target.cloneNode(true);
    clone.removeAttribute('id');
    wrapper.appendChild(clone);
    document.body.appendChild(wrapper);

    try {
        const images = clone.querySelectorAll('img');
        await Promise.all(Array.from(images).map((img) => img.complete ? Promise.resolve() : new Promise((resolve) => {
            img.addEventListener('load', resolve, { once: true });
            img.addEventListener('error', resolve, { once: true });
        })));

        const canvas = await html2canvas(clone, { backgroundColor: '#e8ecff', scale: 2, useCORS: true });
        await new Promise((resolve) => {
            canvas.toBlob((blob) => {
                if (!blob) { resolve(); return; }
                const url = URL.createObjectURL(blob);
                if (action === 'download') {
                    const link = document.createElement('a');
                    link.href = url;
                    link.download = 'laporan-pencapaian-' + new Date().toISOString().slice(0, 10) + '.png';
                    link.click();
                } else {
                    window.open(url, '_blank');
                }
                setTimeout(() => URL.revokeObjectURL(url), 30000);
                resolve();
            }, 'image/png');
        });
    } finally {
        wrapper.remove();
        if (button && originalLabel) button.textContent = originalLabel;
    }
};

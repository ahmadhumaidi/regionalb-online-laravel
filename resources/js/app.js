import './bootstrap';
import Alpine from 'alpinejs';
import html2canvas from 'html2canvas-pro';

window.Alpine = Alpine;
Alpine.start();

/**
 * Keeps the scroll position stable across a full-page reload triggered by a
 * "Hapus" (delete) form — the controller redirects back to the same URL
 * (same filters), but a fresh page load still starts at the top by default.
 */
const SCROLL_KEY_PREFIX = 'preserveScroll:';
document.addEventListener('submit', (event) => {
    const form = event.target;
    if (form instanceof HTMLFormElement && form.hasAttribute('data-preserve-scroll')) {
        sessionStorage.setItem(SCROLL_KEY_PREFIX + location.pathname + location.search, String(window.scrollY));
    }
});
window.addEventListener('DOMContentLoaded', () => {
    const key = SCROLL_KEY_PREFIX + location.pathname + location.search;
    const savedY = sessionStorage.getItem(key);
    if (savedY !== null) {
        sessionStorage.removeItem(key);
        window.scrollTo(0, parseInt(savedY, 10));
    }
});

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

/**
 * Daily Mission "TODAY"/"THIS WEEK" countdowns (profile.index) - ticks a
 * single element's text down to a target ISO timestamp every second. Shows
 * "NdNhNm" once a day or more remains, "NhNmNs" once under a day, freezing
 * at "00:00:00" past the target rather than going negative.
 */
window.startCountdown = function (el, targetIso) {
    const target = new Date(targetIso).getTime();

    const tick = () => {
        const diff = Math.max(0, target - Date.now());
        const totalSeconds = Math.floor(diff / 1000);
        const days = Math.floor(totalSeconds / 86400);
        const hours = Math.floor((totalSeconds % 86400) / 3600);
        const minutes = Math.floor((totalSeconds % 3600) / 60);
        const seconds = totalSeconds % 60;

        el.textContent = days > 0
            ? `${days}D ${hours}H ${minutes}M`
            : `${String(hours).padStart(2, '0')}H ${String(minutes).padStart(2, '0')}M ${String(seconds).padStart(2, '0')}S`;
    };

    tick();

    return setInterval(tick, 1000);
};

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-countdown-target]').forEach((el) => {
        const target = el.getAttribute('data-countdown-target');
        if (target) window.startCountdown(el, target);
    });
});

/**
 * Progress bar "grow from 0" animation on page load - the .progress-fill
 * bars (Level XP, Daily Mission, Pencapaian Kampus/Staff, Anggaran limit,
 * etc) all render with their final width already inline, so without this
 * they'd just appear instantly instead of filling in. Double rAF makes
 * sure the browser paints width:0 first before the transition to the
 * target width kicks in - a single rAF sometimes lands in the same frame
 * as the initial render and the transition never fires.
 */
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.progress-fill').forEach((el) => {
        const target = el.style.width;
        el.style.width = '0%';
        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                el.style.width = target;
            });
        });
    });
});

/**
 * Click-burst cursor effect (circles/triangles/a shockwave disc) at the
 * click point - purely decorative, see the .click-fx* rules in app.css.
 * Builds a fresh element per click and removes it once its animation
 * finishes, rather than reusing a fixed pool, so overlapping rapid clicks
 * don't fight over shared nodes.
 */
const CLICK_FX_COLORS = [
    'var(--color-brand-400)', 'var(--color-brand-500)', 'var(--color-brand-600)',
    'var(--color-tone-green)', 'var(--color-tone-amber)', 'var(--color-tone-red)',
];
document.addEventListener('click', (event) => {
    const fx = document.createElement('div');
    fx.className = 'click-fx';
    fx.style.left = event.clientX + 'px';
    fx.style.top = event.clientY + 'px';

    const disc = document.createElement('span');
    disc.className = 'click-fx-disc';
    fx.appendChild(disc);

    for (let i = 0; i < 6; i++) {
        const circle = document.createElement('span');
        circle.className = 'click-fx-circle';
        circle.style.setProperty('--angle', (i * 60) + 'deg');
        circle.style.setProperty('--dot-color', CLICK_FX_COLORS[i % CLICK_FX_COLORS.length]);
        fx.appendChild(circle);
    }

    for (let i = 0; i < 3; i++) {
        const triangle = document.createElement('span');
        triangle.className = 'click-fx-triangle';
        triangle.style.setProperty('--angle', (i * 120 + 30) + 'deg');
        fx.appendChild(triangle);
    }

    document.body.appendChild(fx);
    setTimeout(() => fx.remove(), 600);
});

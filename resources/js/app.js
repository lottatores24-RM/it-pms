import Alpine from 'alpinejs';
import { initKanban } from './kanban';

window.Alpine = Alpine;

document.addEventListener('DOMContentLoaded', () => {
    initKanban();

    // Chart.js is only downloaded on pages that actually contain a chart.
    if (document.querySelector('canvas[data-chart]')) {
        import('./charts').then(({ initCharts }) => initCharts());
    }
});

// Forms marked with data-confirm open the global confirmation dialog
// (components/confirm-dialog.blade.php) before they are submitted.
document.addEventListener('submit', (event) => {
    const form = event.target;

    if (!(form instanceof HTMLFormElement) || !form.dataset.confirm) {
        return;
    }

    if (form.dataset.confirmed) {
        delete form.dataset.confirmed;
        return;
    }

    event.preventDefault();

    window.dispatchEvent(new CustomEvent('confirm-dialog', {
        detail: {
            form,
            title: form.dataset.confirmTitle || 'Konfirmasi',
            message: form.dataset.confirm,
            button: form.dataset.confirmButton || 'Ya, lanjutkan',
            danger: form.dataset.confirmVariant !== 'primary',
        },
    }));
});

// Numbers on dashboards count up from zero once; the server-rendered value
// stays in place when motion is reduced or JavaScript is unavailable.
Alpine.data('countUp', (target) => ({
    display: target.toLocaleString('id-ID'),
    init() {
        if (target <= 0 || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            return;
        }

        const duration = 900;
        const start = performance.now();
        const step = (now) => {
            const progress = Math.min((now - start) / duration, 1);
            this.display = Math.round(target * (1 - (1 - progress) ** 3)).toLocaleString('id-ID');

            if (progress < 1) {
                requestAnimationFrame(step);
            }
        };

        this.display = '0';
        requestAnimationFrame(step);
    },
}));

Alpine.start();

import { CategoryScale, Chart, Filler, LinearScale, LineController, LineElement, PointElement, Tooltip } from 'chart.js';

Chart.register(LineController, LineElement, PointElement, CategoryScale, LinearScale, Filler, Tooltip);

/**
 * Dashboard charts. Each <canvas data-chart="..."> carries its data as JSON in
 * data-chart-data; every value is also on the page as text or in a table, so
 * nothing depends on the canvas or the tooltip alone.
 */
const SURFACE = '#ffffff';
const GRID = '#eef0f3';
const AXIS = '#e5e7eb';
const INK = '#111827';
const TEXT_MUTED = '#6b7280';
const FONT = { family: 'Figtree, ui-sans-serif, system-ui, sans-serif', size: 12 };
const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

Chart.defaults.font = FONT;
Chart.defaults.color = TEXT_MUTED;

const formatNumber = (value) => value.toLocaleString('id-ID', { maximumFractionDigits: 2 });

const withAlpha = (hex, alpha) => {
    const [r, g, b] = [1, 3, 5].map((start) => parseInt(hex.slice(start, start + 2), 16));

    return `rgba(${r}, ${g}, ${b}, ${alpha})`;
};

/**
 * A hairline that follows the hovered x, so readers aim at a date, not a line.
 */
const crosshair = {
    id: 'crosshair',
    beforeDatasetsDraw(chart) {
        const active = chart.tooltip?.getActiveElements() ?? [];
        if (active.length === 0) {
            return;
        }

        const { ctx, chartArea } = chart;
        const x = active[0].element.x;

        ctx.save();
        ctx.strokeStyle = '#d1d5db';
        ctx.lineWidth = 1;
        ctx.beginPath();
        ctx.moveTo(x, chartArea.top);
        ctx.lineTo(x, chartArea.bottom);
        ctx.stroke();
        ctx.restore();
    },
};

/**
 * Marks the status date ("Hari ini") with a solid rule and a small label pill.
 */
const todayMarker = {
    id: 'todayMarker',
    beforeDatasetsDraw(chart, args, options) {
        if (options.index === null || options.index === undefined) {
            return;
        }

        const { ctx, chartArea } = chart;
        const x = chart.scales.x.getPixelForValue(options.index);
        const label = options.label ?? 'Hari ini';

        ctx.save();
        ctx.strokeStyle = '#9ca3af';
        ctx.lineWidth = 1;
        ctx.beginPath();
        ctx.moveTo(x, chartArea.top);
        ctx.lineTo(x, chartArea.bottom);
        ctx.stroke();

        ctx.font = `600 11px ${FONT.family}`;
        const width = ctx.measureText(label).width + 14;
        const left = Math.min(Math.max(x - width / 2, chartArea.left), chartArea.right - width);
        ctx.fillStyle = INK;
        ctx.beginPath();
        ctx.roundRect(left, chartArea.top - 22, width, 18, 9);
        ctx.fill();
        ctx.fillStyle = SURFACE;
        ctx.textBaseline = 'middle';
        ctx.fillText(label, left + 7, chartArea.top - 13);
        ctx.restore();
    },
};

/**
 * HTML tooltip: the value leads, the series name follows, each row keyed by a
 * short stroke of the series line. Names are inserted with textContent.
 */
function htmlTooltip({ chart, tooltip }) {
    const host = chart.canvas.parentNode;
    let box = host.querySelector('[data-chart-tooltip]');

    if (!box) {
        box = document.createElement('div');
        box.dataset.chartTooltip = '';
        box.className = 'pointer-events-none absolute z-10 min-w-[11rem] rounded-xl bg-white/95 px-3.5 py-3 text-xs shadow-xl ring-1 ring-gray-900/10 backdrop-blur transition-opacity duration-150 ease-out';
        host.appendChild(box);
    }

    const points = (tooltip.dataPoints ?? []).filter((point) => point.parsed.y !== null);
    if (tooltip.opacity === 0 || points.length === 0) {
        box.style.opacity = '0';

        return;
    }

    const title = document.createElement('p');
    title.className = 'mb-2 font-medium text-gray-500';
    title.textContent = tooltip.title?.[0] ?? '';

    const rows = points.map((point) => {
        const dataset = point.dataset;
        const row = document.createElement('div');
        row.className = 'flex items-center gap-2.5 py-0.5';

        const key = document.createElement('span');
        key.className = 'w-3.5 shrink-0 border-t-2';
        key.style.borderColor = dataset.borderColor;
        key.style.borderStyle = dataset.borderDash?.length ? 'dashed' : 'solid';

        const value = document.createElement('span');
        value.className = 'text-sm font-semibold tabular-nums text-gray-900';
        value.textContent = formatNumber(point.parsed.y);

        const name = document.createElement('span');
        name.className = 'text-gray-500';
        name.textContent = dataset.label;

        row.append(key, value, name);

        return row;
    });

    box.replaceChildren(title, ...rows);

    // Anchor beside the hovered x (the crosshair), aligned with the top of the
    // plot so the box never covers the lines; flip left near the right edge.
    const x = points[0].element.x;
    const left = x + 16 + box.offsetWidth > chart.chartArea.right
        ? x - box.offsetWidth - 16
        : x + 16;
    box.style.left = `${Math.max(chart.chartArea.left, left)}px`;
    box.style.top = `${chart.chartArea.top}px`;
    box.style.opacity = '1';
}

/**
 * EVM S-curve (FR-022): cumulative PV, EV and AC on one effort-point axis.
 * PV is dashed (the plan), EV carries a soft area wash (what was earned), and
 * EV/AC stop at the status date (null values leave the rest of the line out).
 */
function evmCurveChart(canvas, data) {
    return new Chart(canvas, {
        type: 'line',
        data: {
            labels: data.labels,
            datasets: data.series.map((series) => ({
                label: series.label,
                data: series.values,
                borderColor: series.color,
                borderWidth: 2,
                borderDash: series.dashed ? [6, 5] : [],
                borderCapStyle: 'round',
                borderJoinStyle: 'round',
                cubicInterpolationMode: 'monotone',
                fill: series.fill ? 'origin' : false,
                backgroundColor: series.fill
                    ? ({ chart }) => {
                        if (!chart.chartArea) {
                            return withAlpha(series.color, 0.1);
                        }

                        const gradient = chart.ctx.createLinearGradient(0, chart.chartArea.top, 0, chart.chartArea.bottom);
                        gradient.addColorStop(0, withAlpha(series.color, 0.22));
                        gradient.addColorStop(1, withAlpha(series.color, 0));

                        return gradient;
                    }
                    : series.color,
                pointRadius: 0,
                pointHitRadius: 12,
                pointHoverRadius: 5,
                pointHoverBackgroundColor: series.color,
                pointHoverBorderColor: SURFACE,
                pointHoverBorderWidth: 2,
            })),
        },
        options: {
            maintainAspectRatio: false,
            animation: reducedMotion ? false : { duration: 1000, easing: 'easeOutQuart' },
            layout: { padding: { top: 26, right: 8 } },
            interaction: { mode: 'index', intersect: false },
            plugins: {
                tooltip: { enabled: false, external: htmlTooltip },
                todayMarker: { index: data.todayIndex, label: 'Hari ini' },
            },
            scales: {
                x: {
                    grid: { display: false },
                    border: { color: AXIS },
                    ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: 6, padding: 8 },
                },
                y: {
                    beginAtZero: true,
                    grid: { color: GRID, lineWidth: 1 },
                    border: { display: false },
                    ticks: { padding: 10, maxTicksLimit: 6, callback: (value) => formatNumber(value) },
                    title: { display: true, text: 'Effort point', color: TEXT_MUTED, font: { size: 11 } },
                },
            },
        },
        plugins: [crosshair, todayMarker],
    });
}

const builders = { 'evm-curve': evmCurveChart };

export function initCharts() {
    document.querySelectorAll('canvas[data-chart]').forEach((canvas) => {
        const build = builders[canvas.dataset.chart];
        if (build) {
            build(canvas, JSON.parse(canvas.dataset.chartData));
        }
    });
}

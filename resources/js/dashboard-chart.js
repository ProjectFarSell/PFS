import {
    ArcElement, BarController, BarElement, CategoryScale, Chart, DoughnutController,
    Legend, LinearScale, LineController, LineElement, PointElement, Tooltip,
} from 'chart.js';
import { formatMetric } from './dashboard-format';

Chart.register(
    ArcElement, BarController, BarElement, CategoryScale, DoughnutController,
    Legend, LinearScale, LineController, LineElement, PointElement, Tooltip,
);

export function renderChart(canvas, spec, palette, enlarged = false) {
    const { unit, type, stacked } = spec;
    const donut = type === 'doughnut';
    const seriesColors = spec.labels.map((_, index) => palette.series[index % palette.series.length]);
    return new Chart(canvas, {
        type,
        data: {
            labels: [...spec.labels],
            datasets: spec.datasets.map((dataset, index) => {
                const color = palette.series[index % palette.series.length];
                return {
                    label: dataset.label,
                    data: [...dataset.values],
                    backgroundColor: donut || (type === 'bar' && spec.datasets.length === 1) ? seriesColors : color,
                    borderColor: donut ? palette.surface : color,
                    pointRadius: spec.labels.length > 30 ? 0 : 2,
                    borderDash: index % 2 ? [6, 4] : [],
                };
            }),
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: false,
            locale: 'en-PH',
            plugins: {
                legend: { display: donut || spec.datasets.length > 1, labels: { color: palette.text, boxWidth: 12, font: { size: enlarged ? 16 : 12 } } },
                tooltip: {
                    callbacks: {
                        label: context => `${context.dataset.label}: ${formatMetric(context.raw, unit)}`,
                    },
                },
            },
            ...(donut ? {} : { scales: {
                x: { stacked, ticks: { color: palette.text, maxTicksLimit: enlarged ? 5 : 8, font: { size: enlarged ? 16 : 12 } }, grid: { display: false } },
                y: {
                    stacked,
                    beginAtZero: true,
                    ...(unit === 'percent' ? { max: 100 } : {}),
                    ticks: { color: palette.text, maxTicksLimit: 5, font: { size: enlarged ? 16 : 12 }, callback: value => formatMetric(value, unit) },
                    grid: { color: palette.border },
                },
            } }),
        },
    });
}

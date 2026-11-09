import { formatMetric } from './dashboard-format';

function nearViewport(element, callback) {
    if (!('IntersectionObserver' in window)) {
        callback();
        return () => {};
    }
    const observer = new IntersectionObserver(entries => {
        if (!entries.some(entry => entry.isIntersecting)) return;
        observer.disconnect();
        callback();
    }, { rootMargin: '180px' });
    observer.observe(element);
    return () => observer.disconnect();
}

export function registerDashboards(Alpine) {
    Alpine.magic('dashboardMetric', () => formatMetric);

    Alpine.data('dashboardDeferred', () => {
        let cancel = () => {};
        return {
            ready: false,
            init() {
                cancel = nearViewport(this.$el, () => { this.ready = true; });
            },
            destroy() {
                cancel();
            },
        };
    });

    Alpine.data('dashboardChart', source => {
        let chartInstance;
        let renderer;
        let spec = source();
        let enlarged = false;
        let cancel = () => {};
        let disposed = false;

        return {
            get chart() {
                return source();
            },
            loading: true,
            failed: false,
            init() {
                cancel = nearViewport(this.$el, async () => {
                    try {
                        const module = await import('./dashboard-chart');
                        if (disposed) return;
                        renderer = module.renderChart;
                        this.draw();
                    } catch (error) {
                        if (!disposed) {
                            this.failed = true;
                            console.error('Could not render the dashboard chart.', error);
                        }
                    } finally {
                        if (!disposed) this.loading = false;
                    }
                });
            },
            update(nextSpec, presentation = false) {
                spec = nextSpec;
                enlarged = presentation;
                if (renderer) this.draw();
            },
            draw() {
                const palette = getComputedStyle(this.$refs.canvas);
                chartInstance?.destroy();
                chartInstance = renderer(this.$refs.canvas, spec, {
                    series: [...this.$el.querySelectorAll('[data-chart-color]')].map(element => getComputedStyle(element).color),
                    text: palette.color,
                    surface: palette.backgroundColor,
                    border: palette.borderTopColor,
                }, enlarged);
            },
            destroy() {
                disposed = true;
                cancel();
                chartInstance?.destroy();
            },
        };
    });
}

import './bootstrap';
import Alpine from 'alpinejs';

window.Alpine = Alpine;

// A reactive store keeps all toggle icons and accessible labels synchronized.
Alpine.store('theme', window.FarSellTheme);
window.FarSellTheme = Alpine.store('theme');
Alpine.magic('theme', () => Alpine.store('theme'));

// ── Carousel Alpine component ─────────────────────────────────────────────────
Alpine.data('carousel', (total = 1, autoplay = 5000) => ({
    current: 0,
    total,
    timer: null,

    init() {
        if (autoplay > 0) {
            this.timer = setInterval(() => this.next(), autoplay);
        }
    },
    destroy() {
        if (this.timer) clearInterval(this.timer);
    },
    next() {
        this.current = (this.current + 1) % this.total;
    },
    prev() {
        this.current = (this.current - 1 + this.total) % this.total;
    },
    goTo(index) {
        this.current = index;
        // Reset autoplay on manual interaction
        if (this.timer) {
            clearInterval(this.timer);
            if (autoplay > 0) this.timer = setInterval(() => this.next(), autoplay);
        }
    },
    isActive(index) {
        return this.current === index;
    },
}));

Alpine.start();

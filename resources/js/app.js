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
    paused: false,
    touchStartX: 0,

    init() {
        if (this.total === 0) return; // guard: empty carousel — no errors
        if (autoplay > 0 && !this.paused) {
            this.timer = setInterval(() => this.next(), autoplay);
        }
    },
    destroy() {
        if (this.timer) clearInterval(this.timer);
    },
    next() {
        if (this.total === 0) return;
        this.current = (this.current + 1) % this.total;
    },
    prev() {
        if (this.total === 0) return;
        this.current = (this.current - 1 + this.total) % this.total;
    },
    goTo(index) {
        if (this.total === 0) return;
        this.current = index;
        if (this.timer) {
            clearInterval(this.timer);
            if (autoplay > 0 && !this.paused) {
                this.timer = setInterval(() => this.next(), autoplay);
            }
        }
    },
    isActive(index) {
        return this.current === index;
    },
    pause() {
        this.paused = true;
        if (this.timer) { clearInterval(this.timer); this.timer = null; }
    },
    play() {
        this.paused = false;
        if (autoplay > 0 && !this.timer) {
            this.timer = setInterval(() => this.next(), autoplay);
        }
    },
    togglePlay() {
        this.paused ? this.play() : this.pause();
    },
    onTouchStart(e) {
        this.touchStartX = e.changedTouches[0].clientX;
    },
    onTouchEnd(e) {
        const delta = this.touchStartX - e.changedTouches[0].clientX;
        if (Math.abs(delta) >= 50) {
            delta > 0 ? this.next() : this.prev();
        }
    },
}));

// ── Countdown Alpine component ────────────────────────────────────────────────
Alpine.data('countdown', (endTime) => ({
    display: '00:00:00',
    timer: null,

    init() {
        this.tick();
        this.timer = setInterval(() => this.tick(), 1000);
    },
    destroy() {
        if (this.timer) clearInterval(this.timer);
    },
    tick() {
        const remaining = Math.max(0, endTime - Math.floor(Date.now() / 1000));
        if (remaining === 0) {
            this.display = 'Ended';
            clearInterval(this.timer);
            this.timer = null;
            return;
        }
        const h = String(Math.floor(remaining / 3600)).padStart(2, '0');
        const m = String(Math.floor((remaining % 3600) / 60)).padStart(2, '0');
        const s = String(remaining % 60).padStart(2, '0');
        this.display = `${h}:${m}:${s}`;
    },
}));

Alpine.start();

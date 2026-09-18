import './bootstrap';
import Alpine from 'alpinejs';

window.Alpine = Alpine;

// ── Theme (dark / light) ──────────────────────────────────────────────────────
// Reads from localStorage on load, falls back to OS preference.
// Exposes window.$theme so any component can call $theme.toggle().
document.addEventListener('DOMContentLoaded', () => {
    const saved = localStorage.getItem('farsell_theme');
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    const isDark = saved ? saved === 'dark' : prefersDark;

    if (isDark) {
        document.documentElement.classList.add('dark');
    } else {
        document.documentElement.classList.remove('dark');
    }
});

// Alpine magic: $theme
Alpine.magic('theme', () => ({
    get isDark() {
        return document.documentElement.classList.contains('dark');
    },
    toggle() {
        const isDark = document.documentElement.classList.toggle('dark');
        localStorage.setItem('farsell_theme', isDark ? 'dark' : 'light');
    },
    set(mode) {
        if (mode === 'dark') {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
        localStorage.setItem('farsell_theme', mode);
    },
}));

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

// Runs before paint and is shared by every Alpine theme toggle.
(() => {
    let choice = null;
    try {
        const saved = window.localStorage.getItem('farsell_theme');
        if (saved === 'dark' || saved === 'light') choice = saved;
    } catch { /* Storage may be blocked; theme switching must still work. */ }

    window.FarSellTheme = {
        isDark: choice === 'dark',
        apply() {
            document.documentElement.classList.toggle('dark', this.isDark);
        },
        toggle() {
            this.set(this.isDark ? 'light' : 'dark');
        },
        set(mode, persist = true) {
            if (mode !== 'dark' && mode !== 'light') return;
            this.isDark = mode === 'dark';
            this.apply();
            if (persist) {
                choice = mode;
                try { window.localStorage.setItem('farsell_theme', mode); } catch { /* Optional persistence. */ }
            }
        },
    };
    window.FarSellTheme.apply();
    window.addEventListener('storage', (event) => {
        if (event.key !== 'farsell_theme' && event.key !== null) return;
        choice = event.newValue === 'dark' || event.newValue === 'light' ? event.newValue : null;
        window.FarSellTheme.set(choice || 'light', false);
    });
})();

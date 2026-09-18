// Runs before paint and is shared by every Alpine theme toggle.
(() => {
    const media = window.matchMedia('(prefers-color-scheme: dark)');
    let choice = null;
    try {
        const saved = window.localStorage.getItem('farsell_theme');
        if (saved === 'dark' || saved === 'light') choice = saved;
    } catch { /* Storage may be blocked; theme switching must still work. */ }

    window.FarSellTheme = {
        isDark: choice ? choice === 'dark' : media.matches,
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
    media.addEventListener('change', (event) => {
        if (!choice) window.FarSellTheme.set(event.matches ? 'dark' : 'light', false);
    });
    window.addEventListener('storage', (event) => {
        if (event.key !== 'farsell_theme' && event.key !== null) return;
        choice = event.newValue === 'dark' || event.newValue === 'light' ? event.newValue : null;
        window.FarSellTheme.set(choice || (media.matches ? 'dark' : 'light'), false);
    });
})();

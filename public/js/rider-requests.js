(() => {
    const panel = document.querySelector('[data-delivery-requests]');
    if (!panel) return;
    const status = document.querySelector('[data-request-refresh-status]');
    let busy = false;
    let previous = '';
    async function refresh() {
        if (busy || document.hidden) return;
        busy = true;
        try {
            const response = await fetch(panel.dataset.url, {headers: {Accept: 'application/json'}, credentials: 'same-origin'});
            if (!response.ok) throw new Error('unavailable');
            const data = await response.json();
            // Do not replace a focused accept button while someone is using the keyboard.
            if (data.html !== previous && !panel.contains(document.activeElement)) {
                panel.innerHTML = data.html;
                previous = data.html;
            }
            status.textContent = 'Requests updated. Checks every 15 seconds while this page is open.';
        } catch {
            panel.replaceChildren();
            previous = '';
            status.textContent = 'Could not refresh requests. Check your connection or reload to verify rider access.';
        } finally {
            busy = false;
        }
    }
    refresh();
    setInterval(refresh, 15000);
    document.addEventListener('visibilitychange', refresh);
})();

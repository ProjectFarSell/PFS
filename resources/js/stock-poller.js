export function createStockPoller({ url, onData, onError = () => {}, intervalMs = 10000, timeoutMs = 8000,
    documentRef = document, windowRef = window, fetchImpl = fetch }) {
    let timer = null;
    let current = null;
    let paused = false;
    let stopped = false;

    function cancel() {
        const request = current;
        current = null;
        if (request) {
            clearTimeout(request.timeout);
            request.controller.abort();
        }
    }

    async function refresh() {
        if (stopped || paused || documentRef.hidden || current) return;
        const request = { controller: new AbortController(), timeout: null };
        current = request;
        request.timeout = setTimeout(() => {
            if (current !== request) return;
            cancel();
            onError(new Error('Stock request timed out'));
        }, timeoutMs);
        try {
            const response = await fetchImpl(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin', cache: 'no-store', signal: request.controller.signal });
            if (!response.ok) {
                const error = new Error('Stock request failed');
                error.status = response.status;
                throw error;
            }
            const data = await response.json();
            if (current === request && !paused && !documentRef.hidden) onData(data);
        } catch (error) {
            if (current === request && !request.controller.signal.aborted) onError(error);
        } finally {
            clearTimeout(request.timeout);
            if (current === request) current = null;
        }
    }

    const visibility = () => documentRef.hidden ? cancel() : refresh();
    const show = () => { paused = false; cancel(); refresh(); };
    const hide = () => { paused = true; cancel(); };
    const focus = () => refresh();
    documentRef.addEventListener('visibilitychange', visibility);
    windowRef.addEventListener('focus', focus);
    windowRef.addEventListener('pageshow', show);
    windowRef.addEventListener('pagehide', hide);
    timer = windowRef.setInterval(refresh, intervalMs);
    refresh();

    return {
        refresh,
        stop() {
            stopped = true;
            cancel();
            windowRef.clearInterval(timer);
            documentRef.removeEventListener('visibilitychange', visibility);
            windowRef.removeEventListener('focus', focus);
            windowRef.removeEventListener('pageshow', show);
            windowRef.removeEventListener('pagehide', hide);
        },
    };
}

// This form enhancement is served by Laravel, even when Vite is unavailable.
(() => {
    document.querySelectorAll('[data-address-location-form]').forEach((form) => {
        if (form.dataset.locationReady) return;
        form.dataset.locationReady = 'true';

        const fields = ['psgc_region_id', 'psgc_province_id', 'psgc_city_municipality_id', 'psgc_barangay_id']
            .map((name) => form.elements.namedItem(name));
        const initial = fields.map((field) => field.dataset.selected || field.value);
        const placeholders = fields.map((field) => field.options[0].textContent);
        const status = form.querySelector('[data-location-status]');
        const retry = form.querySelector('[data-location-retry]');
        const save = form.querySelector('[data-address-save]');
        let generation = 0;
        let controller;
        let retryAction;

        function updateSave() {
            save.disabled = fields.some((field) => field.disabled || !field.value);
        }

        async function loadFrom(start, restore = false) {
            const version = ++generation;
            controller?.abort();
            controller = new AbortController();
            const requestController = controller;
            const signal = requestController.signal;
            retry.hidden = true;
            status.textContent = '';
            for (let i = start; i < fields.length; i++) {
                fields[i].replaceChildren(new Option(placeholders[i], ''));
                fields[i].disabled = true;
            }
            updateSave();

            for (let i = start; i < fields.length && fields[i - 1].value; i++) {
                const field = fields[i];
                const parent = fields[i - 1].value;
                const url = new URL(field.dataset.url, window.location.origin);
                url.searchParams.set(field.dataset.parentParam, parent);
                status.textContent = 'Loading locations…';
                const timeout = setTimeout(() => requestController.abort(), 15000);
                try {
                    const response = await fetch(url, {
                        headers: { Accept: 'application/json' },
                        credentials: 'same-origin', signal,
                    });
                    if (!response.ok) throw new Error('Location request failed');
                    const rows = await response.json();
                    if (!Array.isArray(rows) || !rows.every((row) => row && row.id != null && typeof row.name === 'string')) {
                        throw new Error('Invalid location response');
                    }
                    if (version !== generation) return;
                    field.replaceChildren(new Option(placeholders[i], ''),
                        ...rows.map((row) => new Option(row.name, String(row.id))));
                    field.disabled = rows.length === 0;
                    if (!rows.length) throw new Error('No locations available');
                    // Set the saved value after inserting options (edit/validation reload).
                    if (restore) field.value = initial[i];
                    status.textContent = '';
                } catch {
                    if (version !== generation) return;
                    status.textContent = 'Unable to load locations. Check your connection or sign in again, then retry.';
                    retry.hidden = false;
                    retryAction = () => loadFrom(i, restore);
                    break;
                } finally {
                    clearTimeout(timeout);
                }
            }
            if (version === generation) updateSave();
        }

        fields.forEach((field, index) => field.addEventListener('change', () => {
            if (index < fields.length - 1) loadFrom(index + 1);
            else updateSave();
        }));
        retry.addEventListener('click', () => retryAction?.());
        form.addEventListener('submit', (event) => {
            if (fields.some((field) => field.disabled || !field.value)) {
                event.preventDefault();
                status.textContent = 'Select all four address levels before saving.';
            }
        });
        loadFrom(1, true);
    });
})();

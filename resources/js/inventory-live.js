const inventory = document.querySelector('[data-inventory-live]');

if (inventory) {
    const results = inventory.querySelector('[data-inventory-results]');
    const status = inventory.querySelector('[data-inventory-live-status]');
    const button = inventory.querySelector('[data-inventory-refresh]');
    let activeRequest = null;
    let stopped = false;

    // Keep entered values intact while a stock dialog or filter is in use.
    const isInteracting = () => inventory.querySelector('dialog[open]')
        || document.activeElement?.closest('#inventory-filter-form');

    const refresh = async () => {
        if (stopped || activeRequest || document.hidden || isInteracting()) return;

        const request = new AbortController();
        activeRequest = request;
        const timeout = window.setTimeout(() => request.abort(), 10000);
        button.disabled = true;
        results.setAttribute('aria-busy', 'true');

        try {
            const response = await fetch(window.location.href, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                cache: 'no-store',
                signal: request.signal,
            });
            if ([401, 403, 404, 419].includes(response.status) || response.redirected) {
                stopped = true;
                status.textContent = 'Stock updates stopped. Reload this page to check your access.';
                return;
            }
            if (! response.ok) throw new Error('Stock update failed.');
            const data = await response.json();
            if (typeof data.html !== 'string') throw new Error('Invalid stock response.');
            if (document.hidden || isInteracting()) return;

            if (results.innerHTML !== data.html) {
                const focused = results.contains(document.activeElement) ? document.activeElement : null;
                const dialogId = focused?.dataset.dialogOpen;
                const href = focused?.getAttribute('href');
                results.innerHTML = data.html;
                // Row actions can retain focus even after closing a dialog.
                // Preserve it across updates so polling can resume normally.
                const replacement = dialogId
                    ? results.querySelector(`[data-dialog-open="${CSS.escape(dialogId)}"]`)
                    : Array.from(results.querySelectorAll('a[href]')).find((link) => link.getAttribute('href') === href);
                replacement?.focus({ preventScroll: true });
            }
            status.textContent = `Stock updated at ${new Date().toLocaleTimeString()}.`;
        } catch (error) {
            if (! stopped) status.textContent = 'Could not update stock. Retrying automatically, or use Refresh stock.';
        } finally {
            window.clearTimeout(timeout);
            activeRequest = null;
            button.disabled = false;
            results.removeAttribute('aria-busy');
        }
    };

    button.addEventListener('click', refresh);
    let timer = window.setInterval(refresh, 15000);
    document.addEventListener('visibilitychange', refresh);
    window.addEventListener('online', refresh);
    window.addEventListener('pagehide', () => {
        stopped = true;
        window.clearInterval(timer);
        activeRequest?.abort();
    });
    window.addEventListener('pageshow', (event) => {
        if (! event.persisted) return;
        stopped = false;
        timer = window.setInterval(refresh, 15000);
        return refresh();
    });
}
